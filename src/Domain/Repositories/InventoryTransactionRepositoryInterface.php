<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Catalog\InventoryTransaction;

interface InventoryTransactionRepositoryInterface
{
    public function findById(int $id): ?InventoryTransaction;

    /**
     * @return array<InventoryTransaction>
     */
    public function findByProductId(int $productId, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<InventoryTransaction>
     */
    public function findByShopId(int $shopId, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<InventoryTransaction>
     */
    public function findByReference(string $referenceType, int $referenceId): array;

    public function save(InventoryTransaction $transaction): InventoryTransaction;
}
