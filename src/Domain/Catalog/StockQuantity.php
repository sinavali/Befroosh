<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\ValueObject;
use App\Domain\Exceptions\NegativeInventoryException;

/**
 * Value Object representing a non-negative quantity of inventory.
 */
final readonly class StockQuantity implements ValueObject
{
    public function __construct(public float $value)
    {
        if ($this->value < 0.0) {
            throw new NegativeInventoryException(
                sprintf('مقدار موجودی کالا نمی‌تواند منفی باشد (%s).', $this->value)
            );
        }
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function from(float $qty): self
    {
        return new self($qty);
    }

    public function add(float|self $qty): self
    {
        $amount = $qty instanceof self ? $qty->value : $qty;
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('مقدار افزایشی موجودی نمی‌تواند منفی باشد.');
        }

        return new self($this->value + $amount);
    }

    /**
     * Strictly subtracts quantity, throwing NegativeInventoryException if insufficient.
     */
    public function subtract(float|self $qty): self
    {
        $amount = $qty instanceof self ? $qty->value : $qty;
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('مقدار کاهشی موجودی نمی‌تواند منفی باشد.');
        }

        if ($this->value - $amount < 0.0) {
            throw new NegativeInventoryException(
                sprintf('کسر مقدار %s از موجودی %s منجر به موجودی منفی می‌شود.', $amount, $this->value)
            );
        }

        return new self($this->value - $amount);
    }

    /**
     * Safely subtracts quantity with non-negative clamping: max(0.0, current - amount).
     */
    public function clampSubtract(float|self $qty): self
    {
        $amount = $qty instanceof self ? $qty->value : $qty;
        return new self(max(0.0, $this->value - $amount));
    }

    public function hasSufficient(float $needed): bool
    {
        return $this->value >= $needed;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && abs($this->value - $other->value) < 0.0001;
    }

    public function toArray(): float
    {
        return $this->value;
    }
}
