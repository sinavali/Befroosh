<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class ReservationExpiredEvent implements DomainEvent
{
    /**
     * @param array<array{product_id: int, quantity: float, unit_cost?: float}> $expiredItems
     */
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public array $expiredItems = [],
        public ?DateTimeImmutable $expiredAt = null,
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
        return 'order.reservation_expired';
    }

    public function aggregateId(): int
    {
        return $this->orderId;
    }

    public function payload(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_uuid' => $this->orderUuid,
            'shop_id' => $this->shopId,
            'items_restored' => count($this->expiredItems),
            'expired_items' => $this->expiredItems,
            'items' => $this->expiredItems,
            'expired_at' => ($this->expiredAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
