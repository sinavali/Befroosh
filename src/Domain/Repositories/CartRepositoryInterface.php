<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Catalog\CartItem;
use DateTimeImmutable;

interface CartRepositoryInterface
{
    public function findById(int $id): ?CartItem;

    /**
     * @return array<CartItem>
     */
    public function findByUserId(int $userId): array;

    /**
     * @return array<CartItem>
     */
    public function findByUserAndShop(int $userId, int $shopId): array;

    public function findItem(int $userId, int $productId): ?CartItem;

    public function countItemsByUserId(int $userId): int;

    public function save(CartItem $item): CartItem;

    public function delete(int $id): void;

    public function deleteItem(int $userId, int $productId): void;

    public function clearUserCartForShop(int $userId, int $shopId): void;

    /**
     * Purges expired cart records (older than 7 days).
     *
     * @return int Count of purged items.
     */
    public function purgeExpired(DateTimeImmutable $now): int;
}
