<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Application\DTO\CancelOrderRequest;
use App\Application\DTO\CancelOrderResponse;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedDomainActionException;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;

/**
 * Use-Case: Cancels an active order and emits cancellation events
 * (which automatically restores reserved/physical inventory).
 */
final readonly class CancelOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(CancelOrderRequest $request): CancelOrderResponse
    {
        return $this->transactionManager->transactional(function () use ($request) {
            $order = $this->orderRepository->findById($request->orderId);
            if ($order === null) {
                throw EntityNotFoundException::forEntity('Order', $request->orderId);
            }

            // Authorization: customer can only cancel their own order; staff can cancel shop orders
            $isCustomer = $request->userRole === 'customer';
            if ($isCustomer && $order->getCustomerId() !== $request->userId) {
                throw new UnauthorizedDomainActionException('شما تنها مجاز به لغو سفارشات خود هستید.');
            }

            $now = new DateTimeImmutable();
            $order->cancel($request->reason, $now, $request->userId);

            $savedOrder = $this->orderRepository->save($order);

            // Dispatch domain events (triggers ReleaseStockOnOrderCanceled subscriber)
            $events = $savedOrder->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return new CancelOrderResponse(
                orderId: $savedOrder->getId() ?? $request->orderId,
                uuid: $savedOrder->getUuid(),
                status: $savedOrder->getStatus()->value,
                canceledAt: $savedOrder->getCanceledAt() ?? $now,
                reason: $savedOrder->getCancellationReason() ?? $request->reason,
                itemsRestoredCount: count($savedOrder->getItems())
            );
        });
    }
}
