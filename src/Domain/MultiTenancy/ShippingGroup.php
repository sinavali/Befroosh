<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * Shipping Group entity for shared logistics pooling across branches.
 */
final class ShippingGroup extends Entity
{
    public function __construct(
        private int $businessId,
        private string $name,
        private float $shippingCost = 250000.0,
        private float $freeShippingThreshold = 2000000.0,
        private ?string $centralHubAddress = null,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        if ($this->shippingCost < 0.0) {
            throw new \InvalidArgumentException('هزینه ارسال گروه لجستیک نمی‌تواند منفی باشد.');
        }
        if ($this->freeShippingThreshold < 0.0) {
            throw new \InvalidArgumentException('حداقل مبلغ ارسال رایگان نمی‌تواند منفی باشد.');
        }
    }

    public function getBusinessId(): int
    {
        return $this->businessId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShippingCost(): float
    {
        return $this->shippingCost;
    }

    public function getFreeShippingThreshold(): float
    {
        return $this->freeShippingThreshold;
    }

    public function getCentralHubAddress(): ?string
    {
        return $this->centralHubAddress;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function calculateShipping(float $subtotal): float
    {
        if ($this->freeShippingThreshold > 0.0 && $subtotal >= $this->freeShippingThreshold) {
            return 0.0;
        }

        return $this->shippingCost;
    }
}
