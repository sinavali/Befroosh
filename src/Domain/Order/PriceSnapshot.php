<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\ValueObject;

/**
 * Immutable financial snapshot of order totals.
 */
final readonly class PriceSnapshot implements ValueObject
{
    public float $estimatedTotal;
    public float $finalTotal;

    public function __construct(
        public float $subtotal,
        public float $shippingCost = 0.0,
        public float $taxAmount = 0.0,
        public float $discountAmount = 0.0,
        ?float $estimatedTotal = null,
        ?float $finalTotal = null
    ) {
        if ($this->subtotal < 0.0) {
            throw new \InvalidArgumentException('مجموع اقلام نمی‌تواند منفی باشد.');
        }
        if ($this->shippingCost < 0.0) {
            throw new \InvalidArgumentException('هزینه ارسال نمی‌تواند منفی باشد.');
        }
        if ($this->taxAmount < 0.0) {
            throw new \InvalidArgumentException('مبلغ مالیات نمی‌تواند منفی باشد.');
        }
        if ($this->discountAmount < 0.0) {
            throw new \InvalidArgumentException('مبلغ تخفیف نمی‌تواند منفی باشد.');
        }

        $calculated = max(0.0, $this->subtotal + $this->shippingCost + $this->taxAmount - $this->discountAmount);
        $this->estimatedTotal = $estimatedTotal ?? $calculated;
        $this->finalTotal = $finalTotal ?? $calculated;
    }

    public static function create(
        float $subtotal,
        float $shippingCost = 0.0,
        float $taxAmount = 0.0,
        float $discountAmount = 0.0
    ): self {
        return new self($subtotal, $shippingCost, $taxAmount, $discountAmount);
    }

    public function isFree(): bool
    {
        return $this->finalTotal <= 0.0;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && abs($this->subtotal - $other->subtotal) < 0.001
            && abs($this->shippingCost - $other->shippingCost) < 0.001
            && abs($this->taxAmount - $other->taxAmount) < 0.001
            && abs($this->discountAmount - $other->discountAmount) < 0.001
            && abs($this->finalTotal - $other->finalTotal) < 0.001;
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shippingCost,
            'tax_amount' => $this->taxAmount,
            'discount_amount' => $this->discountAmount,
            'estimated_total' => $this->estimatedTotal,
            'final_total' => $this->finalTotal,
        ];
    }
}
