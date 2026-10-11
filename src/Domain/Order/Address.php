<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * Address entity representing a saved customer shipping location.
 */
final class Address extends Entity
{
    public function __construct(
        private int $userId,
        private string $state,
        private string $city,
        private string $address,
        private ?string $recipientName = null,
        private ?string $recipientPhone = null,
        private ?string $postalCode = null,
        private ?string $description = null,
        private bool $isDefault = false,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function getRecipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function toSnapshot(): AddressSnapshot
    {
        return new AddressSnapshot(
            recipientName: $this->recipientName,
            recipientPhone: $this->recipientPhone,
            state: $this->state,
            city: $this->city,
            address: $this->address,
            postalCode: $this->postalCode,
            description: $this->description
        );
    }
}
