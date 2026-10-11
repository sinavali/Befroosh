<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use App\Domain\Events\BranchCatalogModeChangedEvent;
use DateTimeImmutable;

/**
 * Branch entity within a multi-tenant business hierarchy.
 */
final class Branch extends Entity
{
    public const CATALOG_MODE_BOTH = 'both';
    public const CATALOG_MODE_BUSINESS_ONLY = 'business_only';
    public const CATALOG_MODE_BRANCH_ONLY = 'branch_only';

    public const VALID_CATALOG_MODES = [
        self::CATALOG_MODE_BOTH,
        self::CATALOG_MODE_BUSINESS_ONLY,
        self::CATALOG_MODE_BRANCH_ONLY,
    ];

    public function __construct(
        private int $businessId,
        private string $name,
        private string $slug,
        private ?string $logoPath = null,
        private ?string $phone = null,
        private ?string $address = null,
        private string $catalogMode = self::CATALOG_MODE_BOTH,
        private ?int $shippingGroupId = null,
        private float $defaultShippingCost = 250000.0,
        private float $freeShippingThreshold = 2000000.0,
        private bool $isMain = false,
        private bool $active = true,
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
            throw new \InvalidArgumentException('شناسه انگلیسی شعبه (slug) نمی‌تواند خالی باشد.');
        }

        if (!in_array($this->catalogMode, self::VALID_CATALOG_MODES, true)) {
            throw new \InvalidArgumentException(sprintf('حالت کاتالوگ شعبه نامعتبر است: %s', $this->catalogMode));
        }

        if ($this->defaultShippingCost < 0.0) {
            throw new \InvalidArgumentException('هزینه ارسال شعبه نمی‌تواند منفی باشد.');
        }

        if ($this->freeShippingThreshold < 0.0) {
            throw new \InvalidArgumentException('حداقل مبلغ ارسال رایگان شعبه نمی‌تواند منفی باشد.');
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getCatalogMode(): string
    {
        return $this->catalogMode;
    }

    public function getShippingGroupId(): ?int
    {
        return $this->shippingGroupId;
    }

    public function getDefaultShippingCost(): float
    {
        return $this->defaultShippingCost;
    }

    public function getFreeShippingThreshold(): float
    {
        return $this->freeShippingThreshold;
    }

    public function isMain(): bool
    {
        return $this->isMain;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeCatalogMode(string $newMode, ?int $actorId = null): void
    {
        if (!in_array($newMode, self::VALID_CATALOG_MODES, true)) {
            throw new \InvalidArgumentException(sprintf('حالت کاتالوگ جدید نامعتبر است: %s', $newMode));
        }

        $oldMode = $this->catalogMode;
        if ($oldMode === $newMode) {
            return;
        }

        $this->catalogMode = $newMode;
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new BranchCatalogModeChangedEvent(
            eventId: bin2hex(random_bytes(16)),
            businessId: $this->businessId,
            branchId: $this->id ?? 0,
            oldMode: $oldMode,
            newMode: $newMode,
            actorId: $actorId
        ));
    }

    /**
     * Calculates effective shipping cost, delegating to shipping group if assigned.
     */
    public function calculateEffectiveShipping(float $subtotal, ?ShippingGroup $shippingGroup = null): float
    {
        if ($shippingGroup !== null && $this->shippingGroupId !== null && $shippingGroup->getId() === $this->shippingGroupId) {
            return $shippingGroup->calculateShipping($subtotal);
        }

        if ($this->freeShippingThreshold > 0.0 && $subtotal >= $this->freeShippingThreshold) {
            return 0.0;
        }

        return $this->defaultShippingCost;
    }

    public function setShippingGroupId(?int $shippingGroupId): void
    {
        $this->shippingGroupId = $shippingGroupId;
        $this->updatedAt = new DateTimeImmutable();
    }
}
