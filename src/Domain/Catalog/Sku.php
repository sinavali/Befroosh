<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\ValueObject;
use App\Domain\Exceptions\InvalidSkuException;

/**
 * Value Object representing a validated Stock Keeping Unit (SKU).
 */
final readonly class Sku implements ValueObject
{
    public string $value;

    public function __construct(string $rawSku)
    {
        $normalized = strtoupper(trim($rawSku));

        if (!preg_match('/^[A-Za-z0-9\-_]{2,50}$/', $normalized)) {
            throw new InvalidSkuException(
                sprintf('شناسه کالا (SKU) نامعتبر است: «%s». باید بین ۲ تا ۵۰ کاراکتر و فقط شامل حروف انگلیسی، اعداد، خط فاصله یا زیرخط باشد.', $rawSku)
            );
        }

        $this->value = $normalized;
    }

    public static function from(string $rawSku): self
    {
        return new self($rawSku);
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toArray(): string
    {
        return $this->value;
    }
}
