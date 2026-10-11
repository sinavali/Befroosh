<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Common\Entity;
use App\Domain\Events\StockAdjustedEvent;
use App\Domain\Events\StockInwardRecordedEvent;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use DateTimeImmutable;

/**
 * Product aggregate root governing pricing, inventory, reservations, and purchase constraints.
 */
final class Product extends Entity
{
    private StockQuantity $stockQuantity;
    private StockQuantity $reservedQuantity;
    private ?Sku $sku = null;

    public function __construct(
        private int $shopId,
        private string $title,
        private int $price,
        float|StockQuantity $stockQuantity = 0.0,
        float|StockQuantity $reservedQuantity = 0.0,
        private float $costPrice = 0.0,
        string|Sku|null $sku = null,
        private ?int $branchId = null,
        private ?int $categoryId = null,
        private ?string $slug = null,
        private ?string $description = null,
        private float $minStockAlert = 5.0,
        private float $minOrderQty = 1.0,
        private ?string $barcode = null,
        private string $unit = 'عدد',
        private int $weightGrams = 0,
        private float $taxRate = 0.0,
        private float $maxPerOrder = 0.0,
        private float $maxPerMonth = 0.0,
        private bool $isBusinessProduct = true,
        private ?string $imagePath = null,
        private bool $active = true,
        private ?DateTimeImmutable $deletedAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->stockQuantity = $stockQuantity instanceof StockQuantity ? $stockQuantity : StockQuantity::from($stockQuantity);
        $this->reservedQuantity = $reservedQuantity instanceof StockQuantity ? $reservedQuantity : StockQuantity::from($reservedQuantity);
        $this->sku = $sku === null ? null : ($sku instanceof Sku ? $sku : Sku::from($sku));
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->validateInvariants();
    }

    private function validateInvariants(): void
    {
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('عنوان کالا نمی‌تواند خالی باشد.');
        }

        if ($this->price < 0) {
            throw new \InvalidArgumentException('قیمت فروش کالا نمی‌تواند منفی باشد.');
        }

        if ($this->costPrice < 0.0) {
            throw new \InvalidArgumentException('قیمت خرید کالا نمی‌تواند منفی باشد.');
        }

        if ($this->taxRate < 0.0 || $this->taxRate > 1.0) {
            throw new \InvalidArgumentException('نرخ مالیات کالا باید بین ۰ و ۱ باشد.');
        }

        if ($this->maxPerOrder < 0.0) {
            throw new \InvalidArgumentException('سقف خرید در هر سفارش نمی‌تواند منفی باشد.');
        }

        if ($this->maxPerMonth < 0.0) {
            throw new \InvalidArgumentException('سقف خرید ماهانه نمی‌تواند منفی باشد.');
        }
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getCostPrice(): float
    {
        return $this->costPrice;
    }

    public function getStockQuantity(): StockQuantity
    {
        return $this->stockQuantity;
    }

    public function getReservedQuantity(): StockQuantity
    {
        return $this->reservedQuantity;
    }

    public function getSku(): ?Sku
    {
        return $this->sku;
    }

