<?php

declare(strict_types=1);

/**
 * Challenger Test Harness: Milestone R1 Reservation Lifecycle & State Machine
 *
 * Empirically tests:
 * 1. Expiration at exactly T-1s, T, T+1s.
 * 2. Stock release on cancellation / expiration.
 * 3. Terminal state immutability (cannot modify completed or canceled orders).
 * 4. Free order auto-pay (grand total <= 0 immediately becomes paid).
 * 5. Purchase limits (request > stock, request > max_per_order, request + previous > max_per_month).
 * 6. Stock clamping with negative deltas.
 */

// Autoloader for App\Domain\...
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

use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\StockQuantity;
use App\Domain\Events\OrderCanceledEvent;
use App\Domain\Events\OrderCompletedEvent;
use App\Domain\Events\OrderPaidEvent;
use App\Domain\Events\OrderSubmittedEvent;
use App\Domain\Events\PaymentReceiptRejectedEvent;
use App\Domain\Events\ReservationExpiredEvent;
use App\Domain\Events\StockAdjustedEvent;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\NegativeInventoryException;
use App\Domain\Exceptions\OrderReservationNotExpiredException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Order\PostalTracking;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Order\PurchaseLimits;

class ChallengeHarness
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<array{name: string, error: string, category: string}> */
    private array $failures = [];
    /** @var array<string, array{passed: int, failed: int}> */
    private array $categoryStats = [];

    public function test(string $category, string $name, callable $fn): void
    {
        if (!isset($this->categoryStats[$category])) {
            $this->categoryStats[$category] = ['passed' => 0, 'failed' => 0];
        }

        try {
            $fn();
            $this->passed++;
            $this->categoryStats[$category]['passed']++;
            echo "  [PASS] [{$category}] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $this->categoryStats[$category]['failed']++;
            $err = $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
            $this->failures[] = [
                'name' => $name,
                'category' => $category,
                'error' => $err,
            ];
            echo "  [FAIL] [{$category}] {$name}\n";
            echo "         Error: {$err}\n";
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
                sprintf("Expected exception of type %s, but caught %s (%s)", $expectedClass, get_class($e), $e->getMessage())
            );
        }
        throw new \RuntimeException($msg ?: sprintf("Expected exception of type %s, but none was thrown", $expectedClass));
    }

    public function report(): int
    {
        echo "\n======================================================================\n";
        echo "CHALLENGE EXECUTION SUMMARY:\n";
        echo "======================================================================\n";
        foreach ($this->categoryStats as $cat => $stats) {
            $tot = $stats['passed'] + $stats['failed'];
            echo sprintf("  %-38s : %2d / %2d passed (%s)\n", $cat, $stats['passed'], $tot, $stats['failed'] === 0 ? 'CLEAN' : 'VULNERABLE');
        }
        echo "----------------------------------------------------------------------\n";
        echo "TOTAL: " . ($this->passed + $this->failed) . " | PASSED: {$this->passed} | FAILED: {$this->failed}\n";

        if ($this->failed > 0) {
            echo "\nCONFIRMED FAILURE MODES / VULNERABILITIES FOUND:\n";
            foreach ($this->failures as $i => $f) {
                echo sprintf("  %d. [%s] %s\n     -> %s\n", $i + 1, $f['category'], $f['name'], $f['error']);
            }
            echo "\nVERDICT: REQUEST_CHANGES\n";
            echo "======================================================================\n";
            return 1;
        }

        echo "\nVERDICT: APPROVE\n";
        echo "======================================================================\n";
        return 0;
    }
}

// Helpers
function makeTestOrder(
    float $subtotal = 200000.0,
    float $shipping = 20000.0,
    float $tax = 0.0,
    float $discount = 0.0,
    int $reservationDays = 4,
    ?DateTimeImmutable $placedAt = null,
    array $items = []
): Order {
    $price = PriceSnapshot::create($subtotal, $shipping, $tax, $discount);
    $addr = new AddressSnapshot('تست گیرنده', '09120000000', 'تهران', 'تهران', 'خیابان تست پلاک ۱');
    $cust = new CustomerSnapshot(10, 'customer_test', 'تست کاربر');

    if (empty($items)) {
        $items = [
            new OrderItem(null, 101, 'زعفران ۱ مثقالی', 200000, 1.0, 150000.0),
        ];
    }

    return Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-CHALLENGE-' . bin2hex(random_bytes(4)),
        price: $price,
        addressSnapshot: $addr,
        customerSnapshot: $cust,
        items: $items,
        reservationDays: $reservationDays,
        placedAt: $placedAt ?? new DateTimeImmutable('2026-10-10 12:00:00')
    );
}

