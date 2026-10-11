<?php

declare(strict_types=1);

namespace App\Domain\Support;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * Ticket entity for customer-merchant and merchant-superadmin support channels.
 */
final class Ticket extends Entity
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public function __construct(
        private int $shopId,
        private int $customerId,
        private string $title,
        private string $status = self::STATUS_OPEN,
        private string $priority = 'medium',
        private bool $isEscalatedToSuperadmin = false,
        private ?int $assignedToUserId = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function isEscalatedToSuperadmin(): bool
    {
        return $this->isEscalatedToSuperadmin;
    }

    public function getAssignedToUserId(): ?int
    {
        return $this->assignedToUserId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function escalateToSuperadmin(): void
    {
        $this->isEscalatedToSuperadmin = true;
        $this->priority = 'urgent';
        $this->updatedAt = new DateTimeImmutable();
    }

    public function resolve(): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function close(): void
    {
        $this->status = self::STATUS_CLOSED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignTo(?int $userId): void
    {
        $this->assignedToUserId = $userId;
        $this->status = self::STATUS_IN_PROGRESS;
        $this->updatedAt = new DateTimeImmutable();
    }
}
