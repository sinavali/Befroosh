<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Common\DomainEvent;
use DateTimeImmutable;

final readonly class StockInwardRecordedEvent implements DomainEvent
{
    public function __construct(
        public string $eventId,
        public int $shopId,
        public int $productId,
        public float $quantity,
        public float $unitCost,
        public float $totalCost,
        public float $newStockQuantity,
        public ?string $invoiceRef = null,
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
        return 'inventory.stock_inward';
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
            'quantity' => $this->quantity,
            'unit_cost' => $this->unitCost,
            'total_cost' => $this->totalCost,
            'new_stock' => $this->newStockQuantity,
            'invoice_ref' => $this->invoiceRef,
            'actor_id' => $this->actorId,
        ];
    }
}