$harness = new ChallengeHarness();

echo "Starting Empirical Challenge Harness for Milestone R1...\n\n";

// ============================================================================
// AREA 1: Expiration at exactly T-1s, T, T+1s
// ============================================================================
$cat1 = '1. Reservation Expiration Boundary';

$harness->test($cat1, 'Expiration at exactly T-1s must not expire and releaseReservation must throw', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 12:00:00');
    $order = makeTestOrder(placedAt: $placed, reservationDays: 4);

    $tExpected = new DateTimeImmutable('2026-10-14 12:00:00');
    $harness->assertEquals($tExpected->format('Y-m-d H:i:s'), $order->getReservationExpiresAt()?->format('Y-m-d H:i:s'));

    // Boundary: T - 1 second (2026-10-14 11:59:59)
    $tMinus1 = new DateTimeImmutable('2026-10-14 11:59:59');
    $harness->assert(!$order->isReservationExpired($tMinus1), 'At T-1s order must not be expired');
    $harness->assert(!$order->isExpired($tMinus1), 'isExpired alias must return false at T-1s');

    // Attempting to release reservation at T-1s must throw OrderReservationNotExpiredException
    $harness->assertThrows(OrderReservationNotExpiredException::class, function () use ($order, $tMinus1) {
        $order->releaseReservation($tMinus1);
    }, 'releaseReservation at T-1s must throw OrderReservationNotExpiredException');

    // Ensure status remains Submitted
    $harness->assertEquals(OrderStatus::Submitted, $order->getStatus());
    $harness->assertEquals(PaymentStatus::Unpaid, $order->getPaymentStatus());
});

$harness->test($cat1, 'Expiration at exactly T (exact second) must expire and releaseReservation succeeds', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 12:00:00');
    $order = makeTestOrder(placedAt: $placed, reservationDays: 4);

    // Boundary: T (2026-10-14 12:00:00)
    $t = new DateTimeImmutable('2026-10-14 12:00:00');
    $harness->assert($order->isReservationExpired($t), 'At T order must be considered expired');
    $harness->assert($order->isExpired($t), 'isExpired alias must return true at T');

    // Releasing reservation at exactly T must succeed
    $order->releaseReservation($t, 'انقضای مهلت ۴ روزه');
    $harness->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $harness->assertEquals(PaymentStatus::Rejected, $order->getPaymentStatus());
    $harness->assertEquals('2026-10-14 12:00:00', $order->getCanceledAt()?->format('Y-m-d H:i:s'));
    $harness->assertEquals('انقضای مهلت ۴ روزه', $order->getCancellationReason());

    // Verify events emitted
    $events = $order->releaseEvents();
    $eventClasses = array_map(fn($e) => get_class($e), $events);
    $harness->assert(in_array(ReservationExpiredEvent::class, $eventClasses, true), 'Must emit ReservationExpiredEvent');
    $harness->assert(in_array(OrderCanceledEvent::class, $eventClasses, true), 'Must emit OrderCanceledEvent');
});

$harness->test($cat1, 'Expiration at exactly T+1s must expire and releaseReservation succeeds', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 12:00:00');
    $order = makeTestOrder(placedAt: $placed, reservationDays: 4);

    // Boundary: T + 1 second (2026-10-14 12:00:01)
    $tPlus1 = new DateTimeImmutable('2026-10-14 12:00:01');
    $harness->assert($order->isReservationExpired($tPlus1), 'At T+1s order must be expired');

    // Releasing reservation at T+1s must succeed
    $order->releaseReservation($tPlus1);
    $harness->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $harness->assertEquals('2026-10-14 12:00:01', $order->getCanceledAt()?->format('Y-m-d H:i:s'));
});

