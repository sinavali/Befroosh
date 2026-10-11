<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Payment\DoubleEntryLedgerEntry;
use App\Domain\Payment\LedgerAccount;
use App\Domain\Payment\LedgerTransaction;
use App\Domain\Repositories\LedgerRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of LedgerRepositoryInterface.
 */
final class SqliteLedgerRepository implements LedgerRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?DoubleEntryLedgerEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting_ledger WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<DoubleEntryLedgerEntry>
     */
    public function findByOrderId(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting_ledger WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<DoubleEntryLedgerEntry>
     */
    public function findByShopId(
        int $shopId,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM accounting_ledger WHERE shop_id = ?';
        $params = [$shopId];

        if ($startDate !== null) {
            $sql .= ' AND entry_date >= ?';
            $params[] = $startDate->format('Y-m-d H:i:s');
        }

        if ($endDate !== null) {
            $sql .= ' AND entry_date <= ?';
            $params[] = $endDate->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

        $stmt = $this->pdo->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(DoubleEntryLedgerEntry $entry): DoubleEntryLedgerEntry
    {
        $now = date('Y-m-d H:i:s');
        $entryDate = $entry->getEntryDate()->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO accounting_ledger (
                shop_id, entry_date, entry_type, order_id, debit,
                credit, account, description, created_by_id, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $entry->getShopId(),
            $entryDate,
            $entry->getEntryType(),
            $entry->getOrderId(),
            $entry->getDebit(),
            $entry->getCredit(),
            $entry->getAccount()->value,
            $entry->getDescription(),
            $entry->getCreatedById(),
            $now
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $entry->setId($id);

        return $entry;
    }

    /**
     * @param array<DoubleEntryLedgerEntry> $entries
     */
    public function saveBatch(array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        // Verify balancing invariant before writing to database
        DoubleEntryLedgerEntry::assertBalancedBatch($entries);

        $needTransaction = !$this->pdo->inTransaction();
        if ($needTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($entries as $entry) {
                $this->save($entry);
            }
            if ($needTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($needTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveTransaction(LedgerTransaction $transaction): void
    {
        $transaction->assertBalanced();
        $this->saveBatch($transaction->getEntries());
    }

    public function calculateAccountBalance(int $shopId, string $account): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(debit) - SUM(credit), 0)
            FROM accounting_ledger
            WHERE shop_id = ? AND account = ?
        ");
        $stmt->execute([$shopId, $account]);

        return (float)$stmt->fetchColumn();
    }

    /**
     * @return array<string, array{debit_sum: float, credit_sum: float, balance: float}>
     */
    public function getTrialBalance(int $shopId, ?DateTimeImmutable $asOfDate = null): array
    {
        $sql = "
            SELECT account, SUM(debit) as total_debit, SUM(credit) as total_credit
            FROM accounting_ledger
            WHERE shop_id = ?
        ";
        $params = [$shopId];

        if ($asOfDate !== null) {
            $sql .= ' AND entry_date <= ?';
            $params[] = $asOfDate->format('Y-m-d H:i:s');
        }

        $sql .= ' GROUP BY account ORDER BY account ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $trialBalance = [];
        foreach ($rows as $row) {
            $acc = (string)$row['account'];
            $debit = (float)$row['total_debit'];
            $credit = (float)$row['total_credit'];
            $trialBalance[$acc] = [
                'debit_sum' => $debit,
                'credit_sum' => $credit,
                'balance' => round($debit - $credit, 2),
            ];
        }

        return $trialBalance;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DoubleEntryLedgerEntry
    {
        $account = LedgerAccount::from((string)$row['account']);
        $entryDate = !empty($row['entry_date']) ? new DateTimeImmutable((string)$row['entry_date']) : null;
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new DoubleEntryLedgerEntry(
            shopId: (int)$row['shop_id'],
            account: $account,
            entryType: (string)$row['entry_type'],
            debit: (float)$row['debit'],
            credit: (float)$row['credit'],
            description: (string)$row['description'],
            orderId: isset($row['order_id']) ? (int)$row['order_id'] : null,
            createdById: isset($row['created_by_id']) ? (int)$row['created_by_id'] : null,
            entryDate: $entryDate,
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
