<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\SubscriptionPlan;

interface SubscriptionPlanRepositoryInterface
{
    public function findById(int $id): ?SubscriptionPlan;

    public function findBySlug(string $slug): ?SubscriptionPlan;

    /**
     * @return array<SubscriptionPlan>
     */
    public function findAllActive(): array;

    public function findDefaultPlan(): SubscriptionPlan;

    public function save(SubscriptionPlan $plan): SubscriptionPlan;
}