$harness->test($cat1, 'Custom reservation duration (e.g. 2 days) and non-positive defaults', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 12:00:00');

    // Custom 2 days
    $order2 = makeTestOrder(placedAt: $placed, reservationDays: 2);
    $harness->assertEquals('2026-10-12 12:00:00', $order2->getReservationExpiresAt()?->format('Y-m-d H:i:s'));

    // Non-positive (0 or negative) must default to 4 days
    $order0 = makeTestOrder(placedAt: $placed, reservationDays: 0);
    $harness->assertEquals('2026-10-14 12:00:00', $order0->getReservationExpiresAt()?->format('Y-m-d H:i:s'));

    $orderNeg = makeTestOrder(placedAt: $placed, reservationDays: -5);
    $harness->assertEquals('2026-10-14 12:00:00', $orderNeg->getReservationExpiresAt()?->format('Y-m-d H:i:s'));
});

$harness->test($cat1, 'Paid orders have null reservation expiration and cannot expire', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 12:00:00');
    $order = makeTestOrder(placedAt: $placed);
    $order->approvePayment(verifiedByUserId: 1, at: new DateTimeImmutable('2026-10-11 12:00:00'));

    $harness->assertEquals(OrderStatus::Paid, $order->getStatus());
    $harness->assertEquals(null, $order->getReservationExpiresAt(), 'Approved payment clears reservation expiry');

    // Way into future
    $future = new DateTimeImmutable('2026-11-01 00:00:00');
    $harness->assert(!$order->isReservationExpired($future), 'Paid order must never expire');

    // Attempting to release reservation on Paid order must throw InvalidOrderStateTransitionException
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order, $future) {
        $order->releaseReservation($future);
    });
});

// ============================================================================
// AREA 2: Stock Release on Cancellation / Expiration
// ============================================================================
$cat2 = '2. Stock Release on Cancellation/Expiration';

$harness->test($cat2, 'Reservation expiration emits items for stock restoration on typed property', function () use ($harness) {
    $item1 = new OrderItem(null, 101, 'زعفران', 100000, 2.0, 70000.0);
    $item2 = new OrderItem(null, 102, 'هل', 50000, 3.0, 35000.0);
    $order = makeTestOrder(items: [$item1, $item2]);

    $tExpired = new DateTimeImmutable('2026-10-15 00:00:00');
    $order->releaseReservation($tExpired);

    $events = $order->releaseEvents();
    $expiredEvent = null;
    foreach ($events as $e) {
        if ($e instanceof ReservationExpiredEvent) {
            $expiredEvent = $e;
            break;
        }
    }

    $harness->assert($expiredEvent !== null, 'ReservationExpiredEvent must be recorded');
    $harness->assertEquals(2, count($expiredEvent->expiredItems));
    $harness->assertEquals(101, $expiredEvent->expiredItems[0]['product_id']);
    $harness->assertEquals(2.0, $expiredEvent->expiredItems[0]['quantity']);
    $harness->assertEquals(102, $expiredEvent->expiredItems[1]['product_id']);
    $harness->assertEquals(3.0, $expiredEvent->expiredItems[1]['quantity']);
});

$harness->test($cat2, '[CHALLENGE] ReservationExpiredEvent and OrderCanceledEvent payload() must include item details', function () use ($harness) {
    $item1 = new OrderItem(null, 101, 'زعفران', 100000, 2.0, 70000.0);
    $order = makeTestOrder(items: [$item1]);
    $order->releaseReservation(new DateTimeImmutable('2026-10-15 00:00:00'));

    $events = $order->releaseEvents();
    $expiredEvent = null;
    $cancelEvent = null;
    foreach ($events as $e) {
        if ($e instanceof ReservationExpiredEvent) $expiredEvent = $e;
        if ($e instanceof OrderCanceledEvent) $cancelEvent = $e;
    }

    $expiredPayload = $expiredEvent?->payload() ?? [];
    $cancelPayload = $cancelEvent?->payload() ?? [];

    // Challenge: For asynchronous consumers or queues serializing payload(), product IDs and quantities must be in payload()
    $hasExpiredItemsInPayload = isset($expiredPayload['expired_items']) || isset($expiredPayload['items']);
    $hasRestoredItemsInPayload = isset($cancelPayload['restored_items']) || isset($cancelPayload['items']);

    $harness->assert(
        $hasExpiredItemsInPayload && $hasRestoredItemsInPayload,
        'Domain event payload() currently omits item details array, providing only integer count items_restored'
    );
});

