<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\Entity;
use App\Domain\Events\OrderCanceledEvent;
use App\Domain\Events\OrderCompletedEvent;
use App\Domain\Events\OrderPaidEvent;
use App\Domain\Events\OrderShippedEvent;
use App\Domain\Events\OrderSubmittedEvent;
use App\Domain\Events\PaymentReceiptApprovedEvent;
use App\Domain\Events\PaymentReceiptRejectedEvent;
use App\Domain\Events\ReservationExpiredEvent;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderReservationNotExpiredException;
use App\Domain\Exceptions\PaymentAlreadyVerifiedException;
use App\Domain\Exceptions\PostalTrackingCodeInvalidException;
use DateTimeImmutable;

/**
 * Order aggregate root managing order lifecycle, items, payment verification, and 4-day reservation.
 */
final class Order extends Entity
{
    /** @var array<OrderItem> */
    private array $items = [];

    public function __construct(
        private string $uuid,
        private int $shopId,
        private int $customerId,
        private OrderStatus $status,
        private PriceSnapshot $price,
        private AddressSnapshot $addressSnapshot,
        private CustomerSnapshot $customerSnapshot,
        private string $paymentMethod = 'card_to_card',
        private PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
        private ?PostalTracking $postalTracking = null,
        private ?int $branchId = null,
        private ?int $shippingGroupId = null,
        private string $createdByType = 'customer',
        private ?int $createdById = null,
        private ?int $paymentCardId = null,
        private ?string $paymentReceiptPath = null,
        private ?string $paymentReference = null,
        private ?string $paymentRejectReason = null,
        private ?DateTimeImmutable $paidAt = null,
        private ?DateTimeImmutable $reservationExpiresAt = null,
        private ?DateTimeImmutable $shippedAt = null,
        private ?DateTimeImmutable $deliveredAt = null,
        private ?DateTimeImmutable $completedAt = null,
        private ?DateTimeImmutable $canceledAt = null,
        private ?string $cancellationReason = null,
        private ?DateTimeImmutable $finalisedAt = null,
        private ?int $addressId = null,
        private ?string $description = null,
        private string $receiptStatus = 'pending',
        private ?int $receiptVerifiedBy = null,
        private ?DateTimeImmutable $receiptVerifiedAt = null,
        private ?string $receiptRejectionReason = null,
        private bool $seenByCustomer = true,
        private bool $seenByAdmin = false,
        /** @var array<OrderItem> $items */
        array $items = [],
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->postalTracking = $postalTracking ?? PostalTracking::empty();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();

        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    /**
     * Factory creating a new Order and enforcing the Free Order Auto-Pay Invariant.
     *
     * @param array<OrderItem> $items
     */
    public static function create(
        int $shopId,
        int $customerId,
        string $uuid,
        PriceSnapshot $price,
        AddressSnapshot $addressSnapshot,
        CustomerSnapshot $customerSnapshot,
        array $items,
        string $paymentMethod = 'card_to_card',
        int $reservationDays = 4,
        ?int $branchId = null,
        ?int $shippingGroupId = null,
        string $createdByType = 'customer',
        ?int $createdById = null,
        ?DateTimeImmutable $placedAt = null
    ): self {
        $placedAt = $placedAt ?? new DateTimeImmutable();
        $isFree = $price->isFree();

        if ($isFree) {
            // Free Order Auto-Pay Invariant: orders with total <= 0 immediately transition to Paid
            $status = OrderStatus::Paid;
            $paymentStatus = PaymentStatus::Paid;
            $paidAt = $placedAt;
            $reservationExpiresAt = null;
        } else {
            // Normal orders awaiting payment hold stock for 4 days
            $status = OrderStatus::Submitted;
            $paymentStatus = PaymentStatus::Unpaid;
            $paidAt = null;
            $reservationExpiresAt = self::calculateReservationExpiration($placedAt, $reservationDays);
        }

        $order = new self(
            uuid: $uuid,
            shopId: $shopId,
            customerId: $customerId,
            status: $status,
            price: $price,
            addressSnapshot: $addressSnapshot,
            customerSnapshot: $customerSnapshot,
            paymentMethod: $paymentMethod,
            paymentStatus: $paymentStatus,
            postalTracking: PostalTracking::empty(),
            branchId: $branchId,
            shippingGroupId: $shippingGroupId,
            createdByType: $createdByType,
            createdById: $createdById,
            paidAt: $paidAt,
            reservationExpiresAt: $reservationExpiresAt,
            items: $items,
            createdAt: $placedAt,
            updatedAt: $placedAt
        );

        // Serialize items summary for domain event
        $lineItemsPayload = [];
        foreach ($items as $item) {
            $lineItemsPayload[] = [
                'product_id' => $item->getProductId() ?? 0,
                'quantity' => $item->getQuantity(),
                'unit_price' => (float)$item->getUnitPrice(),
                'unit_cost' => $item->getUnitCostPrice(),
                'line_total' => $item->getLineTotal(),
            ];
        }

        $order->recordEvent(new OrderSubmittedEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $order->getId() ?? 0,
            orderUuid: $uuid,
            shopId: $shopId,
            customerId: $customerId,
            branchId: $branchId,
            subtotal: $price->subtotal,
            shippingCost: $price->shippingCost,
            taxAmount: $price->taxAmount,
            grandTotal: $price->finalTotal,
            reservationExpiresAt: $reservationExpiresAt,
            lineItems: $lineItemsPayload,
            isAutoPaid: $isFree,
            occurredAt: $placedAt
        ));

        if ($isFree) {
            $order->recordEvent(new OrderPaidEvent(
                eventId: bin2hex(random_bytes(16)),
                orderId: $order->getId() ?? 0,
                orderUuid: $uuid,
                shopId: $shopId,
                customerId: $customerId,
                paidTotal: 0.0,
                paymentMethod: 'free_order',
                verifiedByUserId: null,
                paidAt: $placedAt,
                occurredAt: $placedAt
            ));
        }

        return $order;
    }

