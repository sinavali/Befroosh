<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Catalog\Category;
use App\Domain\Repositories\CategoryRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of CategoryRepositoryInterface.
 */
final class SqliteCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByShopAndSlug(int $shopId, string $slug): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE shop_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$shopId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Category>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM categories WHERE shop_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$shopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Category>
     */
    public function findRootCategories(int $shopId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE shop_id = ? AND (parent_id IS NULL OR parent_id = 0) AND active = 1 ORDER BY sort_order ASC, name ASC');
        $stmt->execute([$shopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Category>
     */
    public function findChildren(int $parentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE parent_id = ? AND active = 1 ORDER BY sort_order ASC, name ASC');
        $stmt->execute([$parentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(Category $category): Category
    {
        $now = date('Y-m-d H:i:s');

        if ($category->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO categories (
                    shop_id, name, slug, parent_id, sort_order, active, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $category->getShopId(),
                $category->getName(),
                $category->getSlug(),
                $category->getParentId(),
                $category->getSortOrder(),
                $category->isActive() ? 1 : 0,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $category->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE categories SET
                    shop_id = ?, name = ?, slug = ?, parent_id = ?, sort_order = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $category->getShopId(),
                $category->getName(),
                $category->getSlug(),
                $category->getParentId(),
                $category->getSortOrder(),
                $category->isActive() ? 1 : 0,
                $category->getId()
            ]);
        }

        return $category;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Category
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new Category(
            shopId: (int)$row['shop_id'],
            name: (string)$row['name'],
            slug: (string)$row['slug'],
            parentId: isset($row['parent_id']) && (int)$row['parent_id'] > 0 ? (int)$row['parent_id'] : null,
            sortOrder: (int)($row['sort_order'] ?? 0),
            active: (bool)($row['active'] ?? 1),
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
