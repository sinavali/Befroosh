<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Catalog\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    public function findByShopAndSlug(int $shopId, string $slug): ?Category;

    /**
     * @return array<Category>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true): array;

    /**
     * @return array<Category>
     */
    public function findRootCategories(int $shopId): array;

    /**
     * @return array<Category>
     */
    public function findChildren(int $parentId): array;

    public function save(Category $category): Category;

    public function delete(int $id): void;
}
