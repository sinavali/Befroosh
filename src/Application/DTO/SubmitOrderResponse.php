<?php

declare(strict_types=1);

namespace App\Application\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object for order submission responses.
 */
final readonly class SubmitOrderResponse
{
    public function __construct(
        public int $orderId,
        public string $uuid,
        public string $status,
        public float $grandTotal,
        public float $subtotal,
        public float $shippingCost,
        public float $taxAmount,
        public bool $isPaid,
        public ?DateTimeImmutable $reservationExpiresAt,
        public int $itemsCount
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'grand_total' => $this->grandTotal,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shippingCost,
            'tax_amount' => $this->taxAmount,
            'is_paid' => $this->isPaid,
            'reservation_expires_at' => $this->reservationExpiresAt?->format('Y-m-d H:i:s'),
            'items_count' => $this->itemsCount,
        ];
    }
}
