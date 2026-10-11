<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Catalog\CartItem;
use App\Domain\Repositories\CartRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of CartRepositoryInterface.
 */
final class SqliteCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?CartItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cart_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<CartItem>
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cart_items WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<CartItem>
     */
    public function findByUserAndShop(int $userId, int $shopId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cart_items WHERE user_id = ? AND shop_id = ? ORDER BY id DESC');
        $stmt->execute([$userId, $shopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findItem(int $userId, int $productId): ?CartItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1');
        $stmt->execute([$userId, $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function countItemsByUserId(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn();
    }

    public function save(CartItem $item): CartItem
    {
        $now = date('Y-m-d H:i:s');

        if ($item->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO cart_items (
                    user_id, shop_id, product_id, quantity, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $item->getUserId(),
                $item->getShopId(),
                $item->getProductId(),
                $item->getQuantity(),
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $item->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE cart_items SET
                    quantity = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $item->getQuantity(),
                $now,
                $item->getId()
            ]);
        }

        return $item;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteItem(int $userId, int $productId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
    }

    public function clearUserCartForShop(int $userId, int $shopId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND shop_id = ?');
        $stmt->execute([$userId, $shopId]);
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        $threshold = $now->modify('-7 days')->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE updated_at < ?');
        $stmt->execute([$threshold]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CartItem
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
        $updatedAt = !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null;

        return new CartItem(
            userId: (int)$row['user_id'],
            shopId: (int)$row['shop_id'],
            productId: (int)$row['product_id'],
            quantity: (float)$row['quantity'],
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            id: (int)$row['id']
        );
    }
}
