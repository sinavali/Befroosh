<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\Entity;
use DateTimeImmutable;

/**
 * Immutable stock movement ledger transaction.
 */
final class InventoryTransaction extends Entity
{
    public const TYPE_INWARD = 'inward';
    public const TYPE_OUTWARD_ORDER = 'outward_order';
    public const TYPE_ADJUSTMENT_PLUS = 'adjustment_plus';
    public const TYPE_ADJUSTMENT_MINUS = 'adjustment_minus';
    public const TYPE_RETURN_IN = 'return_in';
    public const TYPE_WRITE_OFF = 'write_off';

    public const VALID_TYPES = [
        self::TYPE_INWARD,
        self::TYPE_OUTWARD_ORDER,
        self::TYPE_ADJUSTMENT_PLUS,
        self::TYPE_ADJUSTMENT_MINUS,
        self::TYPE_RETURN_IN,
        self::TYPE_WRITE_OFF,
    ];

    public function __construct(
        private int $shopId,
        private int $productId,
        private string $type,
        private float $quantity,
        private ?float $unitCost = null,
        private ?string $referenceType = null,
        private ?int $referenceId = null,
        private ?string $notes = null,
        private ?int $createdById = null,
        private ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (!in_array($this->type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('نوع تراکنش انبار نامعتبر است: %s', $this->type));
        }

        if ($this->quantity <= 0.0) {
            throw new \InvalidArgumentException('تعداد تراکنش انبار باید بزرگتر از صفر باشد.');
        }
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitCost(): ?float
    {
        return $this->unitCost;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedById(): ?int
    {
        return $this->createdById;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isStockIncrement(): bool
    {
        return in_array($this->type, [
            self::TYPE_INWARD,
            self::TYPE_ADJUSTMENT_PLUS,
            self::TYPE_RETURN_IN,
        ], true);
    }

    public function isStockDecrement(): bool
    {
        return in_array($this->type, [
            self::TYPE_OUTWARD_ORDER,
            self::TYPE_ADJUSTMENT_MINUS,
            self::TYPE_WRITE_OFF,
        ], true);
    }

    /**
     * Signed delta on inventory (+ for inwards/returns/adjustments, - for sales/losses).
     */
    public function getStockDelta(): float
    {
        return $this->isStockIncrement() ? $this->quantity : -$this->quantity;
    }
}
