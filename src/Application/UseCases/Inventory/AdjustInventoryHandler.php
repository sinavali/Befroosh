<?php

declare(strict_types=1);

namespace App\Application\UseCases\Inventory;

use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Repositories\InventoryTransactionRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;

/**
 * Use-Case: Adjusts product stock inventory (inward, plus, minus, write-off)
 * and records transactional audit log.
 */
final readonly class AdjustInventoryHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private InventoryTransactionRepositoryInterface $inventoryTransactionRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(
        int $productId,
        float $delta,
        string $type = InventoryTransaction::TYPE_ADJUSTMENT_PLUS,
        ?string $notes = null,
        ?int $userId = null
    ): float {
        return $this->transactionManager->transactional(function () use ($productId, $delta, $type, $notes, $userId) {
            $product = $this->productRepository->findById($productId);
            if ($product === null) {
                throw EntityNotFoundException::forEntity('Product', $productId);
            }

            $product->adjustStock($delta, $type);

            $this->productRepository->updateStock(
                $product->getId() ?? $productId,
                $product->getStockQuantity()->value,
                $product->getReservedQuantity()->value
            );

            $tx = new InventoryTransaction(
                shopId: $product->getShopId(),
                productId: $productId,
                type: $type,
                quantity: abs($delta),
                unitCost: $product->getCostPrice(),
                notes: $notes,
                createdById: $userId
            );

            $this->inventoryTransactionRepository->save($tx);

            $events = $product->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return $product->getStockQuantity()->value;
        });
    }
}