    public function getBranchId(): ?int
    {
        return $this->branchId;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMinStockAlert(): float
    {
        return $this->minStockAlert;
    }

    public function getMinOrderQty(): float
    {
        return $this->minOrderQty;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getWeightGrams(): int
    {
        return $this->weightGrams;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getMaxPerOrder(): float
    {
        return $this->maxPerOrder;
    }

    public function getMaxPerMonth(): float
    {
        return $this->maxPerMonth;
    }

    public function isBusinessProduct(): bool
    {
        return $this->isBusinessProduct;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Physical stock available for new reservations or immediate purchase.
     */
    public function getAvailableStock(): float
    {
        return max(0.0, $this->stockQuantity->value - $this->reservedQuantity->value);
    }

    public function availableStock(): float
    {
        return $this->getAvailableStock();
    }

    public function isInStock(): bool
    {
        return $this->getAvailableStock() > 0.0;
    }

    /**
     * Enforces Triple-Tier Purchase Limits: on-hand stock, per-order cap, and rolling 30-day cap.
     *
     * @throws InsufficientStockException
     * @throws PurchaseLimitExceededException
     */
    public function assertCanPurchase(float $requestedQty, float $past30DaysPurchased = 0.0): void
    {
        if ($requestedQty <= 0.0) {
            throw new \InvalidArgumentException('تعداد درخواستی خرید باید بزرگتر از صفر باشد.');
        }

        if ($this->minOrderQty > 0.0 && $requestedQty < $this->minOrderQty) {
            throw new \InvalidArgumentException(
                sprintf('حداقل تعداد مجاز برای سفارش کالای «%s» %s عدد است.', $this->title, $this->minOrderQty)
            );
        }

        // 1. Available Stock check
        $available = $this->getAvailableStock();
        if ($requestedQty > $available) {
            throw InsufficientStockException::forProduct(
                $this->id ?? 0,
                $this->title,
                $available,
                $requestedQty
            );
        }

        // 2. Per-Order Max Cap check
        if ($this->maxPerOrder > 0.0 && $requestedQty > $this->maxPerOrder) {
            throw PurchaseLimitExceededException::perOrder(
                $this->id ?? 0,
                $this->title,
                $this->maxPerOrder,
                $requestedQty
            );
        }

        // 3. Rolling 30-Day Monthly Cap check
        if ($this->maxPerMonth > 0.0 && ($past30DaysPurchased + $requestedQty) > $this->maxPerMonth) {
            throw PurchaseLimitExceededException::perMonth(
                $this->id ?? 0,
                $this->title,
                $this->maxPerMonth,
                $past30DaysPurchased,
                $requestedQty
            );
        }
    }

    /**
     * Places a reservation hold on available stock.
     */
    public function reserveStock(float $qty): void
    {
        if ($qty <= 0.0) {
            return;
        }

        $available = $this->getAvailableStock();
        if ($qty > $available) {
            throw InsufficientStockException::forProduct(
                $this->id ?? 0,
                $this->title,
                $available,
                $qty
            );
        }

        $this->reservedQuantity = $this->reservedQuantity->add($qty);
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Releases held reservation back to available inventory.
     */
    public function releaseReservedStock(float $qty): void
    {
        if ($qty <= 0.0) {
            return;
        }

        $this->reservedQuantity = $this->reservedQuantity->clampSubtract($qty);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function releaseReservation(float $qty): void
    {
        $this->releaseReservedStock($qty);
    }

    /**
     * Fulfills an order shipment, deducting both physical stock and reserved hold.
     */
    public function fulfillReservation(float $qty): void
    {
        if ($qty <= 0.0) {
            return;
        }

        $this->stockQuantity = $this->stockQuantity->clampSubtract($qty);
        $this->reservedQuantity = $this->reservedQuantity->clampSubtract($qty);
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Adds physical stock and optionally updates cost price.
     */
    public function addStock(float $qty, float $unitCost = 0.0, ?int $actorId = null): void
    {
        if ($qty <= 0.0) {
            return;
        }

        $this->stockQuantity = $this->stockQuantity->add($qty);
        if ($unitCost > 0.0) {
            $this->costPrice = $unitCost;
        }
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new StockAdjustedEvent(
            eventId: bin2hex(random_bytes(16)),
            shopId: $this->shopId,
            productId: $this->id ?? 0,
            adjustmentType: 'adjustment_plus',
            quantityDelta: $qty,
            newStockQuantity: $this->stockQuantity->value,
            unitCost: $this->costPrice,
            notes: 'افزایش موجودی',
            actorId: $actorId
        ));
    }

    public function recordInwardStock(float $qty, float $unitCost = 0.0, ?int $actorId = null, ?string $invoiceRef = null): void
    {
        if ($qty <= 0.0) {
            return;
        }

        $this->stockQuantity = $this->stockQuantity->add($qty);
        if ($unitCost > 0.0) {
            $this->costPrice = $unitCost;
        }
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new StockInwardRecordedEvent(
            eventId: bin2hex(random_bytes(16)),
            shopId: $this->shopId,
            productId: $this->id ?? 0,
            quantity: $qty,
            unitCost: $this->costPrice,
            totalCost: round($qty * $this->costPrice, 2),
            newStockQuantity: $this->stockQuantity->value,
            invoiceRef: $invoiceRef,
            actorId: $actorId
        ));
    }

    /**
     * Removes physical stock, either strictly or with non-negative clamping.
     */
    public function removeStock(float $qty, bool $clamp = false): void
    {
        if ($qty <= 0.0) {
            return;
        }

        if ($clamp) {
            $this->stockQuantity = $this->stockQuantity->clampSubtract($qty);
        } else {
            $this->stockQuantity = $this->stockQuantity->subtract($qty);
        }
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * General inventory adjustment (+ or -).
     */
    public function adjustStock(float $delta, string $reason = '', ?int $actorId = null): void
    {
        if (abs($delta) < 0.0001) {
            return;
        }

        if ($delta > 0) {
            $this->stockQuantity = $this->stockQuantity->add($delta);
            $type = 'adjustment_plus';
        } else {
            $this->stockQuantity = $this->stockQuantity->clampSubtract(abs($delta));
            $type = 'adjustment_minus';
        }

        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new StockAdjustedEvent(
            eventId: bin2hex(random_bytes(16)),
            shopId: $this->shopId,
            productId: $this->id ?? 0,
            adjustmentType: $type,
            quantityDelta: $delta,
            newStockQuantity: $this->stockQuantity->value,
            unitCost: $this->costPrice,
            notes: $reason,
            actorId: $actorId
        ));
    }

    /**
     * Clamps stock quantities ensuring non-negativity.
     */
    public function clampStock(): void
    {
        if ($this->stockQuantity->value < 0.0) {
            $this->stockQuantity = StockQuantity::zero();
        }
        if ($this->reservedQuantity->value < 0.0) {
            $this->reservedQuantity = StockQuantity::zero();
        }
        $this->updatedAt = new DateTimeImmutable();
    }

    public function softDelete(): void
    {
        $this->active = false;
        $this->deletedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->active = true;
        $this->deletedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDetails(
        string $title,
        int $price,
        float $costPrice,
        ?string $slug = null,
        ?string $description = null,
        ?int $categoryId = null,
        ?int $branchId = null,
        float $taxRate = 0.0,
        float $maxPerOrder = 0.0,
        float $maxPerMonth = 0.0
    ): void {
        $this->title = $title;
        $this->price = $price;
        $this->costPrice = $costPrice;
        $this->slug = $slug;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->branchId = $branchId;
        $this->taxRate = $taxRate;
        $this->maxPerOrder = $maxPerOrder;
        $this->maxPerMonth = $maxPerMonth;
        $this->validateInvariants();
        $this->updatedAt = new DateTimeImmutable();
    }
}
