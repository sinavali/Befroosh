<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * Category hierarchy entity.
 */
final class Category extends Entity
{
    public function __construct(
        private int $shopId,
        private string $name,
        private string $slug,
        private ?int $parentId = null,
        private int $sortOrder = 0,
        private bool $active = true,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('نام دسته‌بندی نمی‌تواند خالی باشد.');
        }

        if (trim($this->slug) === '') {
            throw new \InvalidArgumentException('شناسه انگلیسی دسته‌بندی (slug) نمی‌تواند خالی باشد.');
        }

        if ($this->id !== null && $this->parentId !== null && $this->id === $this->parentId) {
            throw new \InvalidArgumentException('دسته‌بندی نمی‌تواند والد خودش باشد.');
        }
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->validateInvariants();
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
        $this->validateInvariants();
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
