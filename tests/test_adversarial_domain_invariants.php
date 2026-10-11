<?php

declare(strict_types=1);

/**
 * Adversarial Stress-Testing Battery for Befroosh Domain Invariants
 *
 * Authored by: Reviewer R1-2 (Adversarial Critic)
 * Purpose: Actively stress-test boundary conditions, floating-point precision,
 *          state machine terminal transitions, edge cases, and robustness invariants.
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
use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InvalidLedgerEntryException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderReservationNotExpiredException;
use App\Domain\Exceptions\PostalTrackingCodeInvalidException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use App\Domain\Exceptions\SubscriptionLimitExceededException;
use App\Domain\Exceptions\UnbalancedLedgerException;
use App\Domain\MultiTenancy\SubscriptionPlan;
use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Order\PostalTracking;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Order\PurchaseLimits;
use App\Domain\Payment\DoubleEntryLedgerEntry;
use App\Domain\Payment\LedgerAccount;
use App\Domain\Payment\LedgerEntryType;
use App\Domain\Payment\LedgerTransaction;
use App\Domain\Payment\CardReceipt;
use App\Domain\Exceptions\PaymentAlreadyVerifiedException;

class AdversarialRunner
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
            echo "  ✓ [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $msg = "  ✗ [FAIL] {$name}: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
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
                sprintf("Expected exception %s, but caught %s (%s)", $expectedClass, get_class($e), $e->getMessage())
            );
        }
        throw new \RuntimeException($msg ?: sprintf("Expected exception %s, but none was thrown", $expectedClass));
    }

    public function summary(): int
    {
        echo "\n======================================================\n";
        echo "Adversarial Stress Test Summary:\n";
        echo "Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        if ($this->failed > 0) {
            echo "\nAdversarial Failures:\n";
            foreach ($this->failures as $f) {
                echo "{$f}\n";
            }
            return 1;
        }
        echo "All adversarial challenges survived!\n";
        echo "======================================================\n";
        return 0;
    }
}

$r = new AdversarialRunner();
echo "Starting Adversarial Stress-Testing Battery...\n\n";

// =========================================================================
// 1. 4-DAY RESERVATION LIFECYCLE: EXACT BOUNDARY CHECKS
// =========================================================================
echo "=== Focus Area 1: 4-Day Reservation Lifecycle Boundaries ===\n";

$r->test('Reservation expiration boundary: 1 second before expiration is NOT expired', function () use ($r) {
    $placed = new \DateTimeImmutable('2026-10-10 12:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-BND-1',
        price: PriceSnapshot::create(subtotal: 100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(10, 'u', 'کاربر'),
        items: [],
        reservationDays: 4,
        placedAt: $placed
    );

    // Expiration is 2026-10-14 12:00:00
    $oneSecBefore = new \DateTimeImmutable('2026-10-14 11:59:59');
    $r->assert(!$order->isReservationExpired($oneSecBefore), 'Should NOT be expired 1s before expiration');

    // Attempting release before expiration MUST throw OrderReservationNotExpiredException
    $r->assertThrows(OrderReservationNotExpiredException::class, function () use ($order, $oneSecBefore) {
        $order->releaseReservation($oneSecBefore);
    });
});

$r->test('Reservation expiration boundary: exactly on expiration second IS expired', function () use ($r) {
    $placed = new \DateTimeImmutable('2026-10-10 12:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-BND-2',
        price: PriceSnapshot::create(subtotal: 100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(10, 'u', 'کاربر'),
        items: [],
        reservationDays: 4,
        placedAt: $placed
    );

    $exactSec = new \DateTimeImmutable('2026-10-14 12:00:00');
    $r->assert($order->isReservationExpired($exactSec), 'Must be expired exactly at expiration timestamp');

    $order->releaseReservation($exactSec);
    $r->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $r->assertEquals(PaymentStatus::Rejected, $order->getPaymentStatus());
});

$r->test('Reservation release cannot be executed twice on already canceled order', function () use ($r) {
    $placed = new \DateTimeImmutable('2026-10-10 12:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-BND-3',
        price: PriceSnapshot::create(subtotal: 100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(10, 'u', 'کاربر'),
        items: [],
        reservationDays: 4,
        placedAt: $placed
    );

    $after = new \DateTimeImmutable('2026-10-15 00:00:00');
    $order->releaseReservation($after);

    // Second call MUST be blocked
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order, $after) {
        $order->releaseReservation($after);
    });
});

$r->test('Reservation release cannot be executed on already paid order', function () use ($r) {
    $placed = new \DateTimeImmutable('2026-10-10 12:00:00');
    $order = Order::create(
        shopId: 1,
        customerId: 10,
        uuid: 'ORD-BND-4',
        price: PriceSnapshot::create(subtotal: 100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(10, 'u', 'کاربر'),
        items: [],
        reservationDays: 4,
        placedAt: $placed
    );

    $order->approvePayment(1, new \DateTimeImmutable('2026-10-11 12:00:00'));

    $after = new \DateTimeImmutable('2026-10-15 00:00:00');
    $r->assert(!$order->isReservationExpired($after), 'Paid order must never be expired');
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order, $after) {
        $order->releaseReservation($after);
    });
});

$r->test('calculateReservationExpiration handles negative or zero duration defensively', function () use ($r) {
    $now = new \DateTimeImmutable('2026-10-10 00:00:00');
    $expZero = Order::calculateReservationExpiration($now, 0);
    $r->assertEquals('2026-10-14 00:00:00', $expZero->format('Y-m-d H:i:s'));

    $expNegative = Order::calculateReservationExpiration($now, -3);
    $r->assertEquals('2026-10-14 00:00:00', $expNegative->format('Y-m-d H:i:s'));
});

// =========================================================================
// 2. DOUBLE-ENTRY ACCOUNTING LEDGER: ZERO TAX/SHIPPING, EPSILON, EDGES
// =========================================================================
echo "\n=== Focus Area 2: Double-Entry Accounting Ledger Edge Cases ===\n";

$r->test('Ledger balance: 0 tax, 0 shipping, 0 discount', function () use ($r) {
    // Pure order: subtotal 500,000, grand total 500,000
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 10,
        orderUuid: 'ORD-NO-EXTRAS',
        subtotal: 500000.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 0.0,
        grandTotal: 500000.0
    );

    $entries = $tx->getEntries();
    $r->assertEquals(2, count($entries), 'Should only create CashBank debit and SalesRevenue credit');
    $tx->assertBalanced();

    $r->assertEquals(500000.0, $entries[0]->getDebit());
    $r->assertEquals(0.0, $entries[0]->getCredit());
    $r->assertEquals(LedgerAccount::CashBank, $entries[0]->getAccount());

    $r->assertEquals(0.0, $entries[1]->getDebit());
    $r->assertEquals(500000.0, $entries[1]->getCredit());
    $r->assertEquals(LedgerAccount::SalesRevenue, $entries[1]->getAccount());
});

$r->test('Ledger balance: 0 tax with shipping and discount', function () use ($r) {
    // Subtotal 1,000,000; Tax 0; Shipping 45,000; Discount 25,000; GrandTotal 1,020,000
    // Debit CashBank: 1,020,000; Debit Discounts: 25,000 => Total Debit = 1,045,000
    // Credit SalesRevenue: 1,000,000; Credit Shipping: 45,000 => Total Credit = 1,045,000
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 11,
        orderUuid: 'ORD-NO-TAX',
        subtotal: 1000000.0,
        taxAmount: 0.0,
        shippingCost: 45000.0,
        discountAmount: 25000.0,
        grandTotal: 1020000.0
    );

    $entries = $tx->getEntries();
    $r->assertEquals(4, count($entries));
    $tx->assertBalanced();
});

$r->test('Ledger balance: floating-point precision with odd decimals and epsilon', function () use ($r) {
    // Decimal precision: Subtotal 333333.33, Tax 29999.99, Shipping 15000.00, Discount 5000.00, GrandTotal 373333.32
    // Debits: 373333.32 + 5000.00 = 378333.32
    // Credits: 333333.33 + 29999.99 + 15000.00 = 378333.32
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 12,
        orderUuid: 'ORD-FLOAT',
        subtotal: 333333.33,
        taxAmount: 29999.99,
        shippingCost: 15000.00,
        discountAmount: 5000.00,
        grandTotal: 373333.32
    );

    $tx->assertBalanced();
});

$r->test('Ledger balance: unbalance detection on 0.01 discrepancy', function () use ($r) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'payment', 100.01, 0.0, 'نقد');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'revenue', 0.0, 100.00, 'درآمد');

    $r->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    });
});

$r->test('Ledger entry validation: negative debit, negative credit, and zero amounts', function () use ($r) {
    // Negative debit
    $r->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 't', -1.0, 0.0, 'desc');
    });

    // Negative credit
    $r->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 't', 0.0, -1.0, 'desc');
    });

    // Zero both
    $r->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 't', 0.0, 0.0, 'desc');
    });

    // Both positive
    $r->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 't', 10.0, 10.0, 'desc');
    });
});

$r->test('LedgerTransaction::forOrderPayment handles 100% discount with zero grandTotal', function () use ($r) {
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
    $r->assertEquals(2, count($entries)); // Discounts debit, SalesRevenue credit
    $r->assertEquals(LedgerAccount::Discounts, $entries[0]->getAccount());
    $r->assertEquals(100000.0, $entries[0]->getDebit());
    $r->assertEquals(0.0, $entries[0]->getCredit());

    $r->assertEquals(LedgerAccount::SalesRevenue, $entries[1]->getAccount());
    $r->assertEquals(0.0, $entries[1]->getDebit());
    $r->assertEquals(100000.0, $entries[1]->getCredit());

    $tx->assertBalanced(); // Invariant: Debit == Credit == 100000.0
});

$r->test('LedgerTransaction::forOrderPayment handles zero subtotal with shipping fee only', function () use ($r) {
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
    $r->assertEquals(2, count($entries)); // CashBank debit, ShippingRevenue credit
    $r->assertEquals(LedgerAccount::CashBank, $entries[0]->getAccount());
    $r->assertEquals(50000.0, $entries[0]->getDebit());

    $r->assertEquals(LedgerAccount::ShippingRevenue, $entries[1]->getAccount());
    $r->assertEquals(50000.0, $entries[1]->getCredit());

    $tx->assertBalanced();
});

$r->test('LedgerTransaction::forOrderPayment handles completely free order with zero entries', function () use ($r) {
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

    $r->assertEquals(0, count($tx->getEntries()));
    $tx->assertBalanced();
});

$r->test('LedgerTransaction::forOrderPayment rejects negative amounts with InvalidLedgerEntryException', function () use ($r) {
    $r->assertThrows(InvalidLedgerEntryException::class, function () {
        LedgerTransaction::forOrderPayment(
            shopId: 1, orderId: 104, orderUuid: 'ORD-NEG',
            subtotal: -50000.0, taxAmount: 0.0, shippingCost: 0.0,
            discountAmount: 0.0, grandTotal: -50000.0
        );
    });
});

// =========================================================================
// 3. ORDER STATE MACHINE & TERMINAL STATE PREVENTION
// =========================================================================
echo "\n=== Focus Area 3: Order State Machine Invariants ===\n";

$r->test('Illegal state transitions are prevented', function () use ($r) {
    $illegalTransitions = [
        [OrderStatus::Submitted, OrderStatus::Shipped],
        [OrderStatus::Submitted, OrderStatus::Completed],
        [OrderStatus::Paid, OrderStatus::Submitted],
        [OrderStatus::Paid, OrderStatus::Completed],
        [OrderStatus::Shipped, OrderStatus::Submitted],
        [OrderStatus::Shipped, OrderStatus::Paid],
        [OrderStatus::Shipped, OrderStatus::Canceled],
        [OrderStatus::Completed, OrderStatus::Submitted],
        [OrderStatus::Completed, OrderStatus::Paid],
        [OrderStatus::Completed, OrderStatus::Shipped],
        [OrderStatus::Completed, OrderStatus::Canceled],
        [OrderStatus::Canceled, OrderStatus::Submitted],
        [OrderStatus::Canceled, OrderStatus::Paid],
        [OrderStatus::Canceled, OrderStatus::Shipped],
        [OrderStatus::Canceled, OrderStatus::Completed],
    ];

    foreach ($illegalTransitions as [$from, $to]) {
        $r->assert(
            !$from->canTransitionTo($to),
            "State transition {$from->value} -> {$to->value} must NOT be allowed"
        );
        $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($from, $to) {
            $from->assertCanTransitionTo($to, 1, 'ORD-TEST');
        });
    }
});

$r->test('Terminal states: Completed and Canceled cannot transition to other states', function () use ($r) {
    $r->assert(OrderStatus::Completed->isTerminal());
    $r->assert(OrderStatus::Canceled->isTerminal());
    $r->assert(!OrderStatus::Paid->isTerminal());
    $r->assert(!OrderStatus::Submitted->isTerminal());
});

$r->test('Ship requires valid 24-digit postal code and rejects invalid input', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-POSTAL',
        price: PriceSnapshot::create(100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'u', 'کاربر'),
        items: []
    );
    $order->approvePayment(1);

    // Invalid codes
    $r->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('12345');
    });
    $r->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('24123456789012345678901'); // 23 digits
    });
    $r->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('2412345678901234567890123'); // 25 digits
    });
    $r->assertThrows(PostalTrackingCodeInvalidException::class, function () {
        PostalTracking::from('24123456789012345678901X'); // Contains letter
    });

    // Valid 24-digit Iran Post code
    $validCode = '241234567890123456789012';
    $order->ship($validCode);
    $r->assertEquals(OrderStatus::Shipped, $order->getStatus());
    $r->assertEquals($validCode, $order->getPostalTracking()->code);
});

$r->test('Adversarial: Repeated Order::cancel() throws InvalidOrderStateTransitionException and halts duplicate events', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-CANCEL-DUP',
        price: PriceSnapshot::create(100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'u', 'کاربر'),
        items: [new OrderItem(null, 101, 'زعفران', 100000, 2.0)]
    );

    // First cancel succeeds
    $order->cancel('اولین لغو سفارش');
    $r->assertEquals(OrderStatus::Canceled, $order->getStatus());
    $events = $order->releaseEvents();
    $r->assertEquals(2, count($events)); // OrderSubmittedEvent, OrderCanceledEvent

    // Second cancel MUST throw InvalidOrderStateTransitionException
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->cancel('دومین لغو سفارش مکرر');
    }, 'Duplicate cancellation must throw InvalidOrderStateTransitionException');

    // Verify NO new events were recorded during the failed duplicate cancellation
    $r->assert(!$order->hasEvents(), 'Failed duplicate cancellation must NOT record any duplicate domain events');
});

$r->test('Adversarial: Repeated Order::complete() throws InvalidOrderStateTransitionException and halts duplicate events', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-COMPLETE-DUP',
        price: PriceSnapshot::create(100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'u', 'کاربر'),
        items: []
    );

    $order->approvePayment(1);
    $order->ship('241234567890123456789012');
    $order->releaseEvents();

    // First complete succeeds
    $order->complete();
    $r->assertEquals(OrderStatus::Completed, $order->getStatus());
    $events = $order->releaseEvents();
    $r->assertEquals(1, count($events)); // OrderCompletedEvent

    // Second complete MUST throw InvalidOrderStateTransitionException
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order) {
        $order->complete();
    }, 'Duplicate completion must throw InvalidOrderStateTransitionException');

    $r->assert(!$order->hasEvents(), 'Failed duplicate completion must NOT record any duplicate domain events');
});

$r->test('Adversarial: Cross-terminal transitions (Completed -> Canceled, Canceled -> Completed) throw InvalidOrderStateTransitionException', function () use ($r) {
    $order1 = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-COMP-THEN-CANCEL',
        price: PriceSnapshot::create(100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'u', 'کاربر'),
        items: []
    );
    $order1->approvePayment(1);
    $order1->ship('241234567890123456789012');
    $order1->complete();

    // Completed cannot be canceled
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order1) {
        $order1->cancel('تلاش لغو پس از تحویل');
    });

    $order2 = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-CANCEL-THEN-COMP',
        price: PriceSnapshot::create(100000.0),
        addressSnapshot: new AddressSnapshot('تست', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'u', 'کاربر'),
        items: []
    );
    $order2->cancel('لغو اولیه');

    // Canceled cannot be completed
    $r->assertThrows(InvalidOrderStateTransitionException::class, function () use ($order2) {
        $order2->complete();
    });
});

$r->test('Order::approvePayment throws PaymentAlreadyVerifiedException on duplicate approval', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-IDEMP-1',
        price: PriceSnapshot::create(subtotal: 50000.0),
        addressSnapshot: new AddressSnapshot('مهدی', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'm', 'مهدی'),
        items: []
    );

    // First approval must succeed
    $order->approvePayment(5);
    $r->assertEquals(OrderStatus::Paid, $order->getStatus());
    $r->assertEquals(PaymentStatus::Paid, $order->getPaymentStatus());

    // Second approval must throw PaymentAlreadyVerifiedException
    $r->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($order) {
        $order->approvePayment(5);
    });
});

$r->test('Duplicate approvePayment does not emit redundant PaymentReceiptApprovedEvent or OrderPaidEvent', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-IDEMP-2',
        price: PriceSnapshot::create(subtotal: 75000.0),
        addressSnapshot: new AddressSnapshot('مهدی', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'm', 'مهدی'),
        items: []
    );

    $order->approvePayment(5);
    $events = $order->releaseEvents();
    // Events: OrderSubmittedEvent (1) + PaymentReceiptApprovedEvent (1) + OrderPaidEvent (1) = 3
    $r->assertEquals(3, count($events));

    // Attempt duplicate approval
    try {
        $order->approvePayment(5);
    } catch (PaymentAlreadyVerifiedException $e) {
        // Expected
    }

    // Crucial Invariant: Aggregate root must have 0 new events queued
    $newEvents = $order->releaseEvents();
    $r->assertEquals(0, count($newEvents), 'Duplicate approval must not emit any events');
});

$r->test('Free order with total 0 is auto-paid and rejects manual payment approval', function () use ($r) {
    $freeOrder = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-FREE-IDEMP',
        price: PriceSnapshot::create(subtotal: 0.0), // Free order
        addressSnapshot: new AddressSnapshot('مهدی', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'm', 'مهدی'),
        items: []
    );

    $r->assertEquals(OrderStatus::Paid, $freeOrder->getStatus());
    $r->assertEquals(PaymentStatus::Paid, $freeOrder->getPaymentStatus());

    // Calling approvePayment on free order must throw PaymentAlreadyVerifiedException
    $r->assertThrows(PaymentAlreadyVerifiedException::class, function () use ($freeOrder) {
        $freeOrder->approvePayment(5);
    });
});

$r->test('PaymentAlreadyVerifiedException contains correct error code, status, and payload', function () use ($r) {
    $order = Order::create(
        shopId: 1,
        customerId: 1,
        uuid: 'ORD-META-1',
        price: PriceSnapshot::create(subtotal: 10000.0),
        addressSnapshot: new AddressSnapshot('مهدی', '09120000000', 'تهران', 'تهران', 'آدرس'),
        customerSnapshot: new CustomerSnapshot(1, 'm', 'مهدی'),
        items: []
    );
    $order->approvePayment(1);

    try {
        $order->approvePayment(1);
        $r->assert(false, 'Should have thrown');
    } catch (PaymentAlreadyVerifiedException $e) {
        $r->assertEquals('PAYMENT_ALREADY_VERIFIED', $e->getErrorCode());
        $r->assertEquals(400, $e->getHttpStatusCode());
        $context = $e->getContext();
        $r->assertEquals('paid', $context['current_status'] ?? null);
    }
});

$r->test('CardReceipt approval and rejection idempotency matrix', function () use ($r) {
    // 1. Pending -> Approved -> [Approve throws, Reject throws]
    $rec1 = new CardReceipt(orderId: 10, filePath: 'r1.jpg', referenceNumber: 'REF-1');
    $rec1->approve(1);
    $r->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $rec1->approve(2));
    $r->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $rec1->reject('err', 2));

    // 2. Pending -> Rejected -> [Reject throws, Approve throws]
    $rec2 = new CardReceipt(orderId: 11, filePath: 'r2.jpg', referenceNumber: 'REF-2');
    $rec2->reject('unclear', 1);
    $r->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $rec2->reject('err2', 2));
    $r->assertThrows(PaymentAlreadyVerifiedException::class, fn() => $rec2->approve(2));
});

// =========================================================================
// 4. PLAN LIMIT QUOTAS: EXACT BOUNDARIES & TICKET ESCALATION
// =========================================================================
echo "\n=== Focus Area 4: Subscription Plan Quotas & Ceilings ===\n";

$r->test('Free tier plan quotas exact boundaries', function () use ($r) {
    $free = new SubscriptionPlan(
        id: 1,
        slug: 'free',
        name: 'Free',
        priceIrt: 0.0,
        maxBusinesses: 1,
        maxBranches: 0,
        maxProducts: 50,
        maxManagers: 0,
        hasTicketing: false,
        hasOnlinePayment: false,
        hasTicketEscalation: false
    );

    // Branches: max 0
    $r->assert(!$free->canAddBranch(0));
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($free) {
        $free->assertCanCreateBranch(0);
    });

    // Managers: max 0
    $r->assert(!$free->canAddManager(0));
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($free) {
        $free->assertCanAssignManager(0);
    });

    // Products: max 50
    $r->assert($free->canAddProduct(49), '49 products: can add 50th');
    $free->assertCanCreateProduct(49);
    $r->assert(!$free->canAddProduct(50), '50 products: cannot add 51st');
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($free) {
        $free->assertCanCreateProduct(50);
    });

    // Ticket escalation: restricted
    $r->assert(!$free->canEscalateTicket());
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($free) {
        $free->assertCanEscalateTicket();
    });
});

$r->test('Plus and Pro plan ticket escalation is strictly restricted', function () use ($r) {
    $plus = new SubscriptionPlan(
        id: 2,
        slug: 'plus',
        name: 'Plus',
        priceIrt: 490000.0,
        maxBusinesses: 1,
        maxBranches: 3,
        maxProducts: 200,
        maxManagers: 3,
        hasTicketing: true,
        hasOnlinePayment: true,
        hasTicketEscalation: false
    );

    $pro = new SubscriptionPlan(
        id: 3,
        slug: 'pro',
        name: 'Pro',
        priceIrt: 990000.0,
        maxBusinesses: 1,
        maxBranches: 5,
        maxProducts: 1000,
        maxManagers: 10,
        hasTicketing: true,
        hasOnlinePayment: true,
        hasTicketEscalation: false
    );

    $r->assert(!$plus->canEscalateTicket());
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($plus) {
        $plus->assertCanEscalateTicket();
    });

    $r->assert(!$pro->canEscalateTicket());
    $r->assertThrows(SubscriptionLimitExceededException::class, function () use ($pro) {
        $pro->assertCanEscalateTicket();
    });
});

$r->test('Ultimate tier allows ticket escalation exclusively', function () use ($r) {
    $ultimate = new SubscriptionPlan(
        id: 4,
        slug: 'ultimate',
        name: 'Ultimate',
        priceIrt: 1990000.0,
        maxBusinesses: 10,
        maxBranches: 9999,
        maxProducts: 999999,
        maxManagers: 9999,
        hasTicketing: true,
        hasOnlinePayment: true,
        hasTicketEscalation: true
    );

    $r->assert($ultimate->canEscalateTicket());
    $ultimate->assertCanEscalateTicket(); // Must not throw
    $r->assert($ultimate->isUltimate());
});

// =========================================================================
// 5. PURCHASE LIMITS: EXACT PER-ORDER AND MONTHLY BOUNDARIES
// =========================================================================
echo "\n=== Focus Area 5: Purchase Limits Exact Boundaries ===\n";

$r->test('PurchaseLimits boundary assertions', function () use ($r) {
    $limits = new PurchaseLimits(
        maxPerOrder: 5.0,
        maxPerMonth: 10.0,
        minOrderQty: 1.0
    );

    // Below minOrderQty throws InvalidArgumentException
    $r->assertThrows(\InvalidArgumentException::class, function () use ($limits) {
        $limits->validate(1, 'کالا', 0.5, 100.0, 0.0);
    });

    // Exactly at minOrderQty passes
    $limits->validate(1, 'کالا', 1.0, 100.0, 0.0);

    // Exactly at available stock passes
    $limits->validate(1, 'کالا', 5.0, 5.0, 0.0);

    // Above available stock throws InsufficientStockException
    $r->assertThrows(InsufficientStockException::class, function () use ($limits) {
        $limits->validate(1, 'کالا', 5.0, 4.99, 0.0);
    });

    // Exactly at maxPerOrder passes
    $limits->validate(1, 'کالا', 5.0, 100.0, 0.0);

    // Above maxPerOrder throws PurchaseLimitExceededException
    $r->assertThrows(PurchaseLimitExceededException::class, function () use ($limits) {
        $limits->validate(1, 'کالا', 5.001, 100.0, 0.0);
    });

    // Exactly at maxPerMonth (past: 6, requested: 4 => sum 10) passes
    $limits->validate(1, 'کالا', 4.0, 100.0, 6.0);

    // Above maxPerMonth (past: 6, requested: 4.01 => sum 10.01) throws
    $r->assertThrows(PurchaseLimitExceededException::class, function () use ($limits) {
        $limits->validate(1, 'کالا', 4.01, 100.0, 6.0);
    });
});

$r->test('Product::assertCanPurchase integrates stock and caps', function () use ($r) {
    $product = new Product(
        shopId: 1,
        title: 'عسل طبیعی دمنوش',
        price: 250000,
        stockQuantity: 15.0,
        reservedQuantity: 5.0, // available = 10
        maxPerOrder: 4.0,
        maxPerMonth: 8.0
    );

    // Valid purchase: 3 <= 10 (avail), 3 <= 4 (order cap), 3 + 4 = 7 <= 8 (monthly cap)
    $product->assertCanPurchase(3.0, past30DaysPurchased: 4.0);

    // Available stock exceeded: requesting 11 when avail is 10
    $r->assertThrows(InsufficientStockException::class, function () use ($product) {
        $product->assertCanPurchase(11.0);
    });

    // Order cap exceeded: requesting 5 when maxPerOrder is 4
    $r->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(5.0);
    });

    // Monthly cap exceeded: requesting 4 when past is 5 => 9 > 8
    $r->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(4.0, past30DaysPurchased: 5.0);
    });
});

$r->test('Product::assertCanPurchase enforces minOrderQty', function () use ($r) {
    $product = new Product(
        shopId: 1,
        title: 'بسته عمده زعفران',
        price: 500000,
        stockQuantity: 100.0,
        minOrderQty: 5.0,
        maxPerOrder: 20.0
    );

    // 1. Below minOrderQty (4.99 < 5.0) -> must throw
    $r->assertThrows(\InvalidArgumentException::class, function () use ($product) {
        $product->assertCanPurchase(4.99);
    });

    // 2. Exactly at minOrderQty (5.0) -> passes
    $product->assertCanPurchase(5.0);

    // 3. Above minOrderQty within maxPerOrder (10.0) -> passes
    $product->assertCanPurchase(10.0);

    // 4. Above maxPerOrder (21.0) -> throws PurchaseLimitExceededException
    $r->assertThrows(PurchaseLimitExceededException::class, function () use ($product) {
        $product->assertCanPurchase(21.0);
    });
});

$r->test('Product::assertCanPurchase default minOrderQty rejects sub-unit quantity', function () use ($r) {
    $product = new Product(
        shopId: 1,
        title: 'کتاب آموزش DDD',
        price: 150000,
        stockQuantity: 10.0
        // default minOrderQty = 1.0
    );

    $r->assertThrows(\InvalidArgumentException::class, function () use ($product) {
        $product->assertCanPurchase(0.5);
    });

    $product->assertCanPurchase(1.0); // passes
});

// =========================================================================
// 6. ADVERSARIAL STRESS TEST: INTEGRITY AND CODE PATTERNS
// =========================================================================
echo "\n=== Section 6: Source Code Integrity Audit ===\n";

$r->test('Zero hardcoded test fixtures or facade dummy methods in src/Domain/', function () use ($r) {
    $domainDir = realpath(__DIR__ . '/../src/Domain');
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($domainDir));

    $suspiciousPatterns = [
        'return true; // dummy',
        'return false; // dummy',
        '// facade',
        '// mock',
        '// stub',
    ];

    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        foreach ($suspiciousPatterns as $pattern) {
            $r->assert(
                !str_contains(strtolower($content), $pattern),
                "Suspicious stub pattern '{$pattern}' in {$file->getPathname()}"
            );
        }
    }
});

exit($r->summary());
