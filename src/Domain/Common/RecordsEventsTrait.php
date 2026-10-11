<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Trait providing domain event accumulation for aggregate roots.
 */
trait RecordsEventsTrait
{
    /** @var array<DomainEvent> */
    protected array $recordedEvents = [];

    public function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Releases all recorded events and clears the internal buffer.
     *
     * @return array<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    public function hasEvents(): bool
    {
        return count($this->recordedEvents) > 0;
    }
}
