<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Domain\Common\DomainEvent;
use App\Domain\Common\EventDispatcherInterface;

/**
 * In-memory Domain Event Dispatcher routing domain events to registered listeners.
 */
final class DomainEventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /**
     * Registers a listener callback for a domain event class or event name.
     */
    public function subscribe(string $eventClassOrName, callable $listener): void
    {
        $this->listeners[$eventClassOrName][] = $listener;
    }

    public function dispatch(DomainEvent $event): void
    {
        $class = get_class($event);
        $name = $event->eventName();

        // Dispatch by concrete class
        if (isset($this->listeners[$class])) {
            foreach ($this->listeners[$class] as $listener) {
                $listener($event);
            }
        }

        // Dispatch by event name string
        if (isset($this->listeners[$name])) {
            foreach ($this->listeners[$name] as $listener) {
                $listener($event);
            }
        }

        // Wildcard subscribers
        if (isset($this->listeners['*'])) {
            foreach ($this->listeners['*'] as $listener) {
                $listener($event);
            }
        }
    }

    /**
     * @param array<DomainEvent> $events
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