$harness->test($cat2, 'Product releaseReservedStock restores available stock with clamping', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'کالای انبار',
        price: 100000,
        stockQuantity: 10.0,
        reservedQuantity: 4.0,
        id: 1
    );

    $harness->assertEquals(6.0, $prod->getAvailableStock());

    // Release 4 reserved units
    $prod->releaseReservedStock(4.0);
    $harness->assertEquals(0.0, $prod->getReservedQuantity()->value);
    $harness->assertEquals(10.0, $prod->getAvailableStock(), 'Releasing reserved stock must restore full available stock');

    // Clamping: Releasing more than held reserved quantity clamps to 0.0 without going negative
    $prod->reserveStock(2.0);
    $harness->assertEquals(2.0, $prod->getReservedQuantity()->value);
    $prod->releaseReservedStock(5.0); // Release 5 when only 2 reserved
    $harness->assertEquals(0.0, $prod->getReservedQuantity()->value, 'Over-release must clamp to 0.0');
    $harness->assertEquals(10.0, $prod->getAvailableStock());
});

$harness->test($cat2, 'Manual cancellation of submitted order emits restoration items on typed property', function () use ($harness) {
    $item = new OrderItem(null, 55, 'عسل طبیعی', 300000, 1.5, 200000.0);
    $order = makeTestOrder(items: [$item]);

    $order->cancel('انصراف خریدار');
    $harness->assertEquals(OrderStatus::Canceled, $order->getStatus());

    $events = $order->releaseEvents();
    $cancelEvent = null;
    foreach ($events as $e) {
        if ($e instanceof OrderCanceledEvent) {
            $cancelEvent = $e;
            break;
        }
    }
    $harness->assert($cancelEvent !== null);
    $harness->assertEquals(1, count($cancelEvent->restoredItems));
    $harness->assertEquals(55, $cancelEvent->restoredItems[0]['product_id']);
    $harness->assertEquals(1.5, $cancelEvent->restoredItems[0]['quantity']);
});

$harness->test($cat2, 'InventoryTransaction return_in restores physical stock on paid order cancel', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'کالای ارسالی',
        price: 500000,
        stockQuantity: 10.0,
        reservedQuantity: 2.0,
        id: 88
    );

    // Fulfill reservation (deducts stock and reservation)
    $prod->fulfillReservation(2.0);
    $harness->assertEquals(8.0, $prod->getStockQuantity()->value);
    $harness->assertEquals(0.0, $prod->getReservedQuantity()->value);

    // Return in transaction
    $tx = new InventoryTransaction(
        shopId: 1,
        productId: 88,
        type: InventoryTransaction::TYPE_RETURN_IN,
        quantity: 2.0,
        notes: 'مرجوعی سفارش لغو شده'
    );
    $harness->assert($tx->isStockIncrement());
    $harness->assertEquals(2.0, $tx->getStockDelta());

    // Restore stock on product
    $prod->addStock($tx->getQuantity());
    $harness->assertEquals(10.0, $prod->getStockQuantity()->value);
    $harness->assertEquals(10.0, $prod->getAvailableStock());
});

// ============================================================================
// AREA 3: Terminal State Immutability (Completed and Canceled)
// ============================================================================
$cat3 = '3. Terminal State Immutability';

$harness->test($cat3, 'Completed order cannot transition to Submitted, Paid, Shipped, or Canceled', function () use ($harness) {
    $order = makeTestOrder();
    $order->approvePayment(1);
    $order->ship(PostalTracking::generateIranPostCode());
    $order->complete();

    $harness->assertEquals(OrderStatus::Completed, $order->getStatus());
    $harness->assert($order->getStatus()->isTerminal());

    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Submitted);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Paid);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Shipped);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Canceled);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->cancel('تلاش برای لغو سفارش تکمیل‌شده');
    });
});

