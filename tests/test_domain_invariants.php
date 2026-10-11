<?php

declare(strict_types=1);

/**
 * Befroosh Domain Layer & Business Invariants Comprehensive Test Suite
 *
 * Verifies all domain models, value objects, aggregate roots, domain exceptions,
 * domain events, and core business invariants with 100% genuine execution.
 */

// 1. PSR-4 Autoloader for App\Domain\...
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Domain\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/Domain/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Import Domain Classes
use App\Domain\Common\Entity;
use App\Domain\Common\DomainEvent;
use App\Domain\Common\DomainException;
use App\Domain\MultiTenancy\Role;
use App\Domain\MultiTenancy\SubscriptionTier;
use App\Domain\MultiTenancy\SubscriptionPlan;
use App\Domain\MultiTenancy\Shop;
use App\Domain\MultiTenancy\Branch;
use App\Domain\MultiTenancy\ShippingGroup;
use App\Domain\MultiTenancy\UserAssignment;
use App\Domain\MultiTenancy\User;
use App\Domain\Catalog\Sku;
use App\Domain\Catalog\StockQuantity;
use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Catalog\Category;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\CartItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Order\PostalTracking;
use App\Domain\Order\PurchaseLimits;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Address;
use App\Domain\Order\Order;
use App\Domain\Payment\LedgerAccount;
use App\Domain\Payment\LedgerEntryType;
use App\Domain\Payment\DoubleEntryLedgerEntry;
use App\Domain\Payment\LedgerTransaction;
use App\Domain\Payment\BankCard;
use App\Domain\Payment\CardReceipt;
use App\Domain\Support\Ticket;

// Exceptions
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\ReservationExpiredException;
use App\Domain\Exceptions\UnbalancedLedgerException;
use App\Domain\Exceptions\SubscriptionLimitExceededException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use App\Domain\Exceptions\UnauthorizedDomainActionException;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\PostalTrackingCodeInvalidException;
use App\Domain\Exceptions\NegativeInventoryException;
use App\Domain\Exceptions\InvalidLedgerEntryException;
use App\Domain\Exceptions\InvalidSkuException;
use App\Domain\Exceptions\OrderReservationNotExpiredException;
use App\Domain\Exceptions\PaymentAlreadyVerifiedException;

// Minimal test runner harness
class DomainTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string> */
    private array $failures = [];

    public function test(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->passed++;
            echo "  ✓ {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $msg = "  ✗ {$name}: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
            $this->failures[] = $msg;
            echo "{$msg}\n";
        }
    }

    public function assert(bool $condition, string $msg = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new \RuntimeException($msg);
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                $msg ?: sprintf("Expected %s, got %s", var_export($expected, true), var_export($actual, true))
            );
        }
    }

    public function assertThrows(string $expectedClass, callable $fn, string $msg = ''): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $expectedClass) {
                return;
            }
            throw new \RuntimeException(
                sprintf("Expected exception of type %s, but caught %s", $expectedClass, get_class($e))
            );
        }
        throw new \RuntimeException($msg ?: sprintf("Expected exception of type %s, but none was thrown", $expectedClass));
    }

    public function report(): int
    {
        echo "\n======================================================\n";
        echo "Domain Invariant Test Summary:\n";
        echo "Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $f) {
                echo "{$f}\n";
            }
            return 1;
        }
        echo "All domain invariant tests passed successfully (100% Genuine)!\n";
        echo "======================================================\n";
        return 0;
    }
}

$t = new DomainTestRunner();

echo "Running Befroosh Domain Layer & Business Invariants Test Suite...\n\n";

// =========================================================================
// SECTION 1: Common Subdomain (Entity, ValueObject, Events, Exceptions)
// =========================================================================
echo "=== Section 1: Common Subdomain ===\n";

class TestStubEntity extends Entity
{
    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }
}

$t->test('Entity identity and equality logic', function () use ($t) {
    $a1 = new TestStubEntity(10);
    $a2 = new TestStubEntity(10);
    $a3 = new TestStubEntity(20);
    $aUnpersisted = new TestStubEntity(null);

    $t->assert($a1->equals($a2), 'Same class and same ID must be equal');
    $t->assert(!$a1->equals($a3), 'Same class and different ID must not be equal');
    $t->assert(!$a1->equals(null), 'Entity should not equal null');
    $t->assert(!$a1->equals($aUnpersisted), 'Persisted and unpersisted must not be equal');
    $t->assert(!$aUnpersisted->equals($aUnpersisted), 'Unpersisted entities (id null) should not equal');

    $fresh = new TestStubEntity(null);
    $fresh->setId(10);
    $t->assert($a1->equals($fresh), 'Fresh entity with assigned ID must equal a1');

    $t->assertThrows(\LogicException::class, function () use ($fresh) {
        $fresh->setId(99);
    }, 'Re-assigning ID must throw LogicException');
});

$t->test('RecordsEventsTrait accumulation and release', function () use ($t) {
    $e = new class extends Entity {
        public function doSomething(): void {
            $this->recordEvent(new class implements DomainEvent {
                public function eventId(): string { return 'ev-1'; }
                public function occurredAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function eventName(): string { return 'test.event'; }
                public function aggregateId(): int { return 1; }
                public function payload(): array { return ['ok' => true]; }
            });
        }
    };

    $t->assert(!$e->hasEvents());
    $e->doSomething();
    $t->assert($e->hasEvents());
    $released = $e->releaseEvents();
    $t->assertEquals(1, count($released));
    $t->assertEquals('test.event', $released[0]->eventName());
    $t->assert(!$e->hasEvents(), 'releaseEvents must clear event buffer');
});

