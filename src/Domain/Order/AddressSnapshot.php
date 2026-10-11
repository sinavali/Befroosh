<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\ValueObject;

/**
 * Immutable snapshot of customer shipping address frozen at order placement.
 */
final readonly class AddressSnapshot implements ValueObject
{
    public function __construct(
        public ?string $recipientName,
        public ?string $recipientPhone,
        public string $state,
        public string $city,
        public string $address,
        public ?string $postalCode = null,
        public ?string $description = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            recipientName: $data['recipient_name'] ?? $data['name'] ?? null,
            recipientPhone: $data['recipient_phone'] ?? $data['phone'] ?? null,
            state: (string)($data['state'] ?? ''),
            city: (string)($data['city'] ?? ''),
            address: (string)($data['address'] ?? ''),
            postalCode: isset($data['postal_code']) ? (string)$data['postal_code'] : null,
            description: $data['description'] ?? null
        );
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return new self(null, null, '', '', '');
        }

        return self::fromArray($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'state' => $this->state,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
            'description' => $this->description,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->recipientName === $other->recipientName
            && $this->recipientPhone === $other->recipientPhone
            && $this->state === $other->state
            && $this->city === $other->city
            && $this->address === $other->address
            && $this->postalCode === $other->postalCode;
    }
}
