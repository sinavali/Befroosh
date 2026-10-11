<?php

declare(strict_types=1);

namespace App\Application\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object for order cancellation responses.
 */
final readonly class CancelOrderResponse
{
    public function __construct(
        public int $orderId,
        public string $uuid,
        public string $status,
        public DateTimeImmutable $canceledAt,
        public string $reason,
        public int $itemsRestoredCount
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
            'canceled_at' => $this->canceledAt->format('Y-m-d H:i:s'),
            'reason' => $this->reason,
            'items_restored_count' => $this->itemsRestoredCount,
        ];
    }
}
