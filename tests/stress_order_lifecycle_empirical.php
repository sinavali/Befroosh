<?php

declare(strict_types=1);

/**
 * Empirical Stress Test Harness: Order Lifecycle, Payment, Reservation, Postal Tracking, and Product Boundaries
 *
 * Authored by: Empirical Challenger (challenger_r1_iter2_2)
 * Scope: Milestone R1
 */

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

use App\Domain\Catalog\Product;
use App\Domain\Catalog\StockQuantity;
use App\Domain\Events\OrderCanceledEvent;
use App\Domain\Events\OrderCompletedEvent;
use App\Domain\Events\OrderPaidEvent;
use App\Domain\Events\OrderShippedEvent;
use App\Domain\Events\OrderSubmittedEvent;
use App\Domain\Events\PaymentReceiptApprovedEvent;
use App\Domain\Events\PaymentReceiptRejectedEvent;
use App\Domain\Events\ReservationExpiredEvent;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderReservationNotExpiredException;
use App\Domain\Exceptions\PaymentAlreadyVerifiedException;
use App\Domain\Exceptions\PostalTrackingCodeInvalidException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Order\PostalTracking;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Payment\CardReceipt;

class EmpiricalStressRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string, array{passed: int, failed: int}> */
    private array $categories = [];
    /** @var array<string> */
    private array $errors = [];

    public function test(string $category, string $name, callable $fn): void
    {
        if (!isset($this->categories[$category])) {
            $this->categories[$category] = ['passed' => 0, 'failed' => 0];
        }

        try {
            $fn();
            $this->passed++;
            $this->categories[$category]['passed']++;
            echo "  [PASS] [{$category}] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $this->categories[$category]['failed']++;
            $msg = "  [FAIL] [{$category}] {$name}: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
            $this->errors[] = $msg;
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
                sprintf("Expected %s, but caught %s: %s", $expectedClass, get_class($e), $e->getMessage())
            );
        }
        throw new \RuntimeException($msg ?: sprintf("Expected %s, but no exception was thrown", $expectedClass));
    }

    public function summary(): int
    {
        echo "\n======================================================================\n";
        echo "EMPIRICAL STRESS TEST EXECUTION SUMMARY:\n";
        echo "======================================================================\n";
        foreach ($this->categories as $cat => $stats) {
            $total = $stats['passed'] + $stats['failed'];
            $status = $stats['failed'] === 0 ? 'CLEAN' : 'VULNERABLE';
            echo sprintf("  %-42s : %2d / %2d passed (%s)\n", $cat, $stats['passed'], $total, $status);
        }
        echo "----------------------------------------------------------------------\n";
        echo "TOTAL TESTS: " . ($this->passed + $this->failed) . " | PASSED: {$this->passed} | FAILED: {$this->failed}\n";

        if ($this->failed > 0) {
            echo "\nDETAILED FAILURES:\n";
            foreach ($this->errors as $err) {
                echo "{$err}\n";
            }
            echo "\nOVERALL VERDICT: ISSUES_DETECTED\n";
            echo "======================================================================\n";
            return 1;
        }

        echo "\nOVERALL VERDICT: APPROVE / CONFIRMED\n";
        echo "======================================================================\n";
        return 0;
    }
}

function makeOrder(
    float $subtotal = 100000.0,
    float $shipping = 15000.0,
    float $tax = 0.0,
    float $discount = 0.0,
    int $reservationDays = 4,
    ?DateTimeImmutable $placedAt = null,
    array $items = []
): Order {
    $price = PriceSnapshot::create($subtotal, $shipping, $tax, $discount);
    $addr = new AddressSnapshot('سینا', '09121111111', 'تهران', 'تهران', 'خیابان آزادی پلاک ۱');
    $cust = new CustomerSnapshot(10, 'sina_user', 'سینا ولی');

    if (empty($items)) {
        $items = [new OrderItem(null, 50, 'کالای زعفران', (int)$subtotal, 1.0, $subtotal * 0.7)];
    }

    return Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-EMPIRICAL-' . bin2hex(random_bytes(4)),
        price: $price,
        addressSnapshot: $addr,
        customerSnapshot: $cust,
        items: $items,
        reservationDays: $reservationDays,
        placedAt: $placedAt ?? new DateTimeImmutable('2026-10-10 12:00:00')
    );
}

