<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * User Assignment entity linking a staff user to a branch with a specific role.
 */
final class UserAssignment extends Entity
{
    public function __construct(
        private int $userId,
        private int $branchId,
        private int $businessId,
        private Role $role,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (!in_array($this->role, [Role::BranchManager, Role::ShopManager, Role::Manager, Role::ShopOwner, Role::BusinessOwner], true)) {
            throw new \InvalidArgumentException(sprintf('نقش انتصاب یافته برای شعبه نامعتبر است: %s', $this->role->value));
        }
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getBranchId(): int
    {
        return $this->branchId;
    }

    public function getBusinessId(): int
    {
        return $this->businessId;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
