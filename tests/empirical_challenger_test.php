<?php

declare(strict_types=1);

/**
 * Empirical Challenger Verification Harness for Milestone R1
 * Focus: Double-Entry Accounting Ledger (DoubleEntryLedgerEntry, LedgerTransaction, LedgerAccount)
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

use App\Domain\Exceptions\InvalidLedgerEntryException;
use App\Domain\Exceptions\UnbalancedLedgerException;
use App\Domain\Payment\DoubleEntryLedgerEntry;
use App\Domain\Payment\LedgerAccount;
use App\Domain\Payment\LedgerEntryType;
use App\Domain\Payment\LedgerTransaction;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function runCheck(string $desc, callable $fn): void {
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    try {
        $fn();
        $passedTests++;
        echo "  [OK] {$desc}\n";
    } catch (\Throwable $e) {
        $failedTests++;
        $failures[] = "{$desc}: " . $e->getMessage();
        echo "  [FAIL] {$desc}: " . $e->getMessage() . "\n";
    }
}

echo "=== Empirical Challenge 1: Single-Entry Invariants ===\n";

runCheck("Reject debit < 0 (-0.01)", function () {
    try {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, -0.01, 0.0, 'test');
        throw new \Exception("Expected exception not thrown");
    } catch (InvalidLedgerEntryException) {
        // Expected
    }
});

runCheck("Reject credit < 0 (-0.01)", function () {
    try {
        new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, LedgerEntryType::SaleRevenue, 0.0, -0.01, 'test');
        throw new \Exception("Expected exception not thrown");
    } catch (InvalidLedgerEntryException) {
        // Expected
    }
});

runCheck("Reject both debit and credit > 0", function () {
    try {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, 100.0, 100.0, 'test');
        throw new \Exception("Expected exception not thrown");
    } catch (InvalidLedgerEntryException) {
        // Expected
    }
});

runCheck("Reject both debit and credit = 0", function () {
    try {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, 0.0, 0.0, 'test');
        throw new \Exception("Expected exception not thrown");
    } catch (InvalidLedgerEntryException) {
        // Expected
    }
});

runCheck("Reject NAN and INF amounts", function () {
    foreach ([NAN, INF, -INF] as $val) {
        try {
            new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, $val, 0.0, 'test');
            throw new \Exception("Failed to reject {$val} in debit");
        } catch (InvalidLedgerEntryException) {
            // Expected
        }
        try {
            new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, 0.0, $val, 'test');
            throw new \Exception("Failed to reject {$val} in credit");
        } catch (InvalidLedgerEntryException) {
            // Expected
        }
    }
});

echo "\n=== Empirical Challenge 2: LedgerAccount Enum & Chart of Accounts ===\n";

runCheck("Verify all 8 account codes, Persian labels, and normal balances", function () {
    $expected = [
        'cash_bank' => ['normal' => 'debit', 'label' => 'نقد و بانک'],
        'inventory_asset' => ['normal' => 'debit', 'label' => 'موجودی کالای انبار'],
        'sales_revenue' => ['normal' => 'credit', 'label' => 'درآمد فروش کالا'],
        'shipping_revenue' => ['normal' => 'credit', 'label' => 'درآمد خدمات حمل و نقل'],
        'cogs' => ['normal' => 'debit', 'label' => 'بهای تمام‌شده کالای فروش‌رفته (COGS)'],
        'tax_payable' => ['normal' => 'credit', 'label' => 'مالیات بر ارزش افزوده پرداختنی'],
        'discounts' => ['normal' => 'debit', 'label' => 'تخفیفات اعطایی به مشتریان'],
        'adjustment' => ['normal' => 'variable', 'label' => 'تعدیلات و ضایعات انبار'],
    ];

    if (count(LedgerAccount::cases()) !== 8) {
        throw new \Exception("Expected 8 accounts, got " . count(LedgerAccount::cases()));
    }

    foreach (LedgerAccount::cases() as $case) {
        if (!isset($expected[$case->value])) {
            throw new \Exception("Unexpected case: {$case->value}");
        }
        if ($case->normalBalance() !== $expected[$case->value]['normal']) {
            throw new \Exception("Mismatch normal balance for {$case->value}");
        }
        if ($case->label() !== $expected[$case->value]['label']) {
            throw new \Exception("Mismatch label for {$case->value}");
        }
    }
});

echo "\n=== Empirical Challenge 3: Specific Edge Cases from User Dispatch ===\n";

runCheck("Edge Case 1: 100% discount order (grandTotal = 0)", function () {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 101,
        orderUuid: 'ORD-100PCT',
        subtotal: 500000.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 500000.0,
        grandTotal: 0.0
    );

    $entries = $tx->getEntries();
    if (count($entries) !== 2) {
        throw new \Exception("Expected exactly 2 entries (Discounts debit, SalesRevenue credit), got " . count($entries));
    }

    $debitTotal = array_sum(array_map(fn($e) => $e->getDebit(), $entries));
    $creditTotal = array_sum(array_map(fn($e) => $e->getCredit(), $entries));

    if (abs($debitTotal - 500000.0) > 0.0001 || abs($creditTotal - 500000.0) > 0.0001) {
        throw new \Exception("Sums incorrect: Debit={$debitTotal}, Credit={$creditTotal}");
    }

    $tx->assertBalanced();
});

runCheck("Edge Case 2: Free gift sample with shipping cost only", function () {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 102,
        orderUuid: 'ORD-FREE-SAMPLE-SHIPPING',
        subtotal: 0.0,
        taxAmount: 0.0,
        shippingCost: 35000.0,
        discountAmount: 0.0,
        grandTotal: 35000.0
    );

    $entries = $tx->getEntries();
    if (count($entries) !== 2) {
        throw new \Exception("Expected exactly 2 entries (CashBank debit, ShippingRevenue credit), got " . count($entries));
    }

    $hasCash = false;
    $hasShipping = false;
    foreach ($entries as $e) {
        if ($e->getAccount() === LedgerAccount::CashBank && $e->getDebit() === 35000.0) {
            $hasCash = true;
        }
        if ($e->getAccount() === LedgerAccount::ShippingRevenue && $e->getCredit() === 35000.0) {
            $hasShipping = true;
        }
    }

    if (!$hasCash || !$hasShipping) {
        throw new \Exception("Missing expected CashBank or ShippingRevenue entry");
    }

    $tx->assertBalanced();
});

runCheck("Edge Case 3: Completely free sample (subtotal = 0, grandTotal = 0)", function () {
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 103,
        orderUuid: 'ORD-ZERO-FREE',
        subtotal: 0.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 0.0,
        grandTotal: 0.0
    );

    $entries = $tx->getEntries();
    if (count($entries) !== 0) {
        throw new \Exception("Completely free sample should generate 0 financial entries, got " . count($entries));
    }

    $tx->assertBalanced();
});

runCheck("Edge Case 4: Negative amount rejection in forOrderPayment", function () {
    $badInputs = [
        ['subtotal' => -100.0, 'tax' => 0.0, 'shipping' => 0.0, 'discount' => 0.0, 'grandTotal' => -100.0],
        ['subtotal' => 100.0, 'tax' => -10.0, 'shipping' => 0.0, 'discount' => 0.0, 'grandTotal' => 90.0],
        ['subtotal' => 100.0, 'tax' => 0.0, 'shipping' => -50.0, 'discount' => 0.0, 'grandTotal' => 50.0],
        ['subtotal' => 100.0, 'tax' => 0.0, 'shipping' => 0.0, 'discount' => -10.0, 'grandTotal' => 110.0],
        ['subtotal' => 100.0, 'tax' => 0.0, 'shipping' => 0.0, 'discount' => 0.0, 'grandTotal' => -50.0],
    ];

    foreach ($badInputs as $idx => $in) {
        try {
            LedgerTransaction::forOrderPayment(
                shopId: 1,
                orderId: 200 + $idx,
                orderUuid: "NEG-{$idx}",
                subtotal: $in['subtotal'],
                taxAmount: $in['tax'],
                shippingCost: $in['shipping'],
                discountAmount: $in['discount'],
                grandTotal: $in['grandTotal']
            );
            throw new \Exception("Failed to throw InvalidLedgerEntryException on input {$idx}");
        } catch (InvalidLedgerEntryException) {
            // Expected
        }
    }
});

runCheck("Edge Case 5: Decimal rounding differences and balance verification (Debit == Credit)", function () {
    // 3 items with fractional division (e.g., 100 / 3)
    $subtotal = 333333.33;
    $tax = round($subtotal * 0.09, 2); // 29999.9997 -> 30000.00
    $shipping = 25000.00;
    $discount = 50000.55;
    $grandTotal = round($subtotal + $tax + $shipping - $discount, 2); // 338332.78

    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 301,
        orderUuid: 'DECIMAL-301',
        subtotal: $subtotal,
        taxAmount: $tax,
        shippingCost: $shipping,
        discountAmount: $discount,
        grandTotal: $grandTotal
    );

    $tx->assertBalanced();

    // Verify unbalance detection if grandTotal drifts by 0.01
    try {
        LedgerTransaction::forOrderPayment(
            shopId: 1,
            orderId: 302,
            orderUuid: 'DECIMAL-302',
            subtotal: $subtotal,
            taxAmount: $tax,
            shippingCost: $shipping,
            discountAmount: $discount,
            grandTotal: $grandTotal + 0.01 // Tampered
        );
        throw new \Exception("Expected UnbalancedLedgerException on 0.01 drift");
    } catch (UnbalancedLedgerException $uex) {
        if ($uex->difference < 0.005) {
            throw new \Exception("Difference reported incorrectly: {$uex->difference}");
        }
    }
});

echo "\n=== Empirical Challenge 4: High-Stress Boundary Invariance Matrix ===\n";

runCheck("Large batch balancing (10,000 balanced pairs)", function () {
    $entries = [];
    for ($i = 0; $i < 10000; $i++) {
        $amount = (float)mt_rand(10, 100000);
        $entries[] = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, $amount, 0.0, "D{$i}");
        $entries[] = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, LedgerEntryType::SaleRevenue, 0.0, $amount, "C{$i}");
    }
    DoubleEntryLedgerEntry::assertBalancedBatch($entries);
});

runCheck("Order payment with complex coupon and multiple tax buckets", function () {
    // Subtotal: 1,250,500.50
    // Tax: 112,545.05
    // Shipping: 45,000.00
    // Discount: 350,250.25
    // GrandTotal: 1,057,795.30
    $subtotal = 1250500.50;
    $tax = 112545.05;
    $shipping = 45000.00;
    $discount = 350250.25;
    $grandTotal = 1057795.30;

    $tx = LedgerTransaction::forOrderPayment(
        shopId: 10,
        orderId: 999,
        orderUuid: 'COMPLEX-999',
        subtotal: $subtotal,
        taxAmount: $tax,
        shippingCost: $shipping,
        discountAmount: $discount,
        grandTotal: $grandTotal
    );

    $tx->assertBalanced();

    // Check entry count: CashBank, Discounts, SalesRevenue, TaxPayable, ShippingRevenue = 5
    if (count($tx->getEntries()) !== 5) {
        throw new \Exception("Expected 5 entries, got " . count($tx->getEntries()));
    }
});

echo "\n======================================================\n";
printf("CHALLENGER RESULTS: Total: %d | Passed: %d | Failed: %d\n", $totalTests, $passedTests, $failedTests);
echo "======================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
