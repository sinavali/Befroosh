<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class OrderCanceledEvent implements DomainEvent
{
    /**
     * @param array<array{product_id: int, quantity: float, unit_cost?: float}> $restoredItems
     */
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public string $cancellationReason,
        public array $restoredItems = [],
        public ?int $canceledByUserId = null,
        public ?DateTimeImmutable $canceledAt = null,
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
        return 'order.canceled';
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
            'customer_id' => $this->customerId,
            'reason' => $this->cancellationReason,
            'items_restored' => count($this->restoredItems),
            'restored_items' => $this->restoredItems,
            'items' => $this->restoredItems,
            'canceled_by' => $this->canceledByUserId,
            'canceled_at' => ($this->canceledAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
