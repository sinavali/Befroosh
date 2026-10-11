<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\MultiTenancy\ShippingGroup;

interface ShippingGroupRepositoryInterface
{
    public function findById(int $id): ?ShippingGroup;

    /**
     * @return array<ShippingGroup>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(ShippingGroup $shippingGroup): ShippingGroup;

    public function delete(int $id): void;
}