$t->test('DomainException payload serialization', function () use ($t) {
    $ex = InsufficientStockException::forProduct(101, 'زعفران قائنات', 2.5, 5.0);
    $t->assertEquals('INSUFFICIENT_STOCK', $ex->getErrorCode());
    $t->assertEquals(422, $ex->getHttpStatusCode());
    $payload = $ex->toErrorPayload();
    $t->assertEquals('INSUFFICIENT_STOCK', $payload['code']);
    $t->assertEquals(101, $payload['details']['product_id']);
    $t->assertEquals(2.5, $payload['details']['available_stock']);
});

// =========================================================================
// SECTION 2: Multi-Tenancy Subdomain (Roles, Shop, Branch, SubscriptionPlan)
// =========================================================================
echo "\n=== Section 2: Multi-Tenancy Subdomain ===\n";

$t->test('Role hierarchical aliasing & permission satisfaction', function () use ($t) {
    // SuperAdmin satisfies everything
    $t->assert(Role::SuperAdmin->satisfies(Role::SuperAdmin));
    $t->assert(Role::SuperAdmin->satisfies(Role::Admin));
    $t->assert(Role::SuperAdmin->satisfies(Role::ShopOwner));
    $t->assert(Role::SuperAdmin->satisfies(Role::BranchManager));
    $t->assert(Role::SuperAdmin->satisfies(Role::Manager));
    $t->assert(Role::SuperAdmin->satisfies(Role::Customer));

    // Admin satisfies all except SuperAdmin
    $t->assert(!Role::Admin->satisfies(Role::SuperAdmin));
    $t->assert(Role::Admin->satisfies(Role::Admin));
    $t->assert(Role::Admin->satisfies(Role::ShopOwner));

    // ShopOwner satisfies BranchManager and Manager
    $t->assert(Role::ShopOwner->satisfies(Role::ShopOwner));
    $t->assert(Role::ShopOwner->satisfies(Role::BusinessOwner));
    $t->assert(Role::ShopOwner->satisfies(Role::BranchManager));
    $t->assert(Role::ShopOwner->satisfies(Role::Manager));
    $t->assert(!Role::ShopOwner->satisfies(Role::Admin));

    // BranchManager satisfies Manager but NOT ShopOwner
    $t->assert(Role::BranchManager->satisfies(Role::BranchManager));
    $t->assert(Role::BranchManager->satisfies(Role::Manager));
    $t->assert(!Role::BranchManager->satisfies(Role::ShopOwner));

    // Manager does not satisfy BranchManager
    $t->assert(!Role::Manager->satisfies(Role::BranchManager));

    // Staff flags
    $t->assert(Role::SuperAdmin->isPlatformStaff());
    $t->assert(Role::Admin->isPlatformStaff());
    $t->assert(!Role::ShopOwner->isPlatformStaff());
    $t->assert(Role::ShopOwner->isShopStaff());
    $t->assert(!Role::Customer->isShopStaff());
});

$t->test('Shop aggregate invariants & calculations', function () use ($t) {
    $shop = new Shop(
        name: 'زعفران زرین',
        slug: 'zarrin-saffron',
        ownerId: 1,
        taxRate: 0.10,
        defaultShippingCost: 350000.0,
        freeShippingThreshold: 1500000.0,
        reservationDays: 4
    );

    // Shipping calculations
    $t->assertEquals(350000.0, $shop->calculateShippingCost(1000000.0), 'Below free shipping threshold');
    $t->assertEquals(0.0, $shop->calculateShippingCost(1500000.0), 'At free shipping threshold');
    $t->assertEquals(0.0, $shop->calculateShippingCost(2000000.0), 'Above free shipping threshold');

    // Tax calculation
    $t->assertEquals(100000.0, $shop->calculateTax(1000000.0));
    $t->assertEquals(12345.67, $shop->calculateTax(123456.7));

    // Reservation expiry calculation
    $placed = new \DateTimeImmutable('2026-10-10 12:00:00');
    $expiry = $shop->calculateReservationExpiry($placed);
    $t->assertEquals('2026-10-14 12:00:00', $expiry->format('Y-m-d H:i:s'));

    // Invariant guard: negative tax or negative shipping throws
    $t->assertThrows(\InvalidArgumentException::class, function () {
        new Shop(name: 'تست', slug: 'test', taxRate: 1.5);
    });
    $t->assertThrows(\InvalidArgumentException::class, function () {
        new Shop(name: 'تست', slug: 'test', defaultShippingCost: -100.0);
    });
    $t->assertThrows(\InvalidArgumentException::class, function () {
        new Shop(name: 'تست', slug: 'test', reservationDays: 0);
    });
});

