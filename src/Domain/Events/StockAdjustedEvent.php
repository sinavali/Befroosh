<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class StockAdjustedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $shopId,
        public int $productId,
        public string $adjustmentType, // 'adjustment_plus' | 'adjustment_minus' | 'write_off' | 'return_in'
        public float $quantityDelta,
        public float $newStockQuantity,
        public float $unitCost = 0.0,
        public string $notes = '',
        public ?int $actorId = null,
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
        return 'inventory.stock_adjusted';
    }

    public function aggregateId(): int
    {
        return $this->productId;
    }

    public function payload(): array
    {
        return [
            'shop_id' => $this->shopId,
            'product_id' => $this->productId,
            'type' => $this->adjustmentType,
            'delta' => $this->quantityDelta,
            'new_stock' => $this->newStockQuantity,
            'unit_cost' => $this->unitCost,
            'actor_id' => $this->actorId,
        ];
    }
}