$harness->test($cat3, 'Canceled order cannot transition to Submitted, Paid, Shipped, or Completed', function () use ($harness) {
    $order = makeTestOrder();
    $order->cancel('لغو اولیه');

    $harness->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $harness->assert($order->getStatus()->isTerminal());

    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Submitted);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Paid);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->transitionTo(OrderStatus::Shipped);
    });
    $harness->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->complete();
    });
});

$harness->test($cat3, '[CHALLENGE] Already Canceled order must be immutable against re-cancelation', function () use ($harness) {
    $order = makeTestOrder();
    $order->cancel('علت اول');
    $order->releaseEvents(); // clear events

    // Empirical Challenge: Calling cancel() on an already canceled order must NOT succeed or re-mutate state
    $threw = false;
    try {
        $order->cancel('علت دوم دستکاری شده');
    } catch (InvalidOrderStateTransitionException|\LogicException $e) {
        $threw = true;
    }

    $harness->assert($threw, 'Re-canceling an already canceled order must throw and be rejected');
    $harness->assertEquals('علت اول', $order->getCancellationReason(), 'Cancellation reason on terminal order must not be overwritten');
    $harness->assertEquals(0, count($order->releaseEvents()), 'Terminal order must not emit new cancellation events');
});

$harness->test($cat3, '[CHALLENGE] Already Completed order must be immutable against re-completion', function () use ($harness) {
    $order = makeTestOrder();
    $order->approvePayment(1);
    $order->ship(PostalTracking::generateIranPostCode());
    $firstCompleteTime = new DateTimeImmutable('2026-10-12 10:00:00');
    $order->complete($firstCompleteTime);
    $order->releaseEvents(); // clear events

    // Empirical Challenge: Calling complete() again on a completed order must NOT succeed or re-mutate state
    $threw = false;
    $secondCompleteTime = new DateTimeImmutable('2026-10-15 12:00:00');
    try {
        $order->complete($secondCompleteTime);
    } catch (InvalidOrderStateTransitionException|\LogicException $e) {
        $threw = true;
    }

    $harness->assert($threw, 'Re-completing an already completed order must throw and be rejected');
    $harness->assertEquals($firstCompleteTime->format('Y-m-d H:i:s'), $order->getCompletedAt()?->format('Y-m-d H:i:s'), 'Completed timestamp must not be overwritten');
    $harness->assertEquals(0, count($order->releaseEvents()), 'Completed order must not emit duplicate OrderCompletedEvent');
});

$harness->test($cat3, '[CHALLENGE] Terminal Completed order cannot have payment rejected', function () use ($harness) {
    $order = makeTestOrder();
    $order->approvePayment(1);
    $order->ship(PostalTracking::generateIranPostCode());
    $order->complete();
    $order->releaseEvents();

    // Empirical Challenge: A completed/delivered order cannot have its payment rejected!
    $threw = false;
    try {
        $order->rejectPayment('رد فیش نامعتبر پس از تحویل کالا', verifiedByUserId: 2);
    } catch (\LogicException|InvalidOrderStateTransitionException $e) {
        $threw = true;
    }

    $harness->assert($threw, 'rejectPayment on a Completed order must throw and be rejected');
    $harness->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus(), 'Payment status of completed order must not change to Rejected');
});

$harness->test($cat3, '[CHALLENGE] Terminal Canceled order cannot have payment rejected', function () use ($harness) {
    $order = makeTestOrder();
    $order->cancel('لغو توسط خریدار');
    $order->releaseEvents();

    $threw = false;
    try {
        $order->rejectPayment('تلاش برای رد پرداخت سفارش لغوشده', verifiedByUserId: 2);
    } catch (\LogicException|InvalidOrderStateTransitionException $e) {
        $threw = true;
    }

    $harness->assert($threw, 'rejectPayment on a Canceled order must throw and be rejected');
});