$runner = new EmpiricalStressRunner();

echo "Starting Comprehensive Empirical Stress Suite for Order Lifecycle...\n\n";

// =========================================================================
// Category 1: Repeated Cancellation, Completion & Cross-Terminal Transitions
// =========================================================================
$cat1 = '1. Repeated Transitions & Cross-Terminal';

$runner->test($cat1, 'Repeated cancel() on Submitted order throws and halts duplicate events', function () use ($runner) {
    $order = makeOrder();
    $order->cancel('اولین لغو');
    $runner->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $order->releaseEvents(); // Clear recorded events

    // Second cancel MUST throw InvalidOrderStateTransitionException
    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->cancel('دومین لغو متخاصم');
    });

    $runner->assertEquals('اولین لغو', $order->getCancellationReason());
    $runner->assert(!$order->hasEvents(), 'No duplicate events recorded on repeated cancel');
});

$runner->test($cat1, 'Repeated complete() on Shipped order throws and halts duplicate events', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $order->ship('241234567890123456789012');
    $order->complete();
    $runner->assertEquals(OrderStatus::Completed, $order->getStatus());
    $order->releaseEvents();

    // Second complete MUST throw InvalidOrderStateTransitionException
    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->complete();
    });

    $runner->assert(!$order->hasEvents(), 'No duplicate events recorded on repeated complete');
});

$runner->test($cat1, 'Completed order cannot transition to Canceled or any other state', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $order->ship('241234567890123456789012');
    $order->complete();
    $order->releaseEvents();

    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->cancel('لغو سفارش تکمیل'));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Canceled));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Submitted));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Paid));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Shipped));
    $runner->assert(!$order->hasEvents(), 'No events emitted during illegal transitions');
});

$runner->test($cat1, 'Canceled order cannot transition to Completed or any other state', function () use ($runner) {
    $order = makeOrder();
    $order->cancel('لغو شده');
    $order->releaseEvents();

    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->complete());
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Completed));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Submitted));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Paid));
    $runner->assertThrows(InvalidOrderStateTransitionException::class, fn() => $order->transitionTo(OrderStatus::Shipped));
    $runner->assert(!$order->hasEvents(), 'No events emitted during illegal transitions');
});

$runner->test($cat1, 'Self-transitions are disallowed across all OrderStatus enum values', function () use ($runner) {
    $allStatuses = [
        OrderStatus::Submitted,
        OrderStatus::Paid,
        OrderStatus::Shipped,
        OrderStatus::Completed,
        OrderStatus::Canceled,
    ];

    foreach ($allStatuses as $st) {
        $runner->assert(!$st->canTransitionTo($st), "Self-transition for {$st->value} must be false");
        $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($st) {
            $st->assertCanTransitionTo($st, 1, 'ORD-SELF');
        });
    }
});

// =========================================================================
// Category 2: Payment Lifecycle & Receipt Verification
// =========================================================================
$cat2 = '2. Payment Lifecycle & Idempotency';

$runner->test($cat2, 'Repeated approvePayment on Submitted order throws PaymentAlreadyVerifiedException', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(verifiedByUserId: 5);
    $runner->assertEquals(OrderStatus::Paid, $order->getStatus());
    $runner->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $order->releaseEvents();

    // Repeated call
    $runner->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($order) {
        $order->approvePayment(verifiedByUserId: 9);
    });

    $runner->assert(!$order->hasEvents(), 'No events emitted during duplicate approval');
});

$runner->test($cat2, 'approvePayment on Free order throws PaymentAlreadyVerifiedException', function () use ($runner) {
    $freeOrder = makeOrder(subtotal: 0.0, shipping: 0.0);
    $runner->assertEquals(OrderStatus::Paid, $freeOrder->getStatus());
    $runner->assertEquals(PaymentStatus::Paid, $freeOrder->getPaymentStatus());
    $freeOrder->releaseEvents();

    $runner->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($freeOrder) {
        $freeOrder->approvePayment(1);
    });

    $runner->assert(!$freeOrder->hasEvents());
});

