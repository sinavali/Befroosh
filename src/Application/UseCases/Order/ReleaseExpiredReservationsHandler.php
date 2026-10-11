<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;

/**
 * Use-Case: Sweeps through expired submitted orders and releases reserved stock.
 */
final readonly class ReleaseExpiredReservationsHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @return array{released_count: int, expired_order_ids: array<int>}
     */
    public function handle(?DateTimeImmutable $now = null): array
    {
        $currentTime = $now ?? new DateTimeImmutable();

        return $this->transactionManager->transactional(function () use ($currentTime) {
            $expiredOrders = $this->orderRepository->findExpiredSubmittedOrders($currentTime);
            $releasedIds = [];

            foreach ($expiredOrders as $order) {
                if (!$order->isReservationExpired($currentTime)) {
                    continue;
                }

                $order->releaseReservation($currentTime);
                $this->orderRepository->save($order);

                $events = $order->releaseEvents();
                $this->eventDispatcher->dispatchAll($events);

                if ($order->getId() !== null) {
                    $releasedIds[] = $order->getId();
                }
            }

            return [
                'released_count' => count($releasedIds),
                'expired_order_ids' => $releasedIds,
            ];
        });
    }
}
