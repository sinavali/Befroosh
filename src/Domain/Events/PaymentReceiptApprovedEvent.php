<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class PaymentReceiptApprovedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public float $grandTotal,
        public float $subtotal,
        public float $taxAmount,
        public float $shippingCost,
        public ?int $verifiedByUserId = null,
        public ?DateTimeImmutable $verifiedAt = null,
        public float $discountAmount = 0.0,
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
        return 'payment.receipt_approved';
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
            'grand_total' => $this->grandTotal,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->taxAmount,
            'shipping_cost' => $this->shippingCost,
            'discount_amount' => $this->discountAmount,
            'verified_by' => $this->verifiedByUserId,
            'verified_at' => ($this->verifiedAt ?? $this->occurredAt)->format('Y-m-d H:i:s'),
        ];
    }
}