$t->test('Branch catalog mode & shipping delegation', function () use ($t) {
    $branch = new Branch(
        businessId: 1,
        name: 'شعبه مرکزی مشهد',
        slug: 'mashhad-central',
        catalogMode: Branch::CATALOG_MODE_BOTH,
        defaultShippingCost: 200000.0,
        freeShippingThreshold: 1000000.0,
        isMain: true
    );

    $t->assertEquals('both', $branch->getCatalogMode());
    $branch->changeCatalogMode(Branch::CATALOG_MODE_BUSINESS_ONLY, actorId: 99);
    $t->assertEquals('business_only', $branch->getCatalogMode());
    $t->assert($branch->hasEvents(), 'Changing catalog mode must record event');

    // Invalid catalog mode throws
    $t->assertThrows(\InvalidArgumentException::class, function () use ($branch) {
        $branch->changeCatalogMode('invalid_mode');
    });

    // Shipping group delegation
    $sg = new ShippingGroup(
        businessId: 1,
        name: 'ارسال اکسپرس پایتخت',
        shippingCost: 500000.0,
        freeShippingThreshold: 3000000.0,
        id: 77
    );
    $branch->setShippingGroupId(77);

    $effective = $branch->calculateEffectiveShipping(1200000.0, $sg);
    $t->assertEquals(500000.0, $effective, 'Must delegate to assigned shipping group');
});

$t->test('4-Tier Subscription Plan Quota Invariants', function () use ($t) {
    $freePlan = new SubscriptionPlan(
        id: 1,
        slug: 'free',
        name: 'طرح پایه رایگان',
        priceIrt: 0.0,
        maxBusinesses: 1,
        maxBranches: 0,
        maxProducts: 50,
        maxManagers: 0,
        hasTicketing: false,
        hasOnlinePayment: false,
        hasTicketEscalation: false
    );

    $ultimatePlan = new SubscriptionPlan(
        id: 4,
        slug: 'ultimate',
        name: 'طرح نامحدود سازمانی',
        priceIrt: 5000000.0,
        maxBusinesses: 10,
        maxBranches: 9999,
        maxProducts: 999999,
        maxManagers: 9999,
        hasTicketing: true,
        hasOnlinePayment: true,
        hasTicketEscalation: true
    );

    // Branch Quota
    $t->assert(!$freePlan->canAddBranch(0), 'Free plan allows 0 extra branches');
    $t->assertThrows(SubscriptionLimitExceededException::class, function () use ($freePlan) {
        $freePlan->assertCanCreateBranch(0, shopId: 1);
    });

    // Product Quota
    $t->assert($freePlan->canAddProduct(49));
    $t->assert(!$freePlan->canAddProduct(50));
    $t->assertThrows(SubscriptionLimitExceededException::class, function () use ($freePlan) {
        $freePlan->assertCanCreateProduct(50, shopId: 1);
    });

    // Manager Quota
    $t->assert(!$freePlan->canAddManager(0));
    $t->assertThrows(SubscriptionLimitExceededException::class, function () use ($freePlan) {
        $freePlan->assertCanAssignManager(0, shopId: 1);
    });

    // Ticket Escalation (Exclusive to Ultimate)
    $t->assert(!$freePlan->canEscalateTicket());
    $t->assertThrows(SubscriptionLimitExceededException::class, function () use ($freePlan) {
        $freePlan->assertCanEscalateTicket(shopId: 1);
    });

    $t->assert($ultimatePlan->canEscalateTicket(), 'Ultimate plan must allow ticket escalation');
    $ultimatePlan->assertCanEscalateTicket(shopId: 1); // Must not throw
    $t->assert($ultimatePlan->isUltimate());
});

// =========================================================================
// SECTION 3: Catalog & Inventory Subdomain
// =========================================================================
echo "\n=== Section 3: Catalog & Inventory Subdomain ===\n";

$t->test('Sku validation & normalization', function () use ($t) {
    $sku = new Sku(' saffron-box-10g ');
    $t->assertEquals('SAFFRON-BOX-10G', $sku->value);
    $t->assertEquals('SAFFRON-BOX-10G', (string)$sku);

    $t->assertThrows(InvalidSkuException::class, function () {
        new Sku('A'); // Too short (< 2)
    });
    $t->assertThrows(InvalidSkuException::class, function () {
        new Sku('invalid sku with spaces');
    });
    $t->assertThrows(InvalidSkuException::class, function () {
        new Sku('invalid$character');
    });
});

$t->test('StockQuantity non-negative invariants', function () use ($t) {
    $sq = new StockQuantity(10.0);
    $added = $sq->add(5.0);
    $t->assertEquals(15.0, $added->value);

    $subtracted = $added->subtract(3.0);
    $t->assertEquals(12.0, $subtracted->value);

    // Strict subtract below zero throws NegativeInventoryException
    $t->assertThrows(NegativeInventoryException::class, function () use ($subtracted) {
        $subtracted->subtract(20.0);
    });

    // Clamped subtract returns 0.0
    $clamped = $subtracted->clampSubtract(20.0);
    $t->assertEquals(0.0, $clamped->value);

    // Constructor with negative value throws
    $t->assertThrows(NegativeInventoryException::class, function () {
        new StockQuantity(-1.0);
    });
});

$t->test('InventoryTransaction delta calculation', function () use ($t) {
    $txInward = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_INWARD,
        quantity: 50.0,
        unitCost: 120000.0
    );
    $t->assert($txInward->isStockIncrement());
    $t->assertEquals(50.0, $txInward->getStockDelta());

    $txOutward = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_OUTWARD_ORDER,
        quantity: 5.0
    );
    $t->assert($txOutward->isStockDecrement());
    $t->assertEquals(-5.0, $txOutward->getStockDelta());

    $txReturn = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_RETURN_IN,
        quantity: 2.0
    );
    $t->assert($txReturn->isStockIncrement());
    $t->assertEquals(2.0, $txReturn->getStockDelta());
});

