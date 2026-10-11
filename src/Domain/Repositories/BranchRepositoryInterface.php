<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\Branch;

interface BranchRepositoryInterface
{
    public function findById(int $id): ?Branch;

    public function findByBusinessAndSlug(int $businessId, string $slug): ?Branch;

    /**
     * @return array<Branch>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return array<Branch>
     */
    public function findActiveByBusinessId(int $businessId): array;

    public function findMainBranch(int $businessId): ?Branch;

    /**
     * Counts active branches for a business to enforce plan quotas.
     */
    public function countActiveByBusinessId(int $businessId): int;

    public function existsBySlug(int $businessId, string $slug, ?int $excludeId = null): bool;

    public function save(Branch $branch): Branch;

    public function delete(int $id): void;
}
