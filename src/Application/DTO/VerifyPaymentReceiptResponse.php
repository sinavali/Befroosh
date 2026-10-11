<?php

declare(strict_types=1);

namespace App\Application\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object for payment verification responses.
 */
final readonly class VerifyPaymentReceiptResponse
{
    public function __construct(
        public int $orderId,
        public string $uuid,
        public string $status,
        public string $paymentStatus,
        public string $receiptStatus,
        public ?DateTimeImmutable $verifiedAt,
        public ?string $rejectionReason = null
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
            'payment_status' => $this->paymentStatus,
            'receipt_status' => $this->receiptStatus,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejectionReason,
        ];
    }
}