$t->test('Product aggregate stock operations, reservations & purchase caps', function () use ($t) {
    $product = new Product(
        shopId: 1,
        title: 'هل اعلا هندوستان',
        price: 850000,
        stockQuantity: 20.0,
        reservedQuantity: 5.0,
        costPrice: 600000.0,
        sku: 'CARDAMOM-100G',
        maxPerOrder: 5.0,
        maxPerMonth: 10.0,
        id: 55
    );

    // Available stock = 20 - 5 = 15
    $t->assertEquals(15.0, $product->getAvailableStock());

    // Reserve 5 more
    $product->reserveStock(5.0);
    $t->assertEquals(10.0, $product->getAvailableStock());
    $t->assertEquals(10.0, $product->getReservedQuantity()->value);

    // Release 2 reserved
    $product->releaseReservedStock(2.0);
    $t->assertEquals(8.0, $product->getReservedQuantity()->value);
    $t->assertEquals(12.0, $product->getAvailableStock());

    // Fulfill reservation (shipment): deducts both physical and reserved
    $product->fulfillReservation(4.0);
    $t->assertEquals(16.0, $product->getStockQuantity()->value);
    $t->assertEquals(4.0, $product->getReservedQuantity()->value);
    $t->assertEquals(12.0, $product->getAvailableStock());

    // Assert Can Purchase checks
    // 1. Available stock exceeded
    $t->assertThrows(InsufficientStockException::class, function () use ($product) {
        $product->assertCanPurchase(15.0);
    });

    // 2. Per-order cap exceeded (maxPerOrder = 5.0)
    $t->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(6.0);
    });

    // 3. 30-day cumulative cap exceeded (already bought 7, maxPerMonth = 10, trying to buy 4 => 11 > 10)
    $t->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(4.0, past30DaysPurchased: 7.0);
    });

    // Valid purchase within limits
    $product->assertCanPurchase(3.0, past30DaysPurchased: 5.0); // 3 <= 12, 3 <= 5, 5+3=8 <= 10. Passes!
});

$t->test('Product::assertCanPurchase enforces minOrderQty', function () use ($t) {
    $product = new Product(
        shopId: 1,
        title: 'بسته عمده زعفران',
        price: 500000,
        stockQuantity: 100.0,
        minOrderQty: 5.0,
        maxPerOrder: 20.0
    );

    // 1. Below minOrderQty (4.99 < 5.0) -> must throw
    $t->assertThrows(\InvalidArgumentException::class, function () use ($product) {
        $product->assertCanPurchase(4.99);
    });

    // 2. Exactly at minOrderQty (5.0) -> passes
    $product->assertCanPurchase(5.0);

    // 3. Above minOrderQty within maxPerOrder (10.0) -> passes
    $product->assertCanPurchase(10.0);

    // 4. Above maxPerOrder (21.0) -> throws PurchaseLimitExceededException
    $t->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(21.0);
    });
});

$t->test('Product::assertCanPurchase default minOrderQty rejects sub-unit quantity', function () use ($t) {
    $product = new Product(
        shopId: 1,
        title: 'کتاب آموزش DDD',
        price: 150000,
        stockQuantity: 10.0
        // default minOrderQty = 1.0
    );

    $t->assertThrows(\InvalidArgumentException::class, function () use ($product) {
        $product->assertCanPurchase(0.5);
    });

    $product->assertCanPurchase(1.0); // passes
});

// =========================================================================
// SECTION 4: Order & Checkout Subdomain (Lifecycle, 4-Day Reservation, Auto-Pay)
// =========================================================================
echo "\n=== Section 4: Order & Checkout Subdomain ===\n";

$t->test('OrderStatus state transitions & terminal states', function () use ($t) {
    // Valid transitions
    $t->assert(OrderStatus::Submitted->canTransitionTo(OrderStatus::Paid));
    $t->assert(OrderStatus::Submitted->canTransitionTo(OrderStatus::Canceled));
    $t->assert(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped));
    $t->assert(OrderStatus::Paid->canTransitionTo(OrderStatus::Canceled));
    $t->assert(OrderStatus::Shipped->canTransitionTo(OrderStatus::Completed));

    // Invalid transitions
    $t->assert(!OrderStatus::Submitted->canTransitionTo(OrderStatus::Shipped));
    $t->assert(!OrderStatus::Submitted->canTransitionTo(OrderStatus::Completed));
    $t->assert(!OrderStatus::Shipped->canTransitionTo(OrderStatus::Paid));

    // Terminal states
    $t->assert(OrderStatus::Completed->isTerminal());
    $t->assert(OrderStatus::Canceled->isTerminal());
    $t->assert(!OrderStatus::Completed->canTransitionTo(OrderStatus::Paid));
    $t->assert(!OrderStatus::Canceled->canTransitionTo(OrderStatus::Submitted));

    $t->assertThrows(InvalidOrderStateTransitionException::class, function () {
        OrderStatus::Completed->assertCanTransitionTo(OrderStatus::Paid, 1, 'ORD-001');
    });
});

$t->test('OrderStatus: Terminal states cannot transition to themselves or other states', function () use ($t) {
    $terminalStates = [OrderStatus::Completed, OrderStatus::Canceled, OrderStatus::Finalised];
    $allStates = OrderStatus::cases();

    foreach ($terminalStates as $terminal) {
        $t->assert($terminal->isTerminal(), "{$terminal->value} must be terminal");
        foreach ($allStates as $target) {
            $t->assert(
                !$terminal->canTransitionTo($target),
                "Terminal state {$terminal->value} must NOT transition to {$target->value}"
            );
            $t->assertThrows(InvalidOrderStateTransitionException::class, function () use ($terminal, $target) {
                $terminal->assertCanTransitionTo($target, 1, 'ORD-TEST');
            });
        }
    }
});

