<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Catalog\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySku(string $sku): ?Product;

    public function findByShopAndSlug(int $shopId, string $slug): ?Product;

    /**
     * @return array<Product>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true, int $limit = 50, int $offset = 0): array;

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
    ): array;

    /**
     * @return array<Product>
     */
    public function findByCategory(int $shopId, int $categoryId, bool $activeOnly = true): array;

    /**
     * Counts active products belonging to shop to enforce plan quotas.
     */
    public function countActiveByShopId(int $shopId): int;

    /**
     * Atomic update of stock quantity and reserved quantity.
     */
    public function updateStock(int $productId, float $newStockQuantity, float $newReservedQuantity): void;

    /**
     * Deactivates branch-specific products when catalog mode switches to business_only.
     *
     * @return int Number of products deactivated.
     */
    public function deactivateBranchSpecificProducts(int $branchId): int;

    public function existsBySku(string $sku, ?int $excludeId = null): bool;

    public function save(Product $product): Product;

    /**
     * Soft-deletes product by setting deleted_at.
     */
    public function delete(int $id): void;
}