    /**
     * Pure calculation of expiration timestamp for a given duration.
     */
    public static function calculateReservationExpiration(
        DateTimeImmutable $placedAt,
        int $reservationDays = 4
    ): DateTimeImmutable {
        if ($reservationDays <= 0) {
            $reservationDays = 4;
        }

        return $placedAt->modify("+{$reservationDays} days");
    }

    /**
     * Predicate determining if an order's 4-day reservation has expired.
     */
    public function isReservationExpired(?DateTimeImmutable $now = null): bool
    {
        if ($this->status !== OrderStatus::Submitted) {
            return false;
        }

        if ($this->reservationExpiresAt === null) {
            return false;
        }

        $currentTime = $now ?? new DateTimeImmutable();
        return $currentTime >= $this->reservationExpiresAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->isReservationExpired($now);
    }

    /**
     * Releases an expired reservation, transitioning to canceled and emitting event for inventory restoration.
     */
    public function releaseReservation(
        ?DateTimeImmutable $now = null,
        string $reason = 'انقضای مهلت رزرو ۴ روزه و عدم پرداخت'
    ): void {
        $now = $now ?? new DateTimeImmutable();

        if ($this->status !== OrderStatus::Submitted) {
            throw InvalidOrderStateTransitionException::fromTransition(
                $this->id ?? 0,
                $this->uuid,
                $this->status->value,
                OrderStatus::Canceled->value
            );
        }

        if (!$this->isReservationExpired($now)) {
            throw new OrderReservationNotExpiredException(
                sprintf('مهلت رزرو سفارش %s هنوز منقضی نشده است (انقضا در: %s).', $this->uuid, $this->reservationExpiresAt?->format('Y-m-d H:i:s'))
            );
        }

        $this->status = OrderStatus::Canceled;
        $this->paymentStatus = PaymentStatus::Rejected;
        $this->canceledAt = $now;
        $this->cancellationReason = $reason;
        $this->updatedAt = $now;

        $restoredItems = [];
        foreach ($this->items as $item) {
            $restoredItems[] = [
                'product_id' => $item->getProductId() ?? 0,
                'quantity' => $item->getQuantity(),
                'unit_cost' => $item->getUnitCostPrice(),
            ];
        }

        $this->recordEvent(new ReservationExpiredEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            expiredItems: $restoredItems,
            expiredAt: $now,
            occurredAt: $now
        ));

