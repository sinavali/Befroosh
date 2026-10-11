<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Data Transfer Object for payment verification requests (approve/reject).
 */
final readonly class VerifyPaymentReceiptRequest
{
    public function __construct(
        public int $orderId,
        public int $verifierUserId,
        public string $action, // 'approve' | 'reject'
        public ?string $rejectionReason = null
    ) {}
}
