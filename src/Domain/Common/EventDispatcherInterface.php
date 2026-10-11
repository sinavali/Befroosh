<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Contract for dispatching domain events to listeners and subscribers.
 */
interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;

    /**
     * @param array<DomainEvent> $events
     */
    public function dispatchAll(array $events): void;
}
