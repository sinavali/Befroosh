<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class OrderSubmittedEvent implements DomainEvent
{
    /**
     * @param array<array{product_id: int, quantity: float, unit_price: float, unit_cost: float, line_total: float}> $lineItems
     */
    public function __construct(
        public string $eventId,
        public int $orderId,
        public string $orderUuid,
        public int $shopId,
        public int $customerId,
        public ?int $branchId,
        public float $subtotal,
        public float $shippingCost,
        public float $taxAmount,
        public float $grandTotal,
        public ?DateTimeImmutable $reservationExpiresAt,
        public array $lineItems = [],
        public bool $isAutoPaid = false,
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
        return 'order.submitted';
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
            'branch_id' => $this->branchId,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shippingCost,
            'tax_amount' => $this->taxAmount,
            'grand_total' => $this->grandTotal,
            'reservation_expires_at' => $this->reservationExpiresAt?->format('Y-m-d H:i:s'),
            'items_count' => count($this->lineItems),
            'is_auto_paid' => $this->isAutoPaid,
        ];
    }
}