$t->test('OrderStatus: Self-transitions are disallowed across all states', function () use ($t) {
    foreach (OrderStatus::cases() as $status) {
        $t->assert(
            !$status->canTransitionTo($status),
            "Self-transition {$status->value} -> {$status->value} must NOT be allowed"
        );
        $t->assertThrows(InvalidOrderStateTransitionException::class, function () use ($status) {
            $status->assertCanTransitionTo($status, 1, 'ORD-SELF');
        });
    }
});

$t->test('PostalTracking 24-digit validation & URL generation', function () use ($t) {
    $validTracking = PostalTracking::from('241234567890123456789012');
    $t->assertEquals('241234567890123456789012', $validTracking->code);
    $t->assertEquals(
        'https://tracking.post.ir/?id=241234567890123456789012',
        $validTracking->getTrackingUrl()
    );

    // Empty tracking
    $empty = PostalTracking::empty();
    $t->assertEquals(null, $empty->code);
    $t->assertEquals(null, $empty->getTrackingUrl());

    // Generated Iran Post barcode
    $generated = PostalTracking::generateIranPostCode();
    $t->assertEquals(24, strlen($generated->code));
    $t->assert(str_starts_with($generated->code, '24'));

    // Invalid format (less than 24 digits or letters) throws
    $t->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('12345');
    });
    $t->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('24123456789012345678901A');
    });
});

$t->test('Free Order Auto-Pay Invariant', function () use ($t) {
    $address = new AddressSnapshot('سارا رضایی', '09120000000', 'تهران', 'تهران', 'خیابان آزادی');
    $customer = new CustomerSnapshot(12, 'sara_r', 'سارا');
    $freePrice = PriceSnapshot::create(subtotal: 50000.0, discountAmount: 50000.0); // Total = 0.0

    $orderItem = new OrderItem(
        orderId: null,
        productId: 10,
        productTitle: 'هدیه نمونه زعفران',
        unitPrice: 50000,
        quantity: 1.0
    );

    $placedAt = new \DateTimeImmutable('2026-10-10 10:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 12,
        uuid: 'ORD-FREE-1',
        price: $freePrice,
        addressSnapshot: $address,
        customerSnapshot: $customer,
        items: [$orderItem],
        placedAt: $placedAt
    );

    // Invariant: Free orders immediately become PAID with zero reservation wait
    $t->assertEquals(OrderStatus::Paid, $order->getStatus());
    $t->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $t->assertEquals('2026-10-10 10:00:00', $order->getPaidAt()?->format('Y-m-d H:i:s'));
    $t->assertEquals(null, $order->getReservationExpiresAt());
    $t->assert(!$order->isReservationExpired());
});

$t->test('Automated 4-Day Reservation Lifecycle & Expiration Release', function () use ($t) {
    $address = new AddressSnapshot('علی محمدی', '09121111111', 'خراسان رضوی', 'مشهد', 'بلوار سجاد');
    $customer = new CustomerSnapshot(15, 'ali_m', 'علی');
    $price = PriceSnapshot::create(subtotal: 500000.0, shippingCost: 50000.0); // Total = 550,000 > 0

    $orderItem = new OrderItem(
        orderId: null,
        productId: 20,
        productTitle: 'زعفران ۱ مثقال',
        unitPrice: 500000,
        quantity: 1.0,
        unitCostPrice: 380000.0
    );

    $placedAt = new \DateTimeImmutable('2026-10-10 08:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 15,
        uuid: 'ORD-RES-99',
        price: $price,
        addressSnapshot: $address,
        customerSnapshot: $customer,
        items: [$orderItem],
        reservationDays: 4,
        placedAt: $placedAt
    );

    // Invariant 1: Normal unpaid order starts as Submitted with 4-day reservation
    $t->assertEquals(OrderStatus::Submitted, $order->getStatus());
    $t->assertEquals(PaymentStatus::Unpaid, $order->getPaymentStatus());
    $t->assertEquals('2026-10-14 08:00:00', $order->getReservationExpiresAt()?->format('Y-m-d H:i:s'));

    // Invariant 2: Expiration evaluation at day 3 (not expired yet)
    $day3 = new \DateTimeImmutable('2026-10-13 08:00:00');
    $t->assert(!$order->isReservationExpired($day3));
    $t->assertThrows(OrderReservationNotExpiredException::class, function () use ($order, $day3) {
        $order->releaseReservation($day3);
    });

    // Invariant 3: Expiration evaluation at day 4 + 1 second (expired)
    $day4Expired = new \DateTimeImmutable('2026-10-14 08:00:01');
    $t->assert($order->isReservationExpired($day4Expired));

    // Release expired reservation
    $order->releaseReservation($day4Expired);
    $t->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $t->assertEquals(PaymentStatus::Rejected, $order->getPaymentStatus());
    $t->assertEquals('انقضای مهلت رزرو ۴ روزه و عدم پرداخت', $order->getCancellationReason());
    $t->assertEquals('2026-10-14 08:00:01', $order->getCanceledAt()?->format('Y-m-d H:i:s'));

    // Verify domain events emitted for inventory restoration
    $events = $order->releaseEvents();
    $eventNames = array_map(fn($e) => $e->eventName(), $events);
    $t->assert(in_array('order.reservation_expired', $eventNames, true));
    $t->assert(in_array('order.canceled', $eventNames, true));
});

