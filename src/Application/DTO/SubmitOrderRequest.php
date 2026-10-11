<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Data Transfer Object for order submission requests.
 */
final readonly class SubmitOrderRequest
{
    /**
     * @param array<array{product_id: int, quantity: float}> $items
     */
    public function __construct(
        public int $shopId,
        public int $customerId,
        public int $addressId,
        public array $items,
        public string $paymentMethod = 'card_to_card',
        public ?int $paymentCardId = null,
        public ?string $description = null,
        public string $createdByType = 'customer',
        public ?int $createdById = null,
        public ?int $branchId = null,
        public ?int $shippingGroupId = null
    ) {}
}
