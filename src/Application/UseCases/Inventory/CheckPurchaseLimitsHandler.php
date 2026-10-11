<?php

declare(strict_types=1);

namespace App\Application\UseCases\Inventory;

use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;

/**
 * Use-Case: Validates whether a customer can purchase a specified quantity of a product
 * against stock availability, per-order limits, and rolling 30-day monthly caps.
 */
final readonly class CheckPurchaseLimitsHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * @return array{ok: bool, error: ?string, max_allowed: float}
     */
    public function handle(int $customerId, int $productId, float $quantity): array
    {
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            throw EntityNotFoundException::forEntity('Product', $productId);
        }

        $past30Days = $this->orderRepository->calculate30DayPurchasedQuantity($customerId, $productId);

        try {
            $product->assertCanPurchase($quantity, $past30Days);
            return [
                'ok' => true,
                'error' => null,
                'max_allowed' => $product->getAvailableStock(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'max_allowed' => $product->getAvailableStock(),
            ];
        }
    }
}