$runner->test($cat2, 'approvePayment on Canceled order throws InvalidOrderStateTransitionException', function () use ($runner) {
    $order = makeOrder();
    $order->cancel('لغو سفارش');
    $order->releaseEvents();

    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->approvePayment(1);
    });

    $runner->assert(!$order->hasEvents());
});

$runner->test($cat2, 'rejectPayment on Completed order throws InvalidOrderStateTransitionException', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $order->ship('241234567890123456789012');
    $order->complete();
    $order->releaseEvents();

    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->rejectPayment('رد فیش سفارش تحویل‌شده', 2);
    });

    $runner->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $runner->assert(!$order->hasEvents());
});

$runner->test($cat2, 'rejectPayment on Canceled order throws InvalidOrderStateTransitionException', function () use ($runner) {
    $order = makeOrder();
    $order->cancel('لغو سفارش');
    $order->releaseEvents();

    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->rejectPayment('رد فیش سفارش لغوشده', 2);
    });

    $runner->assert(!$order->hasEvents());
});

$runner->test($cat2, 'rejectPayment on Paid order throws PaymentAlreadyVerifiedException', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $order->releaseEvents();

    $runner->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($order) {
        $order->rejectPayment('رد فیش سفارش پرداخت شده', 2);
    });

    $runner->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());
    $runner->assert(!$order->hasEvents());
});

$runner->test($cat2, 'CardReceipt idempotency: Cannot approve or reject already finalized receipt', function () use ($runner) {
    // 1. Approved receipt
    $receipt1 = new CardReceipt(orderId: 101, filePath: 'receipt1.jpg');
    $receipt1->approve(1);
    $runner->assert($receipt1->isApproved());
    $runner->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $receipt1->approve(2));
    $runner->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $receipt1->reject('رد', 2));

    // 2. Rejected receipt
    $receipt2 = new CardReceipt(orderId: 102, filePath: 'receipt2.jpg');
    $receipt2->reject('ناخوانا', 1);
    $runner->assert($receipt2->isRejected());
    $runner->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $receipt2->reject('مجدد', 2));
    $runner->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $receipt2->approve(2));
});

// =========================================================================
// Category 3: Event Emission Deduplication & State Safety
// =========================================================================
$cat3 = '3. Event Emission Deduplication';

$runner->test($cat3, 'ReleaseEvents clears internal event buffer completely', function () use ($runner) {
    $order = makeOrder();
    $runner->assert($order->hasEvents());
    $events = $order->releaseEvents();
    $runner->assertEquals(1, count($events)); // OrderSubmittedEvent
    $runner->assert(!$order->hasEvents(), 'Buffer must be empty after releaseEvents');
    $runner->assertEquals(0, count($order->releaseEvents()), 'Subsequent releaseEvents returns empty array');
});

$runner->test($cat3, 'Normal lifecycle emits exactly expected events without duplicates', function () use ($runner) {
    $order = makeOrder();
    $eSubmitted = $order->releaseEvents();
    $runner->assertEquals(1, count($eSubmitted));
    $runner->assert($eSubmitted[0] instanceof OrderSubmittedEvent);

    $order->approvePayment(1);
    $ePaid = $order->releaseEvents();
    $runner->assertEquals(2, count($ePaid)); // PaymentReceiptApprovedEvent, OrderPaidEvent
    $runner->assert($ePaid[0] instanceof PaymentReceiptApprovedEvent);
    $runner->assert($ePaid[1] instanceof OrderPaidEvent);

    $order->ship('241234567890123456789012');
    $eShipped = $order->releaseEvents();
    $runner->assertEquals(1, count($eShipped));
    $runner->assert($eShipped[0] instanceof OrderShippedEvent);

    $order->complete();
    $eCompleted = $order->releaseEvents();
    $runner->assertEquals(1, count($eCompleted));
    $runner->assert($eCompleted[0] instanceof OrderCompletedEvent);
});

$runner->test($cat3, 'Free order creation emits exactly OrderSubmittedEvent and OrderPaidEvent', function () use ($runner) {
    $freeOrder = makeOrder(subtotal: 0.0, shipping: 0.0);
    $events = $freeOrder->releaseEvents();
    $runner->assertEquals(2, count($events));
    $runner->assert($events[0] instanceof OrderSubmittedEvent);
    $runner->assert($events[1] instanceof OrderPaidEvent);
    $runner->assertEquals(0.0, $events[1]->paidTotal);
    $runner->assertEquals('free_order', $events[1]->paymentMethod);
});

