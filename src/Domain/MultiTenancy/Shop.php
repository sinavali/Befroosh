<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use App\Domain\Exceptions\DomainException;
use DateTimeImmutable;

/**
 * Multi-tenant root aggregate representing an independent business/shop.
 */
final class Shop extends Entity
{
    /**
     * @param array<string> $paymentMethods
     */
    public function __construct(
        private string $name,
        private string $slug,
        private ?int $ownerId = null,
        private ?string $phone = null,
        private ?string $email = null,
        private ?string $address = null,
        private ?string $nationalId = null,
        private ?string $economicCode = null,
        private ?string $cardNumber = null,
        private ?string $cardHolder = null,
        private ?string $bankName = null,
        private ?string $shabaNumber = null,
        private bool $cardToCardEnabled = true,
        private int $reservationDays = 4,
        private array $paymentMethods = ['card_to_card'],
        private float $taxRate = 0.0,
        private float $defaultShippingCost = 250000.0,
        private float $freeShippingThreshold = 2000000.0,
        private bool $active = true,
        private int $subscriptionPlanId = 1,
        private ?DateTimeImmutable $subscriptionExpiresAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (trim($this->slug) === '') {
            throw new \InvalidArgumentException('شناسه انگلیسی فروشگاه (slug) نمی‌تواند خالی باشد.');
        }

        if ($this->taxRate < 0.0 || $this->taxRate > 1.0) {
            throw new \InvalidArgumentException('نرخ مالیات باید مقداری بین ۰ و ۱ باشد.');
        }

        if ($this->defaultShippingCost < 0.0) {
            throw new \InvalidArgumentException('هزینه پیش‌فرض ارسال نمی‌تواند منفی باشد.');
        }

        if ($this->freeShippingThreshold < 0.0) {
            throw new \InvalidArgumentException('حداقل مبلغ ارسال رایگان نمی‌تواند منفی باشد.');
        }

        if ($this->reservationDays < 1) {
            throw new \InvalidArgumentException('مدت زمان مهلت رزرو کالا باید حداقل ۱ روز باشد.');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function getEconomicCode(): ?string
    {
        return $this->economicCode;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function getCardHolder(): ?string
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

    public function isCardToCardEnabled(): bool
    {
        return $this->cardToCardEnabled;
    }

    public function getReservationDays(): int
    {
        return $this->reservationDays;
    }

    /**
     * @return array<string>
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getDefaultShippingCost(): float
    {
        return $this->defaultShippingCost;
    }

    public function getFreeShippingThreshold(): float
    {
        return $this->freeShippingThreshold;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSubscriptionPlanId(): int
    {
        return $this->subscriptionPlanId;
    }

    public function getSubscriptionExpiresAt(): ?DateTimeImmutable
    {
        return $this->subscriptionExpiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Calculates shipping cost based on subtotal and free shipping threshold.
     */
    public function calculateShippingCost(float $subtotal): float
    {
        if ($this->freeShippingThreshold > 0.0 && $subtotal >= $this->freeShippingThreshold) {
            return 0.0;
        }

        return $this->defaultShippingCost;
    }

    /**
     * Calculates VAT on subtotal rounded to 2 decimal places.
     */
    public function calculateTax(float $subtotal): float
    {
        return round($subtotal * $this->taxRate, 2);
    }

    /**
     * Calculates reservation expiration timestamp from a given start date.
     */
    public function calculateReservationExpiry(?DateTimeImmutable $from = null): DateTimeImmutable
    {
        $base = $from ?? new DateTimeImmutable();
        return $base->modify("+{$this->reservationDays} days");
    }

    /**
     * Checks if shop's subscription is currently active.
     */
    public function isSubscriptionActive(?DateTimeImmutable $now = null): bool
    {
        if ($this->subscriptionExpiresAt === null) {
            return true; // Permanent / unlimited
        }

        $currentTime = $now ?? new DateTimeImmutable();
        return $currentTime <= $this->subscriptionExpiresAt;
    }

    public function updateSettings(
        float $taxRate,
        float $defaultShippingCost,
        float $freeShippingThreshold,
        int $reservationDays
    ): void {
        $this->taxRate = $taxRate;
        $this->defaultShippingCost = $defaultShippingCost;
        $this->freeShippingThreshold = $freeShippingThreshold;
        $this->reservationDays = $reservationDays;
        $this->validateInvariants();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateSubscription(int $planId, ?DateTimeImmutable $expiresAt): void
    {
        $this->subscriptionPlanId = $planId;
        $this->subscriptionExpiresAt = $expiresAt;
        $this->updatedAt = new DateTimeImmutable();
    }
}
