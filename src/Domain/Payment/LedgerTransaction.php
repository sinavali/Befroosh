<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Exceptions\InvalidLedgerEntryException;
use App\Domain\Exceptions\UnbalancedLedgerException;
use DateTimeImmutable;

/**
 * Ledger transaction aggregate orchestrating balanced double-entry vouchers.
 */
final class LedgerTransaction
{
    /** @var array<DoubleEntryLedgerEntry> */
    private array $entries = [];

    /**
     * @param array<DoubleEntryLedgerEntry> $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            $this->addEntry($entry);
        }
    }

    public function addEntry(DoubleEntryLedgerEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return array<DoubleEntryLedgerEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Enforces balanced debit/credit equation.
     */
    public function assertBalanced(): void
    {
        DoubleEntryLedgerEntry::assertBalancedBatch($this->entries);
    }

    /**
     * Factory: builds standard balanced double-entry accounting records for verified order payments.
     *
     * Invariant Proof:
     *   Total Debit  = GrandTotal + DiscountAmount = (Subtotal + Tax + Shipping - Discount) + Discount = Subtotal + Tax + Shipping
     *   Total Credit = Subtotal + Tax + Shipping
     *   Total Debit - Total Credit = 0.00
     */
    public static function forOrderPayment(
        int $shopId,
        int $orderId,
        string $orderUuid,
        float $subtotal,
        float $taxAmount,
        float $shippingCost,
        float $discountAmount,
        float $grandTotal,
        ?int $verifierId = null,
        ?DateTimeImmutable $date = null
    ): self {
        if (
            $subtotal < -0.0001
            || $taxAmount < -0.0001
            || $shippingCost < -0.0001
            || $discountAmount < -0.0001
            || $grandTotal < -0.0001
        ) {
            throw new InvalidLedgerEntryException('مبالغ مالی سفارش در سند حسابداری نمی‌توانند منفی باشند.');
        }

        $tx = new self();
        $date = $date ?? new DateTimeImmutable();

        // 1. Debit Cash/Bank for total payment received (only if > 0)
        if ($grandTotal > 0.0001) {
            $tx->addEntry(new DoubleEntryLedgerEntry(
                shopId: $shopId,
                account: LedgerAccount::CashBank,
                entryType: LedgerEntryType::PaymentReceived,
                debit: $grandTotal,
                credit: 0.0,
                description: "واریز وجه سفارش #{$orderUuid}",
                orderId: $orderId,
                createdById: $verifierId,
                entryDate: $date
            ));
        }

        // 2. Debit Discounts (if any)
        if ($discountAmount > 0.0001) {
            $tx->addEntry(new DoubleEntryLedgerEntry(
                shopId: $shopId,
                account: LedgerAccount::Discounts,
                entryType: LedgerEntryType::DiscountExpense,
                debit: $discountAmount,
                credit: 0.0,
                description: "تخفیف اعطایی به مشتری در سفارش #{$orderUuid}",
                orderId: $orderId,
                createdById: $verifierId,
                entryDate: $date
            ));
        }

        // 3. Credit Sales Revenue (only if > 0)
        if ($subtotal > 0.0001) {
            $tx->addEntry(new DoubleEntryLedgerEntry(
                shopId: $shopId,
                account: LedgerAccount::SalesRevenue,
                entryType: LedgerEntryType::SaleRevenue,
                debit: 0.0,
                credit: $subtotal,
                description: "درآمد حاصل از فروش کالا - سفارش #{$orderUuid}",
                orderId: $orderId,
                createdById: $verifierId,
                entryDate: $date
            ));
        }

        // 4. Credit Tax Payable (if any)
        if ($taxAmount > 0.0001) {
            $tx->addEntry(new DoubleEntryLedgerEntry(
                shopId: $shopId,
                account: LedgerAccount::TaxPayable,
                entryType: LedgerEntryType::TaxPayable,
                debit: 0.0,
                credit: $taxAmount,
                description: "مالیات بر ارزش افزوده سفارش #{$orderUuid}",
                orderId: $orderId,
                createdById: $verifierId,
                entryDate: $date
            ));
        }

        // 5. Credit Shipping Revenue (if any)
        if ($shippingCost > 0.0001) {
            $tx->addEntry(new DoubleEntryLedgerEntry(
                shopId: $shopId,
                account: LedgerAccount::ShippingRevenue,
                entryType: LedgerEntryType::ShippingRevenue,
                debit: 0.0,
                credit: $shippingCost,
                description: "درآمد حمل و نقل و پست پیشتاز - سفارش #{$orderUuid}",
                orderId: $orderId,
                createdById: $verifierId,
                entryDate: $date
            ));
        }

        // Enforce invariant immediately
        $tx->assertBalanced();

        return $tx;
    }
}
