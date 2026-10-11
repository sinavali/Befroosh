<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\UserAssignment;

interface UserAssignmentRepositoryInterface
{
    public function findById(int $id): ?UserAssignment;

    public function findByUserAndBranch(int $userId, int $branchId): ?UserAssignment;

    /**
     * @return array<UserAssignment>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return array<UserAssignment>
     */
    public function findByBranchId(int $branchId): array;

    /**
     * @return array<UserAssignment>
     */
    public function findByUserId(int $userId): array;

    /**
     * Counts distinct managers assigned to a business to enforce plan quotas.
     */
    public function countManagersByBusinessId(int $businessId): int;

    public function save(UserAssignment $assignment): UserAssignment;

    public function delete(int $id): void;

    public function deleteByUserAndBranch(int $userId, int $branchId): void;
}
