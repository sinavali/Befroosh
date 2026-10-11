<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Payment\DoubleEntryLedgerEntry;
use DateTimeImmutable;

interface LedgerRepositoryInterface
{
    public function findById(int $id): ?DoubleEntryLedgerEntry;

    /**
     * @return array<DoubleEntryLedgerEntry>
     */
    public function findByOrderId(int $orderId): array;

    /**
     * @return array<DoubleEntryLedgerEntry>
     */
    public function findByShopId(
        int $shopId,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        int $limit = 100,
        int $offset = 0
    ): array;

    public function save(DoubleEntryLedgerEntry $entry): DoubleEntryLedgerEntry;

    /**
     * Atomically saves a balanced set of entries.
     * Implementations MUST ensure transaction safety and verify sum(debit) === sum(credit).
     *
     * @param array<DoubleEntryLedgerEntry> $entries
     */
    public function saveBatch(array $entries): void;

    /**
     * Atomically saves a balanced LedgerTransaction aggregate.
     */
    public function saveTransaction(\App\Domain\Payment\LedgerTransaction $transaction): void;

    /**
     * Returns net balance for an account head (Debit sum - Credit sum).
     */
    public function calculateAccountBalance(int $shopId, string $account): float;

    /**
     * Returns trial balance summary: list of accounts with respective debit and credit sums.
     *
     * @return array<string, array{debit_sum: float, credit_sum: float, balance: float}>
     */
    public function getTrialBalance(int $shopId, ?DateTimeImmutable $asOfDate = null): array;
}
