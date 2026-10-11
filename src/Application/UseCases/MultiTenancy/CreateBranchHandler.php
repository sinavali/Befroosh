<?php

declare(strict_types=1);

namespace App\Application\UseCases\MultiTenancy;

use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\SubscriptionLimitExceededException;
use App\Domain\MultiTenancy\Branch;
use App\Domain\Repositories\BranchRepositoryInterface;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

/**
 * Use-Case: Creates a new business branch while strictly enforcing
 * the subscription tier quota for maximum branches.
 */
final readonly class CreateBranchHandler
{
    public function __construct(
        private BranchRepositoryInterface $branchRepository,
        private ShopRepositoryInterface $shopRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private TransactionManagerInterface $transactionManager
    ) {}

    public function handle(
        int $businessId,
        string $name,
        string $slug,
        ?string $phone = null,
        ?string $address = null,
        string $catalogMode = 'both',
        ?int $shippingGroupId = null,
        float $defaultShippingCost = 0.0,
        float $freeShippingThreshold = 0.0,
        bool $isMain = false
    ): Branch {
        return $this->transactionManager->transactional(function () use (
            $businessId, $name, $slug, $phone, $address, $catalogMode,
            $shippingGroupId, $defaultShippingCost, $freeShippingThreshold, $isMain
        ) {
            $shop = $this->shopRepository->findById($businessId);
            if ($shop === null) {
                throw EntityNotFoundException::forEntity('Shop', $businessId);
            }

            // Verify subscription limits
            $planId = $shop->getSubscriptionPlanId();
            $plan = $this->subscriptionPlanRepository->findById($planId)
                ?? $this->subscriptionPlanRepository->findDefaultPlan();

            $currentBranchCount = $this->branchRepository->countActiveByBusinessId($businessId);
            $plan->assertCanCreateBranch($currentBranchCount, $businessId);

            $branch = new Branch(
                businessId: $businessId,
                name: $name,
                slug: $slug,
                phone: $phone,
                address: $address,
                catalogMode: $catalogMode,
                shippingGroupId: $shippingGroupId,
                defaultShippingCost: $defaultShippingCost,
                freeShippingThreshold: $freeShippingThreshold,
                isMain: $isMain
            );

            return $this->branchRepository->save($branch);
        });
    }
}