$harness->test($cat3, '[CHALLENGE] Line items and totals on terminal orders must be immutable', function () use ($harness) {
    $order = makeTestOrder();
    $initialTotal = $order->getPrice()->finalTotal;
    $order->cancel('لغوشده');

    // Empirical Challenge: Modifying items or recalculating price on a terminal order must be rejected
    $threw = false;
    try {
        $newItem = new OrderItem(null, 999, 'کالای اضافه پس از لغو', 500000, 2.0);
        $order->addItem($newItem);
        $order->calculateTotals();
    } catch (\LogicException|InvalidOrderStateTransitionException $e) {
        $threw = true;
    }

    $harness->assert($threw, 'Modifying items or recalculating totals on a terminal order must be rejected');
    $harness->assertEquals(1, count($order->getItems()), 'Line items count on canceled order must remain unchanged');
    $harness->assertEquals($initialTotal, $order->getPrice()->finalTotal, 'Price total on canceled order must remain unchanged');
});

// ============================================================================
// AREA 4: Free Order Auto-Pay Invariant
// ============================================================================
$cat4 = '4. Free Order Auto-Pay Invariant';

$harness->test($cat4, 'Order with subtotal 0 immediately transitions to Paid with null reservation', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 14:00:00');
    $order = makeTestOrder(subtotal: 0.0, shipping: 0.0, placedAt: $placed);

    $harness->assertEquals(OrderStatus::Paid, $order->getStatus(), 'Free order status must be Paid');
    $harness->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus(), 'Free order paymentStatus must be Paid');
    $harness->assertEquals('2026-10-10 14:00:00', $order->getPaidAt()?->format('Y-m-d H:i:s'));
    $harness->assertEquals(null, $order->getReservationExpiresAt(), 'Free order must have null reservation expiration');
    $harness->assert(!$order->isReservationExpired(), 'Free order cannot be expired');

    // Events emitted
    $events = $order->releaseEvents();
    $eventClasses = array_map(fn($e) => get_class($e), $events);
    $harness->assert(in_array(OrderSubmittedEvent::class, $eventClasses, true));
    $harness->assert(in_array(OrderPaidEvent::class, $eventClasses, true));
});

$harness->test($cat4, 'Order with 100% discount immediately transitions to Paid', function () use ($harness) {
    $order = makeTestOrder(subtotal: 150000.0, shipping: 25000.0, discount: 175000.0);
    $harness->assertEquals(0.0, $order->getPrice()->finalTotal);
    $harness->assertEquals(OrderStatus::Paid, $order->getStatus());
    $harness->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $harness->assertEquals(null, $order->getReservationExpiresAt());
});

$harness->test($cat4, 'Order with discount exceeding total (clamped to 0) transitions to Paid', function () use ($harness) {
    $order = makeTestOrder(subtotal: 100000.0, shipping: 0.0, discount: 150000.0);
    $harness->assertEquals(0.0, $order->getPrice()->finalTotal);
    $harness->assertEquals(OrderStatus::Paid, $order->getStatus());
    $harness->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
});

$harness->test($cat4, 'Order with grand total 1 IRT > 0 is NOT free and requires 4-day reservation', function () use ($harness) {
    $placed = new DateTimeImmutable('2026-10-10 14:00:00');
    $order = makeTestOrder(subtotal: 100000.0, shipping: 0.0, discount: 99999.0, placedAt: $placed); // Net total = 1.0 IRT
    $harness->assertEquals(1.0, $order->getPrice()->finalTotal);
    $harness->assertEquals(OrderStatus::Submitted, $order->getStatus());
    $harness->assertEquals(PaymentStatus::Unpaid, $order->getPaymentStatus());
    $harness->assertEquals('2026-10-14 14:00:00', $order->getReservationExpiresAt()?->format('Y-m-d H:i:s'));
});

// ============================================================================
// AREA 5: Purchase Limits
// ============================================================================
$cat5 = '5. Purchase Limits';

$harness->test($cat5, 'Request exceeding available stock throws InsufficientStockException', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'زعفران صادراتی',
        price: 900000,
        stockQuantity: 10.0,
        reservedQuantity: 3.0, // Available = 7.0
        id: 42
    );

    // Request 7.0 -> Passes
    $prod->assertCanPurchase(7.0);

    // Request 7.01 -> Throws InsufficientStockException
    $harness->assertThrows(InsufficientStockException::class, function () use ($prod) {
        $prod->assertCanPurchase(7.01);
    });

    try {
        $prod->assertCanPurchase(8.0);
    } catch (InsufficientStockException $e) {
        $payload = $e->toErrorPayload();
        $harness->assertEquals('INSUFFICIENT_STOCK', $payload['code']);
        $harness->assertEquals(42, $payload['details']['product_id']);
        $harness->assertEquals(7.0, $payload['details']['available_stock']);
    }
});

