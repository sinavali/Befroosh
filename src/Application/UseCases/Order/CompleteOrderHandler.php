<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Order\Order;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;

/**
 * Use-Case: Completes order delivery and transitions to terminal Completed status.
 */
final readonly class CompleteOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(int $orderId): Order
    {
        return $this->transactionManager->transactional(function () use ($orderId) {
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw EntityNotFoundException::forEntity('Order', $orderId);
            }

            $now = new DateTimeImmutable();
            $order->complete($now);

            $savedOrder = $this->orderRepository->save($order);

            $events = $savedOrder->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return $savedOrder;
        });
    }
}
