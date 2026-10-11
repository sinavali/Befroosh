<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Order\Order;
use App\Domain\Order\PostalTracking;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;

/**
 * Use-Case: Marks order shipped with a validated 24-digit Iran Post tracking barcode.
 */
final readonly class ShipOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(
        int $orderId,
        string $trackingCode,
        string $shippingMethod = 'پست پیشتاز',
        ?int $shippedByUserId = null
    ): Order {
        return $this->transactionManager->transactional(function () use ($orderId, $trackingCode, $shippingMethod, $shippedByUserId) {
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw EntityNotFoundException::forEntity('Order', $orderId);
            }

            $now = new DateTimeImmutable();
            $postal = PostalTracking::from($trackingCode, $shippingMethod);
            $order->ship($postal, $shippingMethod, $now, $shippedByUserId);

            $savedOrder = $this->orderRepository->save($order);

            $events = $savedOrder->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return $savedOrder;
        });
    }
}
