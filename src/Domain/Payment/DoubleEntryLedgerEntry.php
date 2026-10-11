<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Common\Entity;
use App\Domain\Exceptions\InvalidLedgerEntryException;
use App\Domain\Exceptions\UnbalancedLedgerException;
use DateTimeImmutable;

/**
 * Double-Entry General Ledger record representing an atomic debit or credit leg.
 */
final class DoubleEntryLedgerEntry extends Entity
{
    private string $entryTypeString;

    public function __construct(
        private int $shopId,
        private LedgerAccount $account,
        LedgerEntryType|string $entryType,
        private float $debit,
        private float $credit,
        private string $description,
        private ?int $orderId = null,
        private ?int $createdById = null,
        private ?DateTimeImmutable $entryDate = null,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->entryTypeString = $entryType instanceof LedgerEntryType ? $entryType->value : $entryType;
        $this->entryDate = $entryDate ?? new DateTimeImmutable();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (is_nan($this->debit) || is_nan($this->credit) || is_infinite($this->debit) || is_infinite($this->credit)) {
            throw new InvalidLedgerEntryException('مبالغ بدهکار و بستانکار باید اعداد متناهی و معتبر باشند.');
        }

        if ($this->debit < 0.0 || $this->credit < 0.0) {
            throw new InvalidLedgerEntryException('مبالغ بدهکار و بستانکار در سند حسابداری نمی‌توانند منفی باشند.');
        }

        if ($this->debit > 0.0 && $this->credit > 0.0) {
            throw new InvalidLedgerEntryException('یک ردیف سند حسابداری نمی‌تواند همزمان دارای بدهکار و بستانکار باشد.');
        }

        if (abs($this->debit) < 0.0001 && abs($this->credit) < 0.0001) {
            throw new InvalidLedgerEntryException('ردیف سند حسابداری باید دارای حداقل یکی از مبالغ بدهکار یا بستانکار مثبت باشد.');
        }
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getAccount(): LedgerAccount
    {
        return $this->account;
    }

    public function getEntryType(): string
    {
        return $this->entryTypeString;
    }

    public function getDebit(): float
    {
        return $this->debit;
    }

    public function getCredit(): float
    {
        return $this->credit;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getCreatedById(): ?int
    {
        return $this->createdById;
    }

    public function getEntryDate(): DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Asserts that a collection of entries satisfies the double-entry accounting equation:
     * sum(Debit) == sum(Credit) with absolute difference <= 0.0001.
     *
     * @param array<DoubleEntryLedgerEntry> $entries
     * @throws UnbalancedLedgerException
     */
    public static function assertBalanced(array $entries): void
    {
        self::assertBalancedBatch($entries);
    }

    /**
     * @param array<DoubleEntryLedgerEntry> $entries
     * @throws UnbalancedLedgerException
     */
    public static function assertBalancedBatch(array $entries): void
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entries as $entry) {
            $d = $entry->getDebit();
            $c = $entry->getCredit();
            if (is_nan($d) || is_nan($c) || is_infinite($d) || is_infinite($c)) {
                throw new UnbalancedLedgerException('سند حسابداری حاوی مبالغ نامعتبر یا نامتناهی است.');
            }
            $totalDebit += $d;
            $totalCredit += $c;
        }

        if (is_nan($totalDebit) || is_nan($totalCredit) || is_infinite($totalDebit) || is_infinite($totalCredit)) {
            throw new UnbalancedLedgerException('سند حسابداری حاوی مبالغ نامعتبر یا نامتناهی است.');
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $diff = round(abs($totalDebit - $totalCredit), 2);

        if ($diff > 0.0001) {
            throw UnbalancedLedgerException::fromSums($totalDebit, $totalCredit, $diff);
        }
    }
}