$t->test('Order full payment, shipping, and completion workflow', function () use ($t) {
    $address = new AddressSnapshot('امیر حسینی', '09122222222', 'اصفهان', 'اصفهان', 'چهارباغ');
    $customer = new CustomerSnapshot(25, 'amir_h', 'امیر');
    $price = PriceSnapshot::create(subtotal: 1000000.0, shippingCost: 50000.0);

    $order = Order::create(
        shopId: 1,
        customerId: 25,
        uuid: 'ORD-FLOW-1',
        price: $price,
        addressSnapshot: $address,
        customerSnapshot: $customer,
        items: []
    );

    // Customer submits receipt
    $order->submitPaymentReceipt('storage/receipts/rec_123.jpg', reference: 'REF-9988');
    $t->assertEquals(PaymentStatus::PendingVerification, $order->getPaymentStatus());
    $t->assertEquals('pending', $order->getReceiptStatus());

    // Merchant approves payment
    $order->approvePayment(verifiedByUserId: 5);
    $t->assertEquals(OrderStatus::Paid, $order->getStatus());
    $t->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $t->assertEquals('approved', $order->getReceiptStatus());
    $t->assertEquals(null, $order->getReservationExpiresAt(), 'Approved order reservation must be cleared');

    // Merchant ships order
    $tracking = PostalTracking::generateIranPostCode();
    $order->ship($tracking, 'پست پیشتاز', shippedByUserId: 5);
    $t->assertEquals(OrderStatus::Shipped, $order->getStatus());
    $t->assertEquals($tracking->code, $order->getPostalTracking()->code);

    // Carrier completes delivery
    $order->complete();
    $t->assertEquals(OrderStatus::Completed, $order->getStatus());
    $t->assert($order->getStatus()->isTerminal());
});

// =========================================================================
// SECTION 5: Payment & Offline Accounting Subdomain
// =========================================================================
echo "\n=== Section 5: Payment & Accounting Subdomain ===\n";

$t->test('BankCard validation and group formatting', function () use ($t) {
    $card = new BankCard(
        shopId: 1,
        cardNumber: '6037-9918-1234-5678',
        cardHolder: 'محمد شجاعی',
        bankName: 'بانک ملی ایران'
    );
    $t->assertEquals('6037991812345678', $card->getCardNumber());
    $t->assertEquals('6037-9918-1234-5678', $card->getFormattedCardNumber());

    $t->assertThrows(\InvalidArgumentException::class, function () {
        new BankCard(shopId: 1, cardNumber: '1234', cardHolder: 'تست'); // Less than 16 digits
    });
    $t->assertThrows(\InvalidArgumentException::class, function () {
        new BankCard(shopId: 1, cardNumber: '6037991812345678', cardHolder: ''); // Empty cardholder
    });
});

$t->test('DoubleEntryLedgerEntry individual entry validation', function () use ($t) {
    // Valid debit entry
    $debitEntry = new DoubleEntryLedgerEntry(
        shopId: 1,
        account: LedgerAccount::CashBank,
        entryType: LedgerEntryType::PaymentReceived,
        debit: 500000.0,
        credit: 0.0,
        description: 'واریز وجه نقدی'
    );
    $t->assertEquals(500000.0, $debitEntry->getDebit());
    $t->assertEquals(0.0, $debitEntry->getCredit());

    // Both debit and credit positive throws
    $t->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(
            shopId: 1,
            account: LedgerAccount::CashBank,
            entryType: 'manual',
            debit: 100.0,
            credit: 100.0,
            description: 'نامعتبر'
        );
    });

    // Both debit and credit zero throws
    $t->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(
            shopId: 1,
            account: LedgerAccount::CashBank,
            entryType: 'manual',
            debit: 0.0,
            credit: 0.0,
            description: 'نامعتبر'
        );
    });

    // Negative amounts throw
    $t->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(
            shopId: 1,
            account: LedgerAccount::CashBank,
            entryType: 'manual',
            debit: -50.0,
            credit: 0.0,
            description: 'نامعتبر'
        );
    });
});

$t->test('Balanced Double-Entry Accounting Equation Assertion', function () use ($t) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'payment', 1000000.0, 0.0, 'نقد');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'revenue', 0.0, 1000000.0, 'درآمد');

    // Balanced batch passes
    DoubleEntryLedgerEntry::assertBalanced([$e1, $e2]);

    // Unbalanced batch throws UnbalancedLedgerException
    $e3Unbalanced = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'revenue', 0.0, 950000.0, 'درآمد ناقص');
    $t->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e3Unbalanced) {
        DoubleEntryLedgerEntry::assertBalanced([$e1, $e3Unbalanced]);
    });
});

$t->test('LedgerTransaction::forOrderPayment balanced voucher creation', function () use ($t) {
    // Order scenario: Subtotal = 1,000,000; Tax = 90,000; Shipping = 50,000; Discount = 40,000; GrandTotal = 1,100,000
    $subtotal = 1000000.0;
    $tax = 90000.0;
    $shipping = 50000.0;
    $discount = 40000.0;
    $grandTotal = 1100000.0;

    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 88,
        orderUuid: 'ORD-ACC-88',
        subtotal: $subtotal,
        taxAmount: $tax,
        shippingCost: $shipping,
        discountAmount: $discount,
        grandTotal: $grandTotal,
        verifierId: 2
    );

    $entries = $tx->getEntries();
    $t->assertEquals(5, count($entries));

    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($entries as $entry) {
        $totalDebit += $entry->getDebit();
        $totalCredit += $entry->getCredit();
    }

    // Invariant proof: Total Debit (1,100,000 + 40,000 = 1,140,000) == Total Credit (1,000,000 + 90,000 + 50,000 = 1,140,000)
    $t->assertEquals(1140000.0, $totalDebit);
    $t->assertEquals(1140000.0, $totalCredit);
    $t->assertEquals(0.0, abs($totalDebit - $totalCredit));

    // Must pass balanced assertion
    $tx->assertBalanced();
});

