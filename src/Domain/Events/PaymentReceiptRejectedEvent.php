<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class PaymentReceiptRejectedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public string $rejectionReason,
        public ?int $rejectedByUserId = null,
        public ?DateTimeImmutable $rejectedAt = null,
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
        return 'payment.receipt_rejected';
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
            'reason' => $this->rejectionReason,
            'rejected_by' => $this->rejectedByUserId,
            'rejected_at' => ($this->rejectedAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
