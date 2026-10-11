<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * OrderItem entity representing a line item with frozen pricing and product snapshots.
 */
final class OrderItem extends Entity
{
    public function __construct(
        private ?int $orderId,
        private ?int $productId,
        private string $productTitle,
        private int $unitPrice,
        private float $quantity,
        private float $unitCostPrice = 0.0,
        private ?string $productSku = null,
        private float $taxAmount = 0.0,
        private ?float $lineTotal = null,
        private string $unit = 'عدد',
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->lineTotal = $lineTotal ?? round($this->unitPrice * $this->quantity, 2);
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if ($this->quantity <= 0.0) {
            throw new \InvalidArgumentException('تعداد سفارش باید بزرگتر از صفر باشد.');
        }

        if ($this->unitPrice < 0) {
            throw new \InvalidArgumentException('قیمت واحد کالا نمی‌تواند منفی باشد.');
        }

        if ($this->unitCostPrice < 0.0) {
            throw new \InvalidArgumentException('قیمت خرید واحد کالا نمی‌تواند منفی باشد.');
        }
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function getProductTitle(): string
    {
        return $this->productTitle;
    }

    public function getProductSku(): ?string
    {
        return $this->productSku;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getUnitCostPrice(): float
    {
        return $this->unitCostPrice;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    public function getLineTotal(): float
    {
        return $this->lineTotal ?? round($this->unitPrice * $this->quantity, 2);
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