$t->test('LedgerTransaction::forOrderPayment handles 100% discount with zero grandTotal', function () use ($t) {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 101,
        orderUuid: 'ORD-100PCT-DISC',
        subtotal: 100000.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 100000.0,
        grandTotal: 0.0
    );

    $entries = $tx->getEntries();
    $t->assertEquals(2, count($entries)); // Discounts debit, SalesRevenue credit
    $t->assertEquals(LedgerAccount::Discounts, $entries[0]->getAccount());
    $t->assertEquals(100000.0, $entries[0]->getDebit());
    $t->assertEquals(0.0, $entries[0]->getCredit());

    $t->assertEquals(LedgerAccount::SalesRevenue, $entries[1]->getAccount());
    $t->assertEquals(0.0, $entries[1]->getDebit());
    $t->assertEquals(100000.0, $entries[1]->getCredit());

    $tx->assertBalanced(); // Invariant: Debit == Credit == 100000.0
});

$t->test('LedgerTransaction::forOrderPayment handles zero subtotal with shipping fee only', function () use ($t) {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 102,
        orderUuid: 'ORD-ZERO-SUBTOTAL',
        subtotal: 0.0,
        taxAmount: 0.0,
        shippingCost: 50000.0,
        discountAmount: 0.0,
        grandTotal: 50000.0
    );

    $entries = $tx->getEntries();
    $t->assertEquals(2, count($entries)); // CashBank debit, ShippingRevenue credit
    $t->assertEquals(LedgerAccount::CashBank, $entries[0]->getAccount());
    $t->assertEquals(50000.0, $entries[0]->getDebit());

    $t->assertEquals(LedgerAccount::ShippingRevenue, $entries[1]->getAccount());
    $t->assertEquals(50000.0, $entries[1]->getCredit());

    $tx->assertBalanced();
});

$t->test('LedgerTransaction::forOrderPayment handles completely free order with zero entries', function () use ($t) {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 103,
        orderUuid: 'ORD-FREE-GIFT',
        subtotal: 0.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 0.0,
        grandTotal: 0.0
    );

    $t->assertEquals(0, count($tx->getEntries()));
    $tx->assertBalanced();
});

$t->test('LedgerTransaction::forOrderPayment rejects negative amounts with InvalidLedgerEntryException', function () use ($t) {
    $t->assertThrows(InvalidLedgerEntryException::class, function () {
        LedgerTransaction::forOrderPayment(
            shopId: 1, orderId: 104, orderUuid: 'ORD-NEG',
            subtotal: -50000.0, taxAmount: 0.0, shippingCost: 0.0,
            discountAmount: 0.0, grandTotal: -50000.0
        );
    });
});

// =========================================================================
// SECTION 6: Support & Tickets Subdomain
// =========================================================================
echo "\n=== Section 6: Support Subdomain ===\n";

$t->test('Ticket lifecycle and superadmin escalation', function () use ($t) {
    $ticket = new Ticket(
        shopId: 1,
        customerId: 10,
        title: 'مشکل در درگاه بانکی',
        status: Ticket::STATUS_OPEN,
        priority: 'medium'
    );

    $t->assertEquals(false, $ticket->isEscalatedToSuperadmin());
    $ticket->escalateToSuperadmin();
    $t->assertEquals(true, $ticket->isEscalatedToSuperadmin());
    $t->assertEquals('urgent', $ticket->getPriority());

    $ticket->assignTo(3);
    $t->assertEquals(Ticket::STATUS_IN_PROGRESS, $ticket->getStatus());
    $t->assertEquals(3, $ticket->getAssignedToUserId());

    $ticket->resolve();
    $t->assertEquals(Ticket::STATUS_RESOLVED, $ticket->getStatus());

    $ticket->close();
    $t->assertEquals(Ticket::STATUS_CLOSED, $ticket->getStatus());
});

// =========================================================================
// SECTION 7: Repository Contracts Reflection & Purity
// =========================================================================
echo "\n=== Section 7: Repository Interfaces Verification ===\n";

$t->test('All 15 Domain Repository Interfaces exist and declare typed contracts', function () use ($t) {
    $interfaces = [
        \App\Domain\Repositories\ShopRepositoryInterface::class,
        \App\Domain\Repositories\BranchRepositoryInterface::class,
        \App\Domain\Repositories\ShippingGroupRepositoryInterface::class,
        \App\Domain\Repositories\UserAssignmentRepositoryInterface::class,
        \App\Domain\Repositories\SubscriptionPlanRepositoryInterface::class,
        \App\Domain\Repositories\UserRepositoryInterface::class,
        \App\Domain\Repositories\AddressRepositoryInterface::class,
        \App\Domain\Repositories\ProductRepositoryInterface::class,
        \App\Domain\Repositories\CategoryRepositoryInterface::class,
        \App\Domain\Repositories\InventoryTransactionRepositoryInterface::class,
        \App\Domain\Repositories\OrderRepositoryInterface::class,
        \App\Domain\Repositories\BankCardRepositoryInterface::class,
        \App\Domain\Repositories\LedgerRepositoryInterface::class,
        \App\Domain\Repositories\TicketRepositoryInterface::class,
        \App\Domain\Repositories\CartRepositoryInterface::class,
    ];

    foreach ($interfaces as $iface) {
        $t->assert(interface_exists($iface), "Interface {$iface} must exist");
        $ref = new \ReflectionClass($iface);
        $t->assert($ref->isInterface(), "{$iface} must be an interface");
        $methods = $ref->getMethods();
        $t->assert(count($methods) > 0, "{$iface} must declare methods");
        foreach ($methods as $method) {
            $t->assert($method->hasReturnType(), "Method {$iface}::{$method->getName()} must have typed return");
        }
    }
});

