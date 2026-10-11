<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class OrderShippedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public string $trackingCode,
        public string $shippingMethod = 'پست پیشتاز',
        public ?int $shippedByUserId = null,
        public ?DateTimeImmutable $shippedAt = null,
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
        return 'order.shipped';
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
            'tracking_code' => $this->trackingCode,
            'shipping_method' => $this->shippingMethod,
            'shipped_by' => $this->shippedByUserId,
            'shipped_at' => ($this->shippedAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
