<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Data Transfer Object for canceling an order.
 */
final readonly class CancelOrderRequest
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $userRole,
        public string $reason = 'لغو توسط کاربر'
    ) {}
}
