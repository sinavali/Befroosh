<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\PurchaseLimitExceededException;

/**
 * Service/Validator enforcing per-order and rolling 30-day cumulative purchase limits.
 */
final readonly class PurchaseLimits
{
    public function __construct(
        public float $maxPerOrder = 0.0,
        public float $maxPerMonth = 0.0,
        public float $minOrderQty = 1.0
    ) {}

    public function hasPerOrderLimit(): bool
    {
        return $this->maxPerOrder > 0.0;
    }

    public function hasMonthlyLimit(): bool
    {
        return $this->maxPerMonth > 0.0;
    }

    /**
     * Validates requested quantity against stock, order cap, and 30-day cumulative purchase limit.
     *
     * @throws InsufficientStockException
     * @throws PurchaseLimitExceededException
     */
    public function validate(
        int $productId,
        string $productTitle,
        float $requestedQty,
        float $availableStock,
        float $past30DaysPurchased = 0.0
    ): void {
        if ($requestedQty < $this->minOrderQty) {
            throw new \InvalidArgumentException(
                sprintf('حداقل تعداد مجاز برای سفارش این کالا %s عدد است.', $this->minOrderQty)
            );
        }

        if ($requestedQty > $availableStock) {
            throw InsufficientStockException::forProduct(
                $productId,
                $productTitle,
                $availableStock,
                $requestedQty
            );
        }

        if ($this->hasPerOrderLimit() && $requestedQty > $this->maxPerOrder) {
            throw PurchaseLimitExceededException::perOrder(
                $productId,
                $productTitle,
                $this->maxPerOrder,
                $requestedQty
            );
        }

        if ($this->hasMonthlyLimit() && ($past30DaysPurchased + $requestedQty) > $this->maxPerMonth) {
            throw PurchaseLimitExceededException::perMonth(
                $productId,
                $productTitle,
                $this->maxPerMonth,
                $past30DaysPurchased,
                $requestedQty
            );
        }
    }
}
