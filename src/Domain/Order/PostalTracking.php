<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\ValueObject;
use App\Domain\Exceptions\PostalTrackingCodeInvalidException;

/**
 * Value Object validating 24-digit Iran Post tracking codes and building tracking URLs.
 */
final readonly class PostalTracking implements ValueObject
{
    public function __construct(
        public ?string $code,
        public string $shippingMethod = 'پست پیشتاز'
    ) {
        if ($this->code !== null && trim($this->code) !== '') {
            $trimmed = trim($this->code);
            // Iran Post standard tracking codes are 24 numeric digits
            if (!preg_match('/^\d{24}$/', $trimmed)) {
                throw PostalTrackingCodeInvalidException::invalidFormat($trimmed);
            }
        }
    }

    public static function empty(): self
    {
        return new self(null);
    }

    public static function from(?string $code, string $shippingMethod = 'پست پیشتاز'): self
    {
        if ($code === null || trim($code) === '') {
            return new self(null, $shippingMethod);
        }

        return new self(trim($code), $shippingMethod);
    }

    /**
     * Generates a syntactically valid 24-digit Iran Post tracking barcode for testing/mocking.
     */
    public static function generateIranPostCode(string $method = 'پست پیشتاز'): self
    {
        $code = '24'
            . (string)random_int(10000000, 99999999)
            . (string)random_int(10000000, 99999999)
            . (string)random_int(100000, 999999);

        return new self($code, $method);
    }

    public function getTrackingUrl(): ?string
    {
        if ($this->code === null || trim($this->code) === '') {
            return null;
        }

        return 'https://tracking.post.ir/?id=' . urlencode($this->code);
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->code === $other->code
            && $this->shippingMethod === $other->shippingMethod;
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'shipping_method' => $this->shippingMethod,
            'tracking_url' => $this->getTrackingUrl(),
        ];
    }
}