// =========================================================================
// Category 4: Product minOrderQty and maxPerOrder Boundaries
// =========================================================================
$cat4 = '4. Product minOrderQty & maxPerOrder Boundaries';

$runner->test($cat4, 'minOrderQty boundary: 0 or negative throws InvalidArgumentException', function () use ($runner) {
    $prod = new Product(shopId: 1, title: 'کالا', price: 1000, stockQuantity: 50.0, minOrderQty: 2.0);
    $runner->assertThrows(\InvalidArgumentException::class, fn() => $prod->assertCanPurchase(0.0));
    $runner->assertThrows(\InvalidArgumentException::class, fn() => $prod->assertCanPurchase(-1.5));
});

$runner->test($cat4, 'minOrderQty boundary: strictly below throws, exactly at passes', function () use ($runner) {
    $prod = new Product(shopId: 1, title: 'زعفران عمده', price: 100000, stockQuantity: 50.0, minOrderQty: 3.0);

    // 2.9999 -> throws InvalidArgumentException
    $runner->assertThrows(\InvalidArgumentException::class, fn() => $prod->assertCanPurchase(2.9999));

    // 3.0 -> passes exactly at boundary
    $prod->assertCanPurchase(3.0);

    // 3.0001 -> passes
    $prod->assertCanPurchase(3.0001);
});

$runner->test($cat4, 'maxPerOrder boundary: exactly at passes, strictly above throws PurchaseLimitExceededException', function () use ($runner) {
    $prod = new Product(shopId: 1, title: 'خاویار محدود', price: 5000000, stockQuantity: 50.0, maxPerOrder: 5.0);

    // 4.9999 -> passes
    $prod->assertCanPurchase(4.9999);

    // 5.0 -> passes exactly at boundary
    $prod->assertCanPurchase(5.0);

    // 5.0001 -> throws PurchaseLimitExceededException
    $runner->assertThrows(PurchaseLimitExceededException::class, fn() => $prod->assertCanPurchase(5.0001));
});

$runner->test($cat4, 'Combined minOrderQty and maxPerOrder tight corridor', function () use ($runner) {
    $prod = new Product(
        shopId: 1,
        title: 'بسته مخصوص',
        price: 200000,
        stockQuantity: 20.0,
        minOrderQty: 2.0,
        maxPerOrder: 4.0
    );

    // Below min
    $runner->assertThrows(\InvalidArgumentException::class, fn() => $prod->assertCanPurchase(1.99));
    // Lower bound
    $prod->assertCanPurchase(2.0);
    // Interior point
    $prod->assertCanPurchase(3.0);
    // Upper bound
    $prod->assertCanPurchase(4.0);
    // Above max
    $runner->assertThrows(PurchaseLimitExceededException::class, fn() => $prod->assertCanPurchase(4.01));
});

$runner->test($cat4, 'Equal minOrderQty and maxPerOrder allows only the exact unit quantity', function () use ($runner) {
    $prod = new Product(
        shopId: 1,
        title: 'کارتن ۶ عددی انحصاری',
        price: 600000,
        stockQuantity: 60.0,
        minOrderQty: 6.0,
        maxPerOrder: 6.0
    );

    $runner->assertThrows(\InvalidArgumentException::class, fn() => $prod->assertCanPurchase(5.99));
    $prod->assertCanPurchase(6.0); // Exactly 6.0 passes
    $runner->assertThrows(PurchaseLimitExceededException::class, fn() => $prod->assertCanPurchase(6.01));
});

$runner->test($cat4, 'Available stock takes precedence over maxPerOrder when stock is lower than limit', function () use ($runner) {
    $prod = new Product(
        shopId: 1,
        title: 'کالای کم‌موجودی',
        price: 50000,
        stockQuantity: 3.0,
        reservedQuantity: 1.0, // available = 2.0
        maxPerOrder: 10.0
    );

    $runner->assertEquals(2.0, $prod->getAvailableStock());
    $prod->assertCanPurchase(2.0); // passes
    $runner->assertThrows(InsufficientStockException::class, fn() => $prod->assertCanPurchase(2.5));
});