$harness->test($cat5, 'Request exceeding max_per_order throws PurchaseLimitExceededException', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'پسته رفسنجان',
        price: 600000,
        stockQuantity: 100.0,
        maxPerOrder: 5.0,
        id: 77
    );

    // Request 5.0 -> Passes
    $prod->assertCanPurchase(5.0);

    // Request 6.0 -> Throws PurchaseLimitExceededException
    $harness->assertThrows(PurchaseLimitExceededException::class, function () use ($prod) {
        $prod->assertCanPurchase(6.0);
    });

    try {
        $prod->assertCanPurchase(6.0);
    } catch (PurchaseLimitExceededException $e) {
        $payload = $e->toErrorPayload();
        $harness->assertEquals('PURCHASE_LIMIT_PER_ORDER_EXCEEDED', $payload['code']);
        $harness->assertEquals('per_order', $payload['details']['limit_type']);
        $harness->assertEquals(5.0, $payload['details']['max_allowed']);
        $harness->assertEquals(6.0, $payload['details']['requested_quantity']);
    }
});

$harness->test($cat5, 'Request + past30Days exceeding max_per_month throws PurchaseLimitExceededException', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'خاویار اصل',
        price: 3500000,
        stockQuantity: 50.0,
        maxPerOrder: 10.0,
        maxPerMonth: 10.0,
        id: 99
    );

    // Past 30 days = 7.0, request 3.0 (sum 10.0) -> Passes
    $prod->assertCanPurchase(3.0, past30DaysPurchased: 7.0);

    // Past 30 days = 7.0, request 4.0 (sum 11.0 > 10.0) -> Throws
    $harness->assertThrows(PurchaseLimitExceededException::class, function () use ($prod) {
        $prod->assertCanPurchase(4.0, past30DaysPurchased: 7.0);
    });

    try {
        $prod->assertCanPurchase(4.0, past30DaysPurchased: 7.0);
    } catch (PurchaseLimitExceededException $e) {
        $payload = $e->toErrorPayload();
        $harness->assertEquals('PURCHASE_LIMIT_PER_MONTH_EXCEEDED', $payload['code']);
        $harness->assertEquals('per_month_30days', $payload['details']['limit_type']);
        $harness->assertEquals(10.0, $payload['details']['max_allowed']);
        $harness->assertEquals(7.0, $payload['details']['already_bought']);
        $harness->assertEquals(4.0, $payload['details']['requested_quantity']);
    }
});

$harness->test($cat5, 'PurchaseLimits Value Object validation in isolation', function () use ($harness) {
    $limits = new PurchaseLimits(maxPerOrder: 5.0, maxPerMonth: 12.0, minOrderQty: 1.0);

    $harness->assert($limits->hasPerOrderLimit());
    $harness->assert($limits->hasMonthlyLimit());

    // Valid purchase
    $limits->validate(productId: 1, productTitle: 'تست', requestedQty: 3.0, availableStock: 10.0, past30DaysPurchased: 5.0);

    // Min qty violated (< 1.0)
    $harness->assertThrows(\InvalidArgumentException::class, function () use ($limits) {
        $limits->validate(1, 'تست', requestedQty: 0.5, availableStock: 10.0);
    });

    // Stock exceeded
    $harness->assertThrows(InsufficientStockException::class, function () use ($limits) {
        $limits->validate(1, 'تست', requestedQty: 12.0, availableStock: 10.0);
    });

    // Per order exceeded
    $harness->assertThrows(PurchaseLimitExceededException::class, function () use ($limits) {
        $limits->validate(1, 'تست', requestedQty: 6.0, availableStock: 20.0);
    });

    // Monthly exceeded
    $harness->assertThrows(PurchaseLimitExceededException::class, function () use ($limits) {
        $limits->validate(1, 'تست', requestedQty: 4.0, availableStock: 20.0, past30DaysPurchased: 10.0);
    });
});

// ============================================================================
// AREA 6: Stock Clamping with Negative Deltas
// ============================================================================
$cat6 = '6. Stock Clamping with Negative Deltas';

