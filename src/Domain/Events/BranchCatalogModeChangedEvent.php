<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class BranchCatalogModeChangedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $businessId,
        public int $branchId,
        public string $oldMode,
        public string $newMode,
        public ?int $actorId = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {}

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'branch.catalog_mode_changed';
    }

    public function aggregateId(): int
    {
        return $this->branchId;
    }

    public function payload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'old_mode' => $this->oldMode,
            'new_mode' => $this->newMode,
            'actor_id' => $this->actorId,
        ];
    }
}
