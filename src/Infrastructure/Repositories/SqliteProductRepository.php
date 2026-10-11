<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Catalog\Product;
use App\Domain\Repositories\ProductRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of ProductRepositoryInterface.
 */
final class SqliteProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE sku = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByShopAndSlug(int $shopId, string $slug): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE shop_id = ? AND slug = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$shopId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Product>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM products WHERE shop_id = ? AND deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $shopId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Storefront product query with catalog_mode resolution.
     *
     * @return array<Product>
     */
    public function findForStorefront(
        int $shopId,
        ?int $branchId = null,
        string $catalogMode = 'both',
        int $limit = 50,
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM products WHERE shop_id = ? AND active = 1 AND deleted_at IS NULL';
        $params = [$shopId];

        if ($branchId !== null) {
            if ($catalogMode === 'branch_only') {
                $sql .= ' AND branch_id = ?';
                $params[] = $branchId;
            } elseif ($catalogMode === 'business_only') {
                $sql .= ' AND (branch_id IS NULL OR branch_id = 0)';
            } else { // 'both'
                $sql .= ' AND (branch_id = ? OR branch_id IS NULL OR branch_id = 0)';
                $params[] = $branchId;
            }
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

    /**
     * @return array<Product>
     */
    public function findByCategory(int $shopId, int $categoryId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM products WHERE shop_id = ? AND category_id = ? AND deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$shopId, $categoryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countActiveByShopId(int $shopId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE shop_id = ? AND active = 1 AND deleted_at IS NULL');
        $stmt->execute([$shopId]);

        return (int)$stmt->fetchColumn();
    }

    public function updateStock(int $productId, float $newStockQuantity, float $newReservedQuantity): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET stock_quantity = ?, reserved_quantity = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$newStockQuantity, $newReservedQuantity, date('Y-m-d H:i:s'), $productId]);
    }

    public function deactivateBranchSpecificProducts(int $branchId): int
    {
        $stmt = $this->pdo->prepare('UPDATE products SET active = 0, updated_at = ? WHERE branch_id = ? AND active = 1');
        $stmt->execute([date('Y-m-d H:i:s'), $branchId]);

        return $stmt->rowCount();
    }

    public function existsBySku(string $sku, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id != ? AND deleted_at IS NULL');
            $stmt->execute([$sku, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND deleted_at IS NULL');
            $stmt->execute([$sku]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    public function save(Product $product): Product
    {
        $now = date('Y-m-d H:i:s');
        $skuValue = $product->getSku()?->value;

        if ($product->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO products (
                    shop_id, title, price, cost_price, stock_quantity, reserved_quantity,
                    sku, branch_id, category_id, slug, description, min_stock_alert, min_order_qty,
                    barcode, unit, weight_grams, tax_rate, max_per_order, max_per_month,
                    is_business_product, image_path, active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $product->getShopId(),
                $product->getTitle(),
                $product->getPrice(),
                $product->getCostPrice(),
                $product->getStockQuantity()->value,
                $product->getReservedQuantity()->value,
                $skuValue,
                $product->getBranchId(),
                $product->getCategoryId(),
                $product->getSlug(),
                $product->getDescription(),
                $product->getMinStockAlert(),
                $product->getMinOrderQty(),
                $product->getBarcode(),
                $product->getUnit(),
                $product->getWeightGrams(),
                $product->getTaxRate(),
                $product->getMaxPerOrder(),
                $product->getMaxPerMonth(),
                $product->isBusinessProduct() ? 1 : 0,
                $product->getImagePath(),
                $product->isActive() ? 1 : 0,
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $product->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE products SET
                    shop_id = ?, title = ?, price = ?, cost_price = ?, stock_quantity = ?, reserved_quantity = ?,
                    sku = ?, branch_id = ?, category_id = ?, slug = ?, description = ?, min_stock_alert = ?, min_order_qty = ?,
                    barcode = ?, unit = ?, weight_grams = ?, tax_rate = ?, max_per_order = ?, max_per_month = ?,
                    is_business_product = ?, image_path = ?, active = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $product->getShopId(),
                $product->getTitle(),
                $product->getPrice(),
                $product->getCostPrice(),
                $product->getStockQuantity()->value,
                $product->getReservedQuantity()->value,
                $skuValue,
                $product->getBranchId(),
                $product->getCategoryId(),
                $product->getSlug(),
                $product->getDescription(),
                $product->getMinStockAlert(),
                $product->getMinOrderQty(),
                $product->getBarcode(),
                $product->getUnit(),
                $product->getWeightGrams(),
                $product->getTaxRate(),
                $product->getMaxPerOrder(),
                $product->getMaxPerMonth(),
                $product->isBusinessProduct() ? 1 : 0,
                $product->getImagePath(),
                $product->isActive() ? 1 : 0,
                $now,
                $product->getId()
            ]);
        }

        return $product;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET deleted_at = ?, updated_at = ? WHERE id = ?');
        $now = date('Y-m-d H:i:s');
        $stmt->execute([$now, $now, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Product
    {
        $deletedAt = !empty($row['deleted_at']) ? new DateTimeImmutable((string)$row['deleted_at']) : null;
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
        $updatedAt = !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null;

        return new Product(
            shopId: (int)$row['shop_id'],
            title: (string)$row['title'],
            price: (int)$row['price'],
            stockQuantity: (float)($row['stock_quantity'] ?? 0.0),
            reservedQuantity: (float)($row['reserved_quantity'] ?? 0.0),
            costPrice: (float)($row['cost_price'] ?? 0.0),
            sku: isset($row['sku']) ? (string)$row['sku'] : null,
            branchId: isset($row['branch_id']) ? (int)$row['branch_id'] : null,
            categoryId: isset($row['category_id']) ? (int)$row['category_id'] : null,
            slug: isset($row['slug']) ? (string)$row['slug'] : null,
            description: isset($row['description']) ? (string)$row['description'] : null,
            minStockAlert: (float)($row['min_stock_alert'] ?? 5.0),
            minOrderQty: (float)($row['min_order_qty'] ?? 1.0),
            barcode: isset($row['barcode']) ? (string)$row['barcode'] : null,
            unit: (string)($row['unit'] ?? 'عدد'),
            weightGrams: (int)($row['weight_grams'] ?? 0),
            taxRate: (float)($row['tax_rate'] ?? 0.0),
            maxPerOrder: (float)($row['max_per_order'] ?? 0.0),
            maxPerMonth: (float)($row['max_per_month'] ?? 0.0),
            isBusinessProduct: (bool)($row['is_business_product'] ?? 1),
            imagePath: isset($row['image_path']) ? (string)$row['image_path'] : null,
            active: (bool)($row['active'] ?? 1),
            deletedAt: $deletedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            id: (int)$row['id']
        );
    }
}
