<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * User entity representing platform staff, shop owners, managers, and customers.
 */
final class User extends Entity
{
    public function __construct(
        private string $username,
        private string $nickname,
        private Role $role,
        private string $passwordHash = '',
        private ?string $phone = null,
        private ?string $email = null,
        private ?int $shopId = null,
        private bool $active = true,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changeRole(Role $newRole): void
    {
        $this->role = $newRole;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = $nickname;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
