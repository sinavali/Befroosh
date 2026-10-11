<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Order\Order;
use DateTimeImmutable;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findByUuid(string $uuid): ?Order;

    public function findByTrackingCode(string $trackingCode): ?Order;

    /**
     * @return array<Order>
     */
    public function findByCustomerId(int $customerId, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<Order>
     */
    public function findByShopId(
        int $shopId,
        ?string $status = null,
        ?string $paymentStatus = null,
        int $limit = 50,
        int $offset = 0
    ): array;

    /**
     * Identifies submitted unpaid orders past their 4-day reservation window.
     *
     * @return array<Order>
     */
    public function findExpiredSubmittedOrders(DateTimeImmutable $thresholdDateTime): array;

    /**
     * Calculates cumulative units bought by customer in rolling 30-day window for non-canceled orders.
     */
    public function calculate30DayPurchasedQuantity(int $customerId, int $productId): float;

    public function countByShopAndStatus(int $shopId, string $status): int;

    public function existsByUuid(string $uuid): bool;

    public function save(Order $order): Order;
}
