<?php

declare(strict_types=1);

/**
 * Challenger Test Harness: Milestone R1 Double-Entry Accounting & Financial Invariants
 *
 * Empirically stress-tests:
 * 1. Single entry invariant validation (negative amounts, mutual exclusivity, zero amounts, non-finite values).
 * 2. General ledger balancing equation (assertBalancedBatch, zero-difference assertion, UnbalancedLedgerException).
 * 3. Floating-point precision, IEEE 754 drift, and sub-cent rounding tolerance.
 * 4. 500+ randomized property-based combinations of order subtotals, tax rates, shipping, and discounts.
 * 5. Zero false negatives under adversarial perturbation (tampered entries).
 * 6. Order payment voucher edge cases (0 grand total, 0 subtotal, excess discounts, negative inputs).
 */

// PSR-4 Autoloader for App\Domain\...
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

final class DoubleEntryChallengeHarness
{
    private int $passed = 0;
    private int $failed = 0;

    /** @var array<array{category: string, name: string, error: string}> */
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
                'category' => $category,
                'name' => $name,
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
                sprintf("Expected exception %s, but caught %s: %s", $expectedClass, get_class($e), $e->getMessage())
            );
        }
        throw new \RuntimeException($msg ?: sprintf("Expected exception %s, but nothing was thrown", $expectedClass));
    }

    public function printSummary(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "DOUBLE-ENTRY ACCOUNTING CHALLENGE TEST SUMMARY\n";
        echo str_repeat('=', 80) . "\n";

        foreach ($this->categoryStats as $cat => $stats) {
            $total = $stats['passed'] + $stats['failed'];
            $pct = $total > 0 ? round(($stats['passed'] / $total) * 100, 1) : 0;
            printf("  %-35s | Passed: %4d | Failed: %3d | Rate: %5.1f%%\n", $cat, $stats['passed'], $stats['failed'], $pct);
        }

        echo str_repeat('-', 80) . "\n";
        printf("TOTAL TESTS: %d | PASSED: %d | FAILED: %d\n", $this->passed + $this->failed, $this->passed, $this->failed);

        if (!empty($this->failures)) {
            echo "\nCONFIRMED FAILURES / EMPIRICAL BUG FINDINGS:\n";
            foreach ($this->failures as $i => $f) {
                printf("  %d. [%s] %s\n     %s\n", $i + 1, $f['category'], $f['name'], $f['error']);
            }
        }
        echo str_repeat('=', 80) . "\n";
    }

    public function getFailedCount(): int
    {
        return $this->failed;
    }

    public function getPassedCount(): int
    {
        return $this->passed;
    }

    /**
     * @return array<array{category: string, name: string, error: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}

$h = new DoubleEntryChallengeHarness();

echo "================================================================================\n";
echo "Starting Challenger Stress Suite for Double-Entry Accounting (src/Domain/Payment)\n";
echo "================================================================================\n\n";

// ============================================================================
// SECTION 1: Single-Entry Invariant Validation (DoubleEntryLedgerEntry)
// ============================================================================
echo "=== Section 1: Single-Entry Invariant Validation ===\n";

$h->test('SingleEntry', 'Accepts valid debit-only entry', function () use ($h) {
    $e = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, LedgerEntryType::PaymentReceived, 500000.0, 0.0, 'واریز وجه');
    $h->assertEquals(500000.0, $e->getDebit());
    $h->assertEquals(0.0, $e->getCredit());
    $h->assertEquals(LedgerAccount::CashBank, $e->getAccount());
    $h->assertEquals('payment_received', $e->getEntryType());
});

$h->test('SingleEntry', 'Accepts valid credit-only entry', function () use ($h) {
    $e = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, LedgerEntryType::SaleRevenue, 0.0, 500000.0, 'درآمد فروش');
    $h->assertEquals(0.0, $e->getDebit());
    $h->assertEquals(500000.0, $e->getCredit());
});

$h->test('SingleEntry', 'Rejects negative debit amount (-0.01)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', -0.01, 0.0, 'منفی');
    });
});

$h->test('SingleEntry', 'Rejects negative debit large amount (-1000000.0)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', -1000000.0, 0.0, 'منفی بزرگ');
    });
});

$h->test('SingleEntry', 'Rejects negative credit amount (-0.01)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'test', 0.0, -0.01, 'بستانکار منفی');
    });
});

$h->test('SingleEntry', 'Rejects negative credit large amount (-1000000.0)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'test', 0.0, -1000000.0, 'بستانکار منفی بزرگ');
    });
});

$h->test('SingleEntry', 'Rejects simultaneous positive debit and positive credit (exclusivity)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', 500.0, 500.0, 'هر دو مثبت');
    });
});

$h->test('SingleEntry', 'Rejects micro positive debit and micro positive credit (exclusivity)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', 0.01, 0.01, 'هر دو خرد مثبت');
    });
});

$h->test('SingleEntry', 'Rejects zero debit and zero credit line', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', 0.0, 0.0, 'صفر');
    });
});

$h->test('SingleEntry', 'Rejects sub-threshold amounts (e.g. 0.00005 below 0.0001 epsilon)', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', 0.00005, 0.0, 'زیر آستانه');
    });
});

// Non-finite value stress tests
$h->test('SingleEntry', 'Rejects NAN debit amount', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', NAN, 0.0, 'NAN');
    }, 'NAN debit must be rejected by invariant validation');
});

$h->test('SingleEntry', 'Rejects NAN credit amount', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'test', 0.0, NAN, 'NAN');
    }, 'NAN credit must be rejected by invariant validation');
});

$h->test('SingleEntry', 'Rejects INF debit amount', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'test', INF, 0.0, 'INF');
    }, 'INF debit must be rejected by invariant validation');
});

$h->test('SingleEntry', 'Rejects -INF credit amount', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'test', 0.0, -INF, '-INF');
    }, '-INF credit must be rejected by invariant validation');
});

// ============================================================================
// SECTION 2: General Ledger Balancing Assertion (assertBalancedBatch)
// ============================================================================
echo "\n=== Section 2: General Ledger Balancing Assertion ===\n";

$h->test('BatchBalancing', 'Accepts perfectly balanced 2-leg batch', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 250000.0, 0.0, 'بدهکار');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 250000.0, 'بستانکار');
    DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    $h->assert(true);
});

$h->test('BatchBalancing', 'Accepts balanced multi-leg compound batch (3 debits, 2 credits)', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 100000.0, 0.0, 'نقد');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::Discounts, 'disc', 20000.0, 0.0, 'تخفیف');
    $e3 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 30000.0, 0.0, 'چک');
    $e4 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 140000.0, 'فروش');
    $e5 = new DoubleEntryLedgerEntry(1, LedgerAccount::TaxPayable, 'tax', 0.0, 10000.0, 'مالیات');

    // Debits: 100,000 + 20,000 + 30,000 = 150,000
    // Credits: 140,000 + 10,000 = 150,000
    DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2, $e3, $e4, $e5]);
    $h->assert(true);
});

$h->test('BatchBalancing', 'Rejects unbalanced batch with 0.01 cent discrepancy', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 100000.0, 0.0, 'بدهکار');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 100000.01, 'بستانکار اضافه');

    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    });
});

$h->test('BatchBalancing', 'Rejects unbalanced batch with large discrepancy', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 1000000.0, 0.0, 'بدهکار');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 500000.0, 'بستانکار ناقص');

    try {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
        $h->assert(false, 'Did not throw');
    } catch (UnbalancedLedgerException $ex) {
        $h->assertEquals(1000000.0, $ex->totalDebit);
        $h->assertEquals(500000.0, $ex->totalCredit);
        $h->assertEquals(500000.0, $ex->difference);
        $h->assertEquals('UNBALANCED_LEDGER_ENTRIES', $ex->getErrorCode());
        $h->assertEquals(500, $ex->getHttpStatusCode());
    }
});

$h->test('BatchBalancing', 'Rejects batch with single debit leg only', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 50000.0, 0.0, 'تک ردیف');
    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1]);
    });
});

$h->test('BatchBalancing', 'Rejects batch with all debits and zero credits', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 50000.0, 0.0, 'بدهکار ۱');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 25000.0, 0.0, 'بدهکار ۲');
    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    });
});

$h->test('BatchBalancing', 'Rejects batch with all credits and zero debits', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 50000.0, 'بستانکار ۱');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::ShippingRevenue, 'shp', 0.0, 25000.0, 'بستانکار ۲');
    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    });
});

$h->test('BatchBalancing', 'Empty batch evaluation', function () use ($h) {
    // An empty array in assertBalancedBatch: total debit = 0, total credit = 0.
    // Does it pass or throw? Double entry voucher must contain at least 2 legs.
    DoubleEntryLedgerEntry::assertBalancedBatch([]);
    $h->assert(true);
});

$h->test('BatchBalancing', 'Rejects batch containing NAN debit (False Negative test)', function () use ($h) {
    // If an entry bypasses single-entry validation with NAN, assertBalancedBatch must catch it
    $ref = new \ReflectionClass(DoubleEntryLedgerEntry::class);
    $e1 = $ref->newInstanceWithoutConstructor();
    $ref->getProperty('debit')->setValue($e1, NAN);
    $ref->getProperty('credit')->setValue($e1, 0.0);
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'test', 0.0, 100000.0, 'بستانکار معتبر');

    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    }, 'assertBalancedBatch MUST reject batches containing NAN amounts instead of silently passing');
});

$h->test('BatchBalancing', 'Rejects batch containing INF debit and credit (False Negative test)', function () use ($h) {
    $ref = new \ReflectionClass(DoubleEntryLedgerEntry::class);
    $e1 = $ref->newInstanceWithoutConstructor();
    $ref->getProperty('debit')->setValue($e1, INF);
    $ref->getProperty('credit')->setValue($e1, 0.0);

    $e2 = $ref->newInstanceWithoutConstructor();
    $ref->getProperty('debit')->setValue($e2, 0.0);
    $ref->getProperty('credit')->setValue($e2, INF);

    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    }, 'assertBalancedBatch MUST reject batches containing INF amounts instead of silently passing');
});

// ============================================================================
// SECTION 3: Floating-Point Precision, IEEE 754 Drift & Sub-Cent Tolerance
// ============================================================================
echo "\n=== Section 3: Floating-Point Precision & Rounding Stress Tests ===\n";

$h->test('FloatPrecision', 'Cumulative binary floating drift (10 x 0.1 vs 1.0)', function () use ($h) {
    // In IEEE 754, sum of ten 0.1 is 0.9999999999999999
    $entries = [];
    for ($i = 0; $i < 10; $i++) {
        $entries[] = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 0.1, 0.0, "جزء {$i}");
    }
    $entries[] = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 1.0, 'کل');

    DoubleEntryLedgerEntry::assertBalancedBatch($entries);
    $h->assert(true);
});

$h->test('FloatPrecision', 'Extreme currency scale (50 Billion Tomans)', function () use ($h) {
    $largeAmount = 50_000_000_000.0;
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', $largeAmount, 0.0, 'مبلغ عظیم');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, $largeAmount, 'مبلغ عظیم');

    DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    $h->assert(true);
});

$h->test('FloatPrecision', 'Discrepancy at exactly 0.005 is rejected', function () use ($h) {
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 10.005, 0.0, 'بدهکار');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 10.000, 'بستانکار');

    $h->assertThrows(UnbalancedLedgerException::class, function () use ($e1, $e2) {
        DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    });
});

$h->test('FloatPrecision', 'Discrepancy at 0.0049 (sub-cent rounding tolerance boundary)', function () use ($h) {
    // Because assertBalancedBatch performs round($total, 2) before comparing,
    // 10.0049 rounds to 10.00 and 10.000 rounds to 10.00, yielding diff = 0.00.
    // We document and verify this exact behavior.
    $e1 = new DoubleEntryLedgerEntry(1, LedgerAccount::CashBank, 'pmt', 10.0049, 0.0, 'بدهکار');
    $e2 = new DoubleEntryLedgerEntry(1, LedgerAccount::SalesRevenue, 'rev', 0.0, 10.0000, 'بستانکار');

    // This passes because of round(..., 2).
    DoubleEntryLedgerEntry::assertBalancedBatch([$e1, $e2]);
    $h->assert(true);
});

// ============================================================================
// SECTION 4: 500+ Randomized Property-Based Order Stress Tests
// ============================================================================
echo "\n=== Section 4: 500+ Randomized Property-Based Order Stress Tests ===\n";

$iterations = 600;
$successfulVouchers = 0;
$perturbationCatches = 0;
$permutationPasses = 0;

$taxRateOptions = [0.0, 0.05, 0.09, 0.10];
$shippingOptions = [0.0, 25000.0, 35000.0, 50000.0, 75000.0, 120000.0];

mt_srand(133742); // Deterministic seed for reproducible property tests

for ($i = 1; $i <= $iterations; $i++) {
    // Generate randomized order parameters
    // Subtotal: 1,000 to 100,000,000 with occasional cent decimals
    $isDecimal = ($i % 5 === 0);
    $subtotal = $isDecimal
        ? (float)mt_rand(10000, 50000000) / 100.0
        : (float)mt_rand(1000, 50000000);

    $taxRate = $taxRateOptions[mt_rand(0, count($taxRateOptions) - 1)];
    $taxAmount = round($subtotal * $taxRate, 2);

    $shippingCost = $shippingOptions[mt_rand(0, count($shippingOptions) - 1)];

    // Discount: between 0 and (subtotal + taxAmount + shippingCost - 1.0)
    $maxDiscount = max(0.0, $subtotal + $taxAmount + $shippingCost - 1.0);
    $discountAmount = 0.0;
    if ($maxDiscount > 0 && ($i % 3 !== 0)) {
        $discountAmount = $isDecimal
            ? round((float)mt_rand(0, (int)($maxDiscount * 100)) / 100.0, 2)
            : (float)mt_rand(0, (int)$maxDiscount);
    }

    $grandTotal = round($subtotal + $taxAmount + $shippingCost - $discountAmount, 2);

    // Test 4A: Voucher Construction & Double-Entry Balancing
    try {
        $tx = LedgerTransaction::forOrderPayment(
            shopId: 1,
            orderId: $i,
            orderUuid: "PROP-ORD-{$i}",
            subtotal: $subtotal,
            taxAmount: $taxAmount,
            shippingCost: $shippingCost,
            discountAmount: $discountAmount,
            grandTotal: $grandTotal,
            verifierId: 10
        );

        $entries = $tx->getEntries();
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entries as $e) {
            $totalDebit += $e->getDebit();
            $totalCredit += $e->getCredit();
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $diff = abs($totalDebit - $totalCredit);

        if ($diff > 0.0001) {
            throw new \RuntimeException(sprintf("Voucher #%d unbalance: Debit=%.2f, Credit=%.2f, Diff=%.4f", $i, $totalDebit, $totalCredit, $diff));
        }

        $tx->assertBalanced();
        $successfulVouchers++;
    } catch (\Throwable $e) {
        $h->test('PropertyTesting', "Run #{$i} forOrderPayment balanced generation", function () use ($e) {
            throw $e;
        });
        continue;
    }

    // Test 4B: Zero False Negatives via Adversarial Perturbation
    // Tamper with one entry in the generated batch by a random perturb amount
    $tamperedEntries = $entries;
    $perturbationAmount = ($i % 2 === 0) ? 0.01 : (float)mt_rand(1, 100);
    $tamperIndex = mt_rand(0, count($tamperedEntries) - 1);
    $originalEntry = $tamperedEntries[$tamperIndex];

    $newDebit = $originalEntry->getDebit() > 0 ? $originalEntry->getDebit() + $perturbationAmount : 0.0;
    $newCredit = $originalEntry->getCredit() > 0 ? $originalEntry->getCredit() + $perturbationAmount : 0.0;

    $tamperedEntries[$tamperIndex] = new DoubleEntryLedgerEntry(
        shopId: $originalEntry->getShopId(),
        account: $originalEntry->getAccount(),
        entryType: $originalEntry->getEntryType(),
        debit: $newDebit,
        credit: $newCredit,
        description: 'دستکاری شده',
        orderId: $originalEntry->getOrderId()
    );

    $caught = false;
    try {
        DoubleEntryLedgerEntry::assertBalancedBatch($tamperedEntries);
    } catch (UnbalancedLedgerException) {
        $caught = true;
        $perturbationCatches++;
    }

    if (!$caught) {
        $h->test('PropertyTesting', "Run #{$i} Tampered batch must throw UnbalancedLedgerException", function () use ($perturbationAmount) {
            throw new \RuntimeException("False Negative! Batch accepted despite +{$perturbationAmount} tampering!");
        });
    }

    // Test 4C: Invariance under entry permutation (shuffling)
    $shuffledEntries = $entries;
    shuffle($shuffledEntries);
    try {
        DoubleEntryLedgerEntry::assertBalancedBatch($shuffledEntries);
        $permutationPasses++;
    } catch (\Throwable $e) {
        $h->test('PropertyTesting', "Run #{$i} Shuffled batch balance", function () use ($e) {
            throw $e;
        });
    }
}

$h->test('PropertyTesting', "Successfully generated and verified {$iterations} randomized order vouchers", function () use ($h, $successfulVouchers, $iterations) {
    $h->assertEquals($iterations, $successfulVouchers);
});

$h->test('PropertyTesting', "Adversarial perturbation detected with 100% precision (Zero False Negatives: {$perturbationCatches}/{$iterations})", function () use ($h, $perturbationCatches, $iterations) {
    $h->assertEquals($iterations, $perturbationCatches);
});

$h->test('PropertyTesting', "Voucher balancing is order-invariant under permutation ({$permutationPasses}/{$iterations})", function () use ($h, $permutationPasses, $iterations) {
    $h->assertEquals($iterations, $permutationPasses);
});

// ============================================================================
// SECTION 5: Domain Edge Cases & Boundary Analysis in forOrderPayment
// ============================================================================
echo "\n=== Section 5: Order Payment Voucher Edge Cases ===\n";

$h->test('VoucherEdgeCases', 'Zero subtotal with shipping cost only (subtotal: 0.0, shipping: 50,000, grandTotal: 50,000)', function () use ($h) {
    // When promotional free product is ordered with shipping fee, subtotal is 0.0.
    // In forOrderPayment, SalesRevenue entry has credit: 0.0, debit: 0.0.
    // Does it succeed or throw InvalidLedgerEntryException?
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 991,
        orderUuid: 'FREE-SAMPLE-991',
        subtotal: 0.0,
        taxAmount: 0.0,
        shippingCost: 50000.0,
        discountAmount: 0.0,
        grandTotal: 50000.0
    );
    $tx->assertBalanced();
    $h->assert(true);
});

$h->test('VoucherEdgeCases', 'Free order with 100% discount (subtotal: 100,000, discount: 100,000, grandTotal: 0.0)', function () use ($h) {
    // When an order is 100% discounted, grandTotal is 0.0.
    // CashBank entry has debit: 0.0, credit: 0.0.
    // Does it succeed or throw InvalidLedgerEntryException?
    $tx = LedgerTransaction::forOrderPayment(
        shopId: 1,
        orderId: 992,
        orderUuid: '100PCT-DISC-992',
        subtotal: 100000.0,
        taxAmount: 0.0,
        shippingCost: 0.0,
        discountAmount: 100000.0,
        grandTotal: 0.0
    );
    $tx->assertBalanced();
    $h->assert(true);
});

$h->test('VoucherEdgeCases', 'Excess discount beyond order amount (subtotal: 100,000, discount: 150,000)', function () use ($h) {
    // If a discount exceeds the order subtotal, can it create a balanced voucher?
    // Total debit = grandTotal + discount = 0 + 150,000 = 150,000.
    // Total credit = subtotal = 100,000.
    // This is fundamentally unbalanced. It should throw UnbalancedLedgerException or InvalidLedgerEntryException.
    $h->assertThrows(\Throwable::class, function () {
        LedgerTransaction::forOrderPayment(
            shopId: 1,
            orderId: 993,
            orderUuid: 'EXCESS-DISC-993',
            subtotal: 100000.0,
            taxAmount: 0.0,
            shippingCost: 0.0,
            discountAmount: 150000.0,
            grandTotal: 0.0
        );
    });
});

$h->test('VoucherEdgeCases', 'Negative subtotal in forOrderPayment', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        LedgerTransaction::forOrderPayment(
            shopId: 1,
            orderId: 994,
            orderUuid: 'NEG-SUB-994',
            subtotal: -50000.0,
            taxAmount: 0.0,
            shippingCost: 0.0,
            discountAmount: 0.0,
            grandTotal: -50000.0
        );
    });
});

$h->test('VoucherEdgeCases', 'Negative grandTotal in forOrderPayment', function () use ($h) {
    $h->assertThrows(InvalidLedgerEntryException::class, function () {
        LedgerTransaction::forOrderPayment(
            shopId: 1,
            orderId: 995,
            orderUuid: 'NEG-GT-995',
            subtotal: 50000.0,
            taxAmount: 0.0,
            shippingCost: 0.0,
            discountAmount: 0.0,
            grandTotal: -50000.0
        );
    });
});

// ============================================================================
// SECTION 6: Chart of Accounts & Normal Balance Consistency
// ============================================================================
echo "\n=== Section 6: Chart of Accounts & Normal Balance Verification ===\n";

$h->test('ChartOfAccounts', 'All 8 standard account heads exist and have labels and normal balances', function () use ($h) {
    $accounts = LedgerAccount::cases();
    $h->assertEquals(8, count($accounts));

    foreach ($accounts as $acc) {
        $h->assert(strlen($acc->label()) > 0, "Account {$acc->value} has non-empty label");
        $h->assert(in_array($acc->normalBalance(), ['debit', 'credit', 'variable'], true), "Account {$acc->value} has valid normal balance");
    }

    $h->assertEquals('debit', LedgerAccount::CashBank->normalBalance());
    $h->assertEquals('debit', LedgerAccount::InventoryAsset->normalBalance());
    $h->assertEquals('debit', LedgerAccount::CostOfGoodsSold->normalBalance());
    $h->assertEquals('debit', LedgerAccount::Discounts->normalBalance());
    $h->assertEquals('credit', LedgerAccount::SalesRevenue->normalBalance());
    $h->assertEquals('credit', LedgerAccount::ShippingRevenue->normalBalance());
    $h->assertEquals('credit', LedgerAccount::TaxPayable->normalBalance());
    $h->assertEquals('variable', LedgerAccount::Adjustment->normalBalance());
});

$h->printSummary();

// Exit code reflects test outcome
exit($h->getFailedCount() === 0 ? 0 : 1);
