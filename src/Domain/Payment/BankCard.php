<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * BankCard entity representing merchant offline card-to-card reception accounts.
 */
final class BankCard extends Entity
{
    private string $normalizedCardNumber;

    public function __construct(
        private int $shopId,
        string $cardNumber,
        private string $cardHolder,
        private ?string $bankName = null,
        private ?string $shabaNumber = null,
        private bool $active = true,
        private ?string $expiresAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->normalizedCardNumber = preg_replace('/\D+/', '', $cardNumber) ?? '';
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (!preg_match('/^\d{16}$/', $this->normalizedCardNumber)) {
            throw new \InvalidArgumentException(
                sprintf('شماره کارت بانکی نامعتبر است: «%s». باید دقیقاً ۱۶ رقم باشد.', $this->normalizedCardNumber)
            );
        }

        if (trim($this->cardHolder) === '') {
            throw new \InvalidArgumentException('نام دارنده کارت بانکی نمی‌تواند خالی باشد.');
        }
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getCardNumber(): string
    {
        return $this->normalizedCardNumber;
    }

    public function getCardHolder(): string
    {
        return $this->cardHolder;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function getShabaNumber(): ?string
    {
        return $this->shabaNumber;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Formats card number into 4-digit groups (e.g. 6037-9918-1234-5678).
     */
    public function getFormattedCardNumber(): string
    {
        return implode('-', str_split($this->normalizedCardNumber, 4));
    }
}
