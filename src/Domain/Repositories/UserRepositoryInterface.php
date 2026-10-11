<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function findByPhone(string $phone): ?User;

    /**
     * @return array<User>
     */
    public function findByShopId(int $shopId): array;

    /**
     * @param array<string> $roles
     * @return array<User>
     */
    public function findByShopIdAndRoles(int $shopId, array $roles): array;

    public function existsByUsername(string $username, ?int $excludeId = null): bool;

    public function existsByPhone(string $phone, ?int $excludeId = null): bool;

    public function countByShopId(int $shopId): int;

    public function save(User $user): User;

    public function delete(int $id): void;
}
