<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class OrderPaidEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public float $paidTotal,
        public string $paymentMethod = 'card_to_card',
        public ?int $verifiedByUserId = null,
        public ?DateTimeImmutable $paidAt = null,
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
        return 'order.paid';
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
            'paid_total' => $this->paidTotal,
            'payment_method' => $this->paymentMethod,
            'verified_by' => $this->verifiedByUserId,
            'paid_at' => ($this->paidAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