$harness->test($cat6, 'StockQuantity subtract strict vs clampSubtract safe non-negativity', function () use ($harness) {
    $sq = new StockQuantity(5.0);

    // Strict subtract exceeding stock throws NegativeInventoryException
    $harness->assertThrows(NegativeInventoryException::class, function () use ($sq) {
        $sq->subtract(6.0);
    });

    // Clamped subtract clamps to 0.0
    $clamped = $sq->clampSubtract(10.0);
    $harness->assertEquals(0.0, $clamped->value);

    // Normal subtract
    $normal = $sq->subtract(2.5);
    $harness->assertEquals(2.5, $normal->value);
});

$harness->test($cat6, 'Product::adjustStock with negative delta clamps to 0.0 and emits adjustment_minus', function () use ($harness) {
    $prod = new Product(
        shopId: 1,
        title: 'کالای تنظیمی',
        price: 100000,
        stockQuantity: 5.0,
        id: 70
    );

    // Adjust -2.0 -> stock becomes 3.0
    $prod->adjustStock(-2.0, 'تعدیل منفی ۲ عدد');
    $harness->assertEquals(3.0, $prod->getStockQuantity()->value);

    // Adjust -10.0 (exceeding remaining 3.0) -> clamps to 0.0
    $prod->adjustStock(-10.0, 'کسری شدید انبار');
    $harness->assertEquals(0.0, $prod->getStockQuantity()->value, 'Physical stock must clamp to 0.0 when negative delta exceeds stock');

    $events = $prod->releaseEvents();
    $lastEvent = end($events);
    $harness->assert($lastEvent instanceof StockAdjustedEvent);
    $harness->assertEquals('adjustment_minus', $lastEvent->adjustmentType);
    $harness->assertEquals(-10.0, $lastEvent->quantityDelta);
    $harness->assertEquals(0.0, $lastEvent->newStockQuantity);
    $harness->assertEquals('adjustment_minus', $lastEvent->payload()['type']);
    $harness->assertEquals(0.0, $lastEvent->payload()['new_stock']);
});

$harness->test($cat6, 'Product::removeStock clamp=true vs clamp=false', function () use ($harness) {
    $prodStrict = new Product(shopId: 1, title: 'کالای دقیق', price: 10000, stockQuantity: 4.0);
    $harness->assertThrows(NegativeInventoryException::class, function () use ($prodStrict) {
        $prodStrict->removeStock(5.0, clamp: false);
    });

    $prodClamp = new Product(shopId: 1, title: 'کالای منعطف', price: 10000, stockQuantity: 4.0);
    $prodClamp->removeStock(10.0, clamp: true);
    $harness->assertEquals(0.0, $prodClamp->getStockQuantity()->value, 'removeStock with clamp=true must clamp to 0.0');
});

$harness->test($cat6, 'Product::clampStock explicitly resets any negative quantity to zero', function () use ($harness) {
    $prod = new Product(shopId: 1, title: 'کالای تست', price: 10000, stockQuantity: 0.0);
    $prod->clampStock();
    $harness->assertEquals(0.0, $prod->getStockQuantity()->value);
    $harness->assertEquals(0.0, $prod->getReservedQuantity()->value);
});

$harness->test($cat6, 'InventoryTransaction signed delta for outward movements and write-offs', function () use ($harness) {
    $txMinus = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_ADJUSTMENT_MINUS,
        quantity: 8.0
    );
    $harness->assert($txMinus->isStockDecrement());
    $harness->assertEquals(-8.0, $txMinus->getStockDelta());

    $txWriteOff = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_WRITE_OFF,
        quantity: 3.5
    );
    $harness->assert($txWriteOff->isStockDecrement());
    $harness->assertEquals(-3.5, $txWriteOff->getStockDelta());

    $txOrder = new InventoryTransaction(
        shopId: 1,
        productId: 10,
        type: InventoryTransaction::TYPE_OUTWARD_ORDER,
        quantity: 12.0
    );
    $harness->assert($txOrder->isStockDecrement());
    $harness->assertEquals(-12.0, $txOrder->getStockDelta());
});

// Run and report
exit($harness->report());
