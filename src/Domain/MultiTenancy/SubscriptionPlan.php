<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use App\Domain\Exceptions\SubscriptionLimitExceededException;

/**
 * Subscription plan defining multi-tenant limits and feature toggles.
 */
final class SubscriptionPlan extends Entity
{
    public function __construct(
        ?int $id,
        private string $slug,
        private string $name,
        private float $priceIrt,
        private int $maxBusinesses,
        private int $maxBranches,
        private int $maxProducts,
        private int $maxManagers,
        private bool $hasTicketing,
        private bool $hasOnlinePayment,
        private bool $hasTicketEscalation,
        private int $reportingYears = 1,
        private bool $allowCustomDomain = false,
        private bool $allowSmsAlerts = false,
        private bool $isActive = true
    ) {
        $this->id = $id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriceIrt(): float
    {
        return $this->priceIrt;
    }

    public function getMaxBusinesses(): int
    {
        return $this->maxBusinesses;
    }

    public function getMaxBranches(): int
    {
        return $this->maxBranches;
    }

    public function getMaxProducts(): int
    {
        return $this->maxProducts;
    }

    public function getMaxManagers(): int
    {
        return $this->maxManagers;
    }

    public function hasTicketing(): bool
    {
        return $this->hasTicketing;
    }

    public function hasOnlinePayment(): bool
    {
        return $this->hasOnlinePayment;
    }

    public function hasTicketEscalation(): bool
    {
        return $this->hasTicketEscalation;
    }

    public function getReportingYears(): int
    {
        return $this->reportingYears;
    }

    public function allowCustomDomain(): bool
    {
        return $this->allowCustomDomain;
    }

    public function allowSmsAlerts(): bool
    {
        return $this->allowSmsAlerts;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isUltimate(): bool
    {
        return $this->slug === 'ultimate';
    }

    public function canAddBranch(int $currentActiveBranchCount): bool
    {
        return $currentActiveBranchCount < $this->maxBranches;
    }

    public function assertCanCreateBranch(int $currentActiveBranchCount, int $shopId = 0): void
    {
        if (!$this->canAddBranch($currentActiveBranchCount)) {
            throw SubscriptionLimitExceededException::forBranches(
                $shopId,
                $this->maxBranches,
                $currentActiveBranchCount
            );
        }
    }

    public function canAddProduct(int $currentActiveProductCount): bool
    {
        return $currentActiveProductCount < $this->maxProducts;
    }

    public function assertCanCreateProduct(int $currentActiveProductCount, int $shopId = 0): void
    {
        if (!$this->canAddProduct($currentActiveProductCount)) {
            throw SubscriptionLimitExceededException::forProducts(
                $shopId,
                $this->maxProducts,
                $currentActiveProductCount
            );
        }
    }

    public function canAddManager(int $currentActiveManagerCount): bool
    {
        return $currentActiveManagerCount < $this->maxManagers;
    }

    public function assertCanAssignManager(int $currentActiveManagerCount, int $shopId = 0): void
    {
        if (!$this->canAddManager($currentActiveManagerCount)) {
            throw SubscriptionLimitExceededException::forManagers(
                $shopId,
                $this->maxManagers,
                $currentActiveManagerCount
            );
        }
    }

    public function canEscalateTicket(): bool
    {
        return $this->hasTicketEscalation;
    }

    public function assertCanEscalateTicket(int $shopId = 0): void
    {
        if (!$this->canEscalateTicket()) {
            throw SubscriptionLimitExceededException::forTicketEscalation(
                $shopId,
                $this->slug
            );
        }
    }
}
