<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\Shop;

interface ShopRepositoryInterface
{
    public function findById(int $id): ?Shop;

    public function findBySlug(string $slug): ?Shop;

    /**
     * @return array<Shop>
     */
    public function findByOwnerId(int $ownerId): array;

    /**
     * @return array<Shop>
     */
    public function findAllActive(int $limit = 100, int $offset = 0): array;

    public function existsBySlug(string $slug, ?int $excludeId = null): bool;

    public function countAll(): int;

    public function save(Shop $shop): Shop;

    public function delete(int $id): void;
}