// =========================================================================
// Category 5: Postal Tracking & Shipping Invariants
// =========================================================================
$cat5 = '5. Postal Tracking & Shipping Invariants';

$runner->test($cat5, 'PostalTracking validates 24 numeric digits Iran Post format strictly', function () use ($runner) {
    // Valid 24 digits
    $valid = PostalTracking::from('241234567890123456789012');
    $runner->assertEquals('241234567890123456789012', $valid->code);
    $runner->assertEquals('https://tracking.post.ir/?id=241234567890123456789012', $valid->getTrackingUrl());

    // Invalid lengths
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from('123'));
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from('24123456789012345678901')); // 23
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from('2412345678901234567890123')); // 25

    // Non-numeric characters
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from('24123456789012345678901a'));
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from('24123456789012345678901-'));
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, fn() => PostalTracking::from(' 2412345678901234567890 1'));
});

$runner->test($cat5, 'Submitted order cannot be shipped directly', function () use ($runner) {
    $order = makeOrder();
    $runner->assertEquals(OrderStatus::Submitted, $order->getStatus());

    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->ship('241234567890123456789012');
    });

    $runner->assertEquals(OrderStatus::Submitted, $order->getStatus());
});

$runner->test($cat5, 'Shipping validation failure leaves order in Paid state atomically', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $runner->assertEquals(OrderStatus::Paid, $order->getStatus());

    // Attempting to ship with invalid tracking code throws PostalTrackingCodeInvalidException
    $runner->assertThrows(PostalTrackingCodeInvalidException::class, function () use ($order) {
        $order->ship('invalid_short');
    });

    // Invariant: Order state MUST remain Paid, NOT Shipped!
    $runner->assertEquals(OrderStatus::Paid, $order->getStatus());
    $runner->assertEquals(null, $order->getShippedAt());
});

$runner->test($cat5, 'Already Shipped order cannot be shipped again', function () use ($runner) {
    $order = makeOrder();
    $order->approvePayment(1);
    $order->ship('241234567890123456789012');
    $runner->assertEquals(OrderStatus::Shipped, $order->getStatus());

    $runner->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->ship('249999999999999999999999');
    });
});

// =========================================================================
// Category 6: Reservation Duration Invariants
// =========================================================================
$cat6 = '6. Reservation Duration Invariants';

$runner->test($cat6, 'Reservation calculation handles non-positive and custom durations defensively', function () use ($runner) {
    $placed = new DateTimeImmutable('2026-10-10 10:00:00');

    // Default 4 days
    $exp4 = Order::calculateReservationExpiration($placed, 4);
    $runner->assertEquals('2026-10-14 10:00:00', $exp4->format('Y-m-d H:i:s'));

    // Non-positive fallback to 4 days
    $exp0 = Order::calculateReservationExpiration($placed, 0);
    $runner->assertEquals('2026-10-14 10:00:00', $exp0->format('Y-m-d H:i:s'));

    $expNeg = Order::calculateReservationExpiration($placed, -10);
    $runner->assertEquals('2026-10-14 10:00:00', $expNeg->format('Y-m-d H:i:s'));

    // Custom positive duration (e.g. 7 days)
    $exp7 = Order::calculateReservationExpiration($placed, 7);
    $runner->assertEquals('2026-10-17 10:00:00', $exp7->format('Y-m-d H:i:s'));
});

$runner->test($cat6, 'Reservation release is strictly guarded before expiration timestamp', function () use ($runner) {
    $placed = new DateTimeImmutable('2026-10-10 10:00:00');
    $order = makeOrder(placedAt: $placed, reservationDays: 4);

    // 1 second before expiration
    $oneSecBefore = new DateTimeImmutable('2026-10-14 09:59:59');
    $runner->assert(!$order->isReservationExpired($oneSecBefore));
    $runner->assertThrows(OrderReservationNotExpiredException::class, fn() => $order->releaseReservation($oneSecBefore));

    // Exact second
    $exact = new DateTimeImmutable('2026-10-14 10:00:00');
    $runner->assert($order->isReservationExpired($exact));

    // Release at exact second succeeds
    $order->releaseReservation($exact);
    $runner->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $runner->assertEquals(PaymentStatus::Rejected, $order->getPaymentStatus());
});

exit($runner->summary());
