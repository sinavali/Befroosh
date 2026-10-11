<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * CartItem entity for multi-tenant cart persistence.
 */
final class CartItem extends Entity
{
    public function __construct(
        private int $userId,
        private int $shopId,
        private int $productId,
        private float $quantity = 1.0,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        if ($this->quantity <= 0.0) {
            throw new \InvalidArgumentException('تعداد کالای سبد خرید باید بزرگتر از صفر باشد.');
        }
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateQuantity(float $newQuantity): void
    {
        if ($newQuantity <= 0.0) {
            throw new \InvalidArgumentException('تعداد کالای سبد خرید باید بزرگتر از صفر باشد.');
        }
        $this->quantity = $newQuantity;
        $this->updatedAt = new DateTimeImmutable();
    }
}
