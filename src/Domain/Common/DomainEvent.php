<?php

declare(strict_types=1);

namespace App\Domain\Common;

use DateTimeImmutable;

/**
 * Base contract for domain events representing significant state mutations.
 */
interface DomainEvent
{
    /**
     * Unique UUID identifying this event instance.
     */
    public function eventId(): string;

    /**
     * Timestamp when the event occurred in UTC.
     */
    public function occurredAt(): DateTimeImmutable;

    /**
     * Machine-readable event name (e.g. "order.submitted").
     */
    public function eventName(): string;

    /**
     * Aggregate Root primary ID that emitted the event.
     */
    public function aggregateId(): int|string;

    /**
     * Serialized payload for logging, auditing, or event distribution.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