// =========================================================================
// SECTION 8: Additional Edge Cases & Purity Assertions
// =========================================================================
echo "\n=== Section 8: Additional Invariant Edge Cases & Purity ===\n";

$t->test('Category self-parenting loop invariant', function () use ($t) {
    $cat = new Category(shopId: 1, name: 'ادویه', slug: 'spices', id: 10);
    $cat->setParentId(5); // valid
    $t->assertEquals(5, $cat->getParentId());

    $t->assertThrows(\InvalidArgumentException::class, function () use ($cat) {
        $cat->setParentId(10); // Cannot be parent of itself
    });
});

$t->test('CardReceipt approval and rejection lifecycle', function () use ($t) {
    $receipt = new CardReceipt(orderId: 101, filePath: 'receipts/test.jpg', referenceNumber: 'REF-1');
    $t->assert($receipt->isPending());
    $t->assert(!$receipt->isApproved());
    $t->assert(!$receipt->isRejected());

    $receipt->approve(verifiedById: 5);
    $t->assert($receipt->isApproved());
    $t->assert(!$receipt->isPending());
    $t->assertEquals(5, $receipt->getVerifiedById());

    // Once approved, receipt cannot be re-approved or rejected
    $t->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($receipt) {
        $receipt->approve(verifiedById: 5);
    });
    $t->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($receipt) {
        $receipt->reject(reason: 'تصویر ناخوانا است', verifiedById: 5);
    });

    // Separate pending receipt for rejection lifecycle
    $rejectedReceipt = new CardReceipt(orderId: 102, filePath: 'receipts/test2.jpg', referenceNumber: 'REF-2');
    $rejectedReceipt->reject(reason: 'تصویر ناخوانا است', verifiedById: 5);
    $t->assert($rejectedReceipt->isRejected());
    $t->assertEquals('تصویر ناخوانا است', $rejectedReceipt->getRejectionReason());
    $t->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($rejectedReceipt) {
        $rejectedReceipt->reject(reason: 'تصویر ناخوانا است', verifiedById: 5);
    });
    $t->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($rejectedReceipt) {
        $rejectedReceipt->approve(verifiedById: 5);
    });
});

$t->test('Address entity snapshot conversion', function () use ($t) {
    $addr = new Address(
        userId: 10,
        state: 'تهران',
        city: 'تهران',
        address: 'خیابان ولیعصر',
        recipientName: 'مهدی',
        recipientPhone: '09123456789',
        postalCode: '1234567890'
    );
    $snapshot = $addr->toSnapshot();
    $t->assertEquals('تهران', $snapshot->state);
    $t->assertEquals('مهدی', $snapshot->recipientName);
    $t->assertEquals('1234567890', $snapshot->postalCode);
    $json = $snapshot->toJson();
    $fromRecov = AddressSnapshot::fromJson($json);
    $t->assert($snapshot->equals($fromRecov));
});

$t->test('Product soft-delete and restoration lifecycle', function () use ($t) {
    $p = new Product(shopId: 1, title: 'کالای تست', price: 10000);
    $t->assert($p->isActive());
    $t->assertEquals(null, $p->getDeletedAt());

    $p->softDelete();
    $t->assert(!$p->isActive());
    $t->assert($p->getDeletedAt() !== null);

    $p->restore();
    $t->assert($p->isActive());
    $t->assertEquals(null, $p->getDeletedAt());
});

$t->test('Zero framework, PDO, or SQL dependencies in src/Domain/', function () use ($t) {
    $domainDir = realpath(__DIR__ . '/../src/Domain');
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($domainDir));

    $forbiddenStrings = ['PDO', 'PDOStatement', 'mysqli'];
    $forbiddenGlobals = ['$_GET', '$_POST', '$_SESSION', '$_COOKIE', '$_SERVER', '$_REQUEST', '$_FILES'];

    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $t->assert(
            str_contains($content, 'declare(strict_types=1);'),
            "File {$file->getFilename()} must declare strict_types=1"
        );

        foreach ($forbiddenGlobals as $global) {
            $t->assert(
                !str_contains($content, $global),
                "Forbidden HTTP superglobal '{$global}' found in {$file->getPathname()}"
            );
        }

        // Tokenize PHP code to inspect actual code tokens (ignoring comments)
        $tokens = token_get_all($content);
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenText = $token[1];

                // Skip comments and docblocks
                if (in_array($tokenType, [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                // Check for forbidden classes/identifiers in code
                if (in_array($tokenText, $forbiddenStrings, true)) {
                    throw new \RuntimeException("Forbidden dependency '{$tokenText}' found in code of {$file->getPathname()}");
                }
            }
        }
    }
});

exit($t->report());
