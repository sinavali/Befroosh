<?php

declare(strict_types=1);

namespace App\Application\Events\Subscribers;

use App\Domain\Catalog\InventoryTransaction;
use App\Domain\Events\OrderCanceledEvent;
use App\Domain\Events\ReservationExpiredEvent;
use App\Domain\Repositories\InventoryTransactionRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;

/**
 * Domain Event Subscriber: Restores product inventory when an order reservation expires
 * or when an order is canceled.
 */
final readonly class ReleaseStockOnOrderCanceled
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private InventoryTransactionRepositoryInterface $inventoryTransactionRepository
    ) {}

    public function onReservationExpired(ReservationExpiredEvent $event): void
    {
        $this->restoreItems($event->shopId, $event->expiredItems, 'انقضای رزرو کالا سفارش #' . $event->orderUuid, $event->orderId);
    }

    public function onOrderCanceled(OrderCanceledEvent $event): void
    {
        $this->restoreItems($event->shopId, $event->restoredItems, 'لغو سفارش #' . $event->orderUuid . ': ' . $event->cancellationReason, $event->orderId);
    }

    /**
     * @param array<array{product_id: int, quantity: float, unit_cost?: float}> $items
     */
    private function restoreItems(int $shopId, array $items, string $reason, int $orderId): void
    {
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $product = $this->productRepository->findById($productId);
            if ($product === null) {
                continue;
            }

            // Release reserved stock on product
            $product->releaseReservedStock($qty);
            $this->productRepository->updateStock(
                $product->getId() ?? $productId,
                $product->getStockQuantity()->value,
                $product->getReservedQuantity()->value
            );

            // Record inventory movement
            $tx = new InventoryTransaction(
                shopId: $shopId,
                productId: $productId,
                type: InventoryTransaction::TYPE_RETURN_IN,
                quantity: $qty,
                unitCost: $item['unit_cost'] ?? $product->getCostPrice(),
                referenceType: 'order',
                referenceId: $orderId,
                notes: $reason
            );

            $this->inventoryTransactionRepository->save($tx);
        }
    }
}
