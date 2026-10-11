<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Repositories\InventoryTransactionRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of InventoryTransactionRepositoryInterface.
 */
final class SqliteInventoryTransactionRepository implements InventoryTransactionRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?InventoryTransaction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<InventoryTransaction>
     */
    public function findByProductId(int $productId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_transactions WHERE product_id = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $productId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<InventoryTransaction>
     */
    public function findByShopId(int $shopId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_transactions WHERE shop_id = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $shopId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<InventoryTransaction>
     */
    public function findByReference(string $referenceType, int $referenceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_transactions WHERE reference_type = ? AND reference_id = ? ORDER BY id ASC');
        $stmt->execute([$referenceType, $referenceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(InventoryTransaction $transaction): InventoryTransaction
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_transactions (
                shop_id, product_id, type, quantity, unit_cost,
                reference_type, reference_id, notes, created_by_id, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $transaction->getShopId(),
            $transaction->getProductId(),
            $transaction->getType(),
            $transaction->getQuantity(),
            $transaction->getUnitCost(),
            $transaction->getReferenceType(),
            $transaction->getReferenceId(),
            $transaction->getNotes(),
            $transaction->getCreatedById(),
            $now
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $transaction->setId($id);

        return $transaction;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): InventoryTransaction
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new InventoryTransaction(
            shopId: (int)$row['shop_id'],
            productId: (int)$row['product_id'],
            type: (string)$row['type'],
            quantity: (float)$row['quantity'],
            unitCost: isset($row['unit_cost']) ? (float)$row['unit_cost'] : null,
            referenceType: isset($row['reference_type']) ? (string)$row['reference_type'] : null,
            referenceId: isset($row['reference_id']) ? (int)$row['reference_id'] : null,
            notes: isset($row['notes']) ? (string)$row['notes'] : null,
            createdById: isset($row['created_by_id']) ? (int)$row['created_by_id'] : null,
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
