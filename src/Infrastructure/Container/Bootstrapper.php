<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Application\Events\DomainEventDispatcher;
use App\Application\Events\Subscribers\GenerateLedgerOnPaymentApproved;
use App\Application\Events\Subscribers\ReleaseStockOnOrderCanceled;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Events\OrderCanceledEvent;
use App\Domain\Events\PaymentReceiptApprovedEvent;
use App\Domain\Events\ReservationExpiredEvent;
use App\Domain\Repositories\AddressRepositoryInterface;
use App\Domain\Repositories\BankCardRepositoryInterface;
use App\Domain\Repositories\BranchRepositoryInterface;
use App\Domain\Repositories\CartRepositoryInterface;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\InventoryTransactionRepositoryInterface;
use App\Domain\Repositories\LedgerRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShippingGroupRepositoryInterface;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TicketRepositoryInterface;
use App\Domain\Repositories\UserAssignmentRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Database\SqliteConnection;
use App\Infrastructure\Database\TransactionManager;
use App\Infrastructure\Repositories\SqliteAddressRepository;
use App\Infrastructure\Repositories\SqliteBankCardRepository;
use App\Infrastructure\Repositories\SqliteBranchRepository;
use App\Infrastructure\Repositories\SqliteCartRepository;
use App\Infrastructure\Repositories\SqliteCategoryRepository;
use App\Infrastructure\Repositories\SqliteInventoryTransactionRepository;
use App\Infrastructure\Repositories\SqliteLedgerRepository;
use App\Infrastructure\Repositories\SqliteOrderRepository;
use App\Infrastructure\Repositories\SqliteProductRepository;
use App\Infrastructure\Repositories\SqliteShippingGroupRepository;
use App\Infrastructure\Repositories\SqliteShopRepository;
use App\Infrastructure\Repositories\SqliteSubscriptionPlanRepository;
use App\Infrastructure\Repositories\SqliteTicketRepository;
use App\Infrastructure\Repositories\SqliteUserAssignmentRepository;
use App\Infrastructure\Repositories\SqliteUserRepository;
use App\Infrastructure\Security\CsrfAdapter;
use App\Infrastructure\Security\RateLimiterAdapter;
use App\Infrastructure\Security\SessionAuthAdapter;
use App\Infrastructure\Storage\FilesystemStorageAdapter;
use PDO;

/**
 * Service container bootstrapper binding all core infrastructure, persistence,
 * event subscribers, and use-case handlers.
 */
final class Bootstrapper
{
    private static bool $booted = false;

    public static function boot(?Container $container = null, ?PDO $pdo = null): Container
    {
        Autoloader::register();

        $c = $container ?? Container::getInstance();

        if (self::$booted && $container === null) {
            return $c;
        }

        // 1. Database & Transactions
        if ($pdo !== null) {
            $c->singleton(PDO::class, $pdo);
        } else {
            $c->singleton(PDO::class, function () {
                global $pdo;
                if ($pdo instanceof PDO) {
                    return $pdo;
                }
                return (new SqliteConnection())->getPdo();
            });
        }

        $c->singleton(TransactionManagerInterface::class, function (Container $c) {
            return new TransactionManager($c->get(PDO::class));
        });

        // 2. Repositories
        $c->singleton(ShopRepositoryInterface::class, function (Container $c) {
            return new SqliteShopRepository($c->get(PDO::class));
        });
        $c->singleton(ProductRepositoryInterface::class, function (Container $c) {
            return new SqliteProductRepository($c->get(PDO::class));
        });
        $c->singleton(OrderRepositoryInterface::class, function (Container $c) {
            return new SqliteOrderRepository($c->get(PDO::class));
        });
        $c->singleton(LedgerRepositoryInterface::class, function (Container $c) {
            return new SqliteLedgerRepository($c->get(PDO::class));
        });
        $c->singleton(UserRepositoryInterface::class, function (Container $c) {
            return new SqliteUserRepository($c->get(PDO::class));
        });
        $c->singleton(BranchRepositoryInterface::class, function (Container $c) {
            return new SqliteBranchRepository($c->get(PDO::class));
        });
        $c->singleton(SubscriptionPlanRepositoryInterface::class, function (Container $c) {
            return new SqliteSubscriptionPlanRepository($c->get(PDO::class));
        });
        $c->singleton(AddressRepositoryInterface::class, function (Container $c) {
            return new SqliteAddressRepository($c->get(PDO::class));
        });
        $c->singleton(InventoryTransactionRepositoryInterface::class, function (Container $c) {
            return new SqliteInventoryTransactionRepository($c->get(PDO::class));
        });
        $c->singleton(CartRepositoryInterface::class, function (Container $c) {
            return new SqliteCartRepository($c->get(PDO::class));
        });
        $c->singleton(CategoryRepositoryInterface::class, function (Container $c) {
            return new SqliteCategoryRepository($c->get(PDO::class));
        });
        $c->singleton(BankCardRepositoryInterface::class, function (Container $c) {
            return new SqliteBankCardRepository($c->get(PDO::class));
        });
        $c->singleton(ShippingGroupRepositoryInterface::class, function (Container $c) {
            return new SqliteShippingGroupRepository($c->get(PDO::class));
        });
        $c->singleton(UserAssignmentRepositoryInterface::class, function (Container $c) {
            return new SqliteUserAssignmentRepository($c->get(PDO::class));
        });
        $c->singleton(TicketRepositoryInterface::class, function (Container $c) {
            return new SqliteTicketRepository($c->get(PDO::class));
        });

        // 3. Domain Event Dispatcher & Subscribers
        $c->singleton(EventDispatcherInterface::class, function (Container $c) {
            $dispatcher = new DomainEventDispatcher();

            // Automatic balanced accounting subscriber
            $ledgerSubscriber = new GenerateLedgerOnPaymentApproved($c->get(LedgerRepositoryInterface::class));
            $dispatcher->subscribe(PaymentReceiptApprovedEvent::class, $ledgerSubscriber);

            // Automatic stock restoration subscriber
            $stockSubscriber = new ReleaseStockOnOrderCanceled(
                $c->get(ProductRepositoryInterface::class),
                $c->get(InventoryTransactionRepositoryInterface::class)
            );
            $dispatcher->subscribe(OrderCanceledEvent::class, fn(OrderCanceledEvent $e) => $stockSubscriber->onOrderCanceled($e));
            $dispatcher->subscribe(ReservationExpiredEvent::class, fn(ReservationExpiredEvent $e) => $stockSubscriber->onReservationExpired($e));

            return $dispatcher;
        });

        // 4. Security & Storage
        $c->singleton(SessionAuthAdapter::class, function (Container $c) {
            return new SessionAuthAdapter($c->get(UserRepositoryInterface::class));
        });
        $c->singleton(CsrfAdapter::class, fn() => new CsrfAdapter());
        $c->singleton(RateLimiterAdapter::class, function (Container $c) {
            return new RateLimiterAdapter($c->get(PDO::class));
        });
        $c->singleton(FilesystemStorageAdapter::class, fn() => new FilesystemStorageAdapter());

        self::$booted = true;
        return $c;
    }
}