        $this->recordEvent(new OrderCanceledEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            cancellationReason: $reason,
            restoredItems: $restoredItems,
            canceledByUserId: null,
            canceledAt: $now,
            occurredAt: $now
        ));
    }

    public function cancelWithReservationRelease(
        string $reason = 'انقضای مهلت رزرو ۴ روزه و عدم پرداخت',
        ?DateTimeImmutable $now = null
    ): void {
        $this->releaseReservation($now, $reason);
    }

    /**
     * Submits offline Card-to-Card payment receipt.
     */
    public function submitPaymentReceipt(
        string $receiptPath,
        ?string $reference = null,
        ?string $description = null
    ): void {
        if ($this->status !== OrderStatus::Submitted) {
            throw new \LogicException(sprintf('تنها سفارش‌های در وضعیت ثبت‌شده مجاز به بارگذاری فیش هستند (وضعیت فعلی: %s).', $this->status->value));
        }

        $this->paymentReceiptPath = $receiptPath;
        $this->paymentReference = $reference;
        $this->paymentStatus = PaymentStatus::PendingVerification;
        $this->receiptStatus = 'pending';
        $this->seenByAdmin = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Approves payment receipt, transitioning order to Paid and emitting accounting events.
     */
    public function approvePayment(
        int $verifiedByUserId,
        DateTimeImmutable|string|null $at = null,
        ?string $referenceNumber = null
    ): void {
        if ($this->status === OrderStatus::Paid || $this->paymentStatus === PaymentStatus::Paid) {
            throw PaymentAlreadyVerifiedException::forOrder($this->id ?? 0, $this->paymentStatus->value);
        }

        if (is_string($at)) {
            $referenceNumber = $referenceNumber ?? $at;
            $at = new DateTimeImmutable();
        }

        $at = $at ?? new DateTimeImmutable();
        if ($referenceNumber !== null) {
            $this->paymentReference = $referenceNumber;
        }

        $this->status->assertCanTransitionTo(OrderStatus::Paid, $this->id ?? 0, $this->uuid);

        $this->status = OrderStatus::Paid;
        $this->paymentStatus = PaymentStatus::Paid;
        $this->receiptStatus = 'approved';
        $this->receiptVerifiedBy = $verifiedByUserId;
        $this->receiptVerifiedAt = $at;
        $this->paidAt = $at;
        $this->reservationExpiresAt = null; // Fulfill reservation permanently
        $this->seenByCustomer = false;
        $this->updatedAt = $at;

        // 1. Payment receipt approved event (triggers balanced double-entry accounting)
        $this->recordEvent(new PaymentReceiptApprovedEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            grandTotal: $this->price->finalTotal,
            subtotal: $this->price->subtotal,
            taxAmount: $this->price->taxAmount,
            shippingCost: $this->price->shippingCost,
            verifiedByUserId: $verifiedByUserId,
            verifiedAt: $at,
            discountAmount: $this->price->discountAmount,
            occurredAt: $at
        ));

        // 2. Order paid event
        $this->recordEvent(new OrderPaidEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            paidTotal: $this->price->finalTotal,
            paymentMethod: $this->paymentMethod,
            verifiedByUserId: $verifiedByUserId,
            paidAt: $at,
            occurredAt: $at
        ));
    }

    /**
     * Rejects payment receipt with explanation.
     */
    public function rejectPayment(string $reason, int $verifiedByUserId, ?DateTimeImmutable $at = null): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidOrderStateTransitionException::fromTransition(
                $this->id ?? 0,
                $this->uuid,
                $this->status->value,
                'rejected_payment'
            );
        }

        if ($this->status === OrderStatus::Paid || $this->paymentStatus === PaymentStatus::Paid) {
            throw PaymentAlreadyVerifiedException::forOrder($this->id ?? 0, $this->paymentStatus->value);
        }

        $at = $at ?? new DateTimeImmutable();
        $this->paymentStatus = PaymentStatus::Rejected;
        $this->receiptStatus = 'rejected';
        $this->paymentRejectReason = $reason;
        $this->receiptRejectionReason = $reason;
        $this->receiptVerifiedBy = $verifiedByUserId;
        $this->receiptVerifiedAt = $at;
        $this->seenByCustomer = false;
        $this->updatedAt = $at;

        $this->recordEvent(new PaymentReceiptRejectedEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            rejectionReason: $reason,
            rejectedByUserId: $verifiedByUserId,
            rejectedAt: $at,
            occurredAt: $at
        ));
    }

    /**
     * Transitions order to Shipped with tracking code.
     */
    public function ship(
        PostalTracking|string $tracking,
        string $shippingMethod = 'پست پیشتاز',
        ?DateTimeImmutable $at = null,
        ?int $shippedByUserId = null
    ): void {
        $at = $at ?? new DateTimeImmutable();
        $this->status->assertCanTransitionTo(OrderStatus::Shipped, $this->id ?? 0, $this->uuid);

        $postal = is_string($tracking) ? PostalTracking::from($tracking, $shippingMethod) : $tracking;
        if ($postal->code === null || trim($postal->code) === '') {
            throw new PostalTrackingCodeInvalidException('کد رهگیری پستی جهت ارسال کالا الزامی است.');
        }

        $this->status = OrderStatus::Shipped;
        $this->postalTracking = $postal;
        $this->shippedAt = $at;
        $this->seenByCustomer = false;
        $this->updatedAt = $at;

        $this->recordEvent(new OrderShippedEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            trackingCode: $postal->code,
            shippingMethod: $postal->shippingMethod,
            shippedByUserId: $shippedByUserId ?? 0,
            shippedAt: $at,
            occurredAt: $at
        ));
    }

    /**
     * Marks order delivery completed (Terminal state).
     */
    public function complete(?DateTimeImmutable $at = null): void
    {
        $at = $at ?? new DateTimeImmutable();
        $this->status->assertCanTransitionTo(OrderStatus::Completed, $this->id ?? 0, $this->uuid);

        $this->status = OrderStatus::Completed;
        $this->completedAt = $at;
        $this->deliveredAt = $at;
        $this->updatedAt = $at;

        $this->recordEvent(new OrderCompletedEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            completedAt: $at,
            occurredAt: $at
        ));
    }

    /**
     * Cancels order and triggers stock restoration (Terminal state).
     */
    public function cancel(
        string $reason,
        ?DateTimeImmutable $at = null,
        ?int $canceledByUserId = null
    ): void {
        $at = $at ?? new DateTimeImmutable();
        $this->status->assertCanTransitionTo(OrderStatus::Canceled, $this->id ?? 0, $this->uuid);

        $this->status = OrderStatus::Canceled;
        $this->cancellationReason = $reason;
        $this->canceledAt = $at;
        $this->updatedAt = $at;

        $restoredItems = [];
        foreach ($this->items as $item) {
            $restoredItems[] = [
                'product_id' => $item->getProductId() ?? 0,
                'quantity' => $item->getQuantity(),
                'unit_cost' => $item->getUnitCostPrice(),
            ];
        }

        $this->recordEvent(new OrderCanceledEvent(
            eventId: bin2hex(random_bytes(16)),
            orderId: $this->id ?? 0,
            orderUuid: $this->uuid,
            shopId: $this->shopId,
            customerId: $this->customerId,
            cancellationReason: $reason,
            restoredItems: $restoredItems,
            canceledByUserId: $canceledByUserId,
            canceledAt: $at,
            occurredAt: $at
        ));
    }

    public function transitionTo(OrderStatus $target): void
    {
        $this->status->assertCanTransitionTo($target, $this->id ?? 0, $this->uuid);
        $this->status = $target;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addItem(OrderItem $item): void
    {
        if ($this->status->isTerminal()) {
            throw new \LogicException(sprintf('امکان تغییر اقلام سفارش در وضعیت نهایی %s وجود ندارد.', $this->status->value));
        }
        $this->items[] = $item;
    }

    public function calculateTotals(): PriceSnapshot
    {
        if ($this->status->isTerminal()) {
            throw new \LogicException(sprintf('امکان محاسبه مجدد مبالغ سفارش در وضعیت نهایی %s وجود ندارد.', $this->status->value));
        }

        $subtotal = 0.0;
        $taxAmount = 0.0;
        foreach ($this->items as $item) {
            $subtotal += $item->getLineTotal();
            $taxAmount += $item->getTaxAmount();
        }

        $this->price = new PriceSnapshot(
            subtotal: $subtotal,
            shippingCost: $this->price->shippingCost,
            taxAmount: $taxAmount,
            discountAmount: $this->price->discountAmount
        );

        return $this->price;
    }

    // Accessors
    public function getUuid(): string { return $this->uuid; }
    public function getOrderNumber(): string { return $this->uuid; }
    public function getShopId(): int { return $this->shopId; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getPrice(): PriceSnapshot { return $this->price; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getPaymentStatus(): PaymentStatus { return $this->paymentStatus; }
    public function getBranchId(): ?int { return $this->branchId; }
    public function getShippingGroupId(): ?int { return $this->shippingGroupId; }
    public function getCreatedByType(): string { return $this->createdByType; }
    public function getCreatedById(): ?int { return $this->createdById; }
    public function getPaymentCardId(): ?int { return $this->paymentCardId; }
    public function getPaymentReceiptPath(): ?string { return $this->paymentReceiptPath; }
    public function getPaymentReference(): ?string { return $this->paymentReference; }
    public function getPaymentRejectReason(): ?string { return $this->paymentRejectReason; }
    public function getPaidAt(): ?DateTimeImmutable { return $this->paidAt; }
    public function getReservationExpiresAt(): ?DateTimeImmutable { return $this->reservationExpiresAt; }
    public function getPostalTracking(): PostalTracking { return $this->postalTracking; }
    public function getShippedAt(): ?DateTimeImmutable { return $this->shippedAt; }
    public function getDeliveredAt(): ?DateTimeImmutable { return $this->deliveredAt; }
    public function getCompletedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function getCanceledAt(): ?DateTimeImmutable { return $this->canceledAt; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function getFinalisedAt(): ?DateTimeImmutable { return $this->finalisedAt; }
    public function getAddressId(): ?int { return $this->addressId; }
    public function getAddressSnapshot(): AddressSnapshot { return $this->addressSnapshot; }
    public function getCustomerSnapshot(): CustomerSnapshot { return $this->customerSnapshot; }
    public function getDescription(): ?string { return $this->description; }
    public function getReceiptStatus(): string { return $this->receiptStatus; }
    public function getReceiptVerifiedBy(): ?int { return $this->receiptVerifiedBy; }
    public function getReceiptVerifiedAt(): ?DateTimeImmutable { return $this->receiptVerifiedAt; }
    public function getReceiptRejectionReason(): ?string { return $this->receiptRejectionReason; }
    public function isSeenByCustomer(): bool { return $this->seenByCustomer; }
    public function isSeenByAdmin(): bool { return $this->seenByAdmin; }
    /** @return array<OrderItem> */
    public function getItems(): array { return $this->items; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
}
