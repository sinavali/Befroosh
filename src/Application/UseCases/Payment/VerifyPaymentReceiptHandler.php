<?php

declare(strict_types=1);

namespace App\Application\UseCases\Payment;

use App\Application\DTO\VerifyPaymentReceiptRequest;
use App\Application\DTO\VerifyPaymentReceiptResponse;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Use-Case: Verifies offline Card-to-Card payment receipts (approve/reject).
 * On approval, dispatches PaymentReceiptApprovedEvent which automatically
 * generates balanced double-entry accounting records.
 */
final readonly class VerifyPaymentReceiptHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(VerifyPaymentReceiptRequest $request): VerifyPaymentReceiptResponse
    {
        return $this->transactionManager->transactional(function () use ($request) {
            $order = $this->orderRepository->findById($request->orderId);
            if ($order === null) {
                throw EntityNotFoundException::forEntity('Order', $request->orderId);
            }

            $now = new DateTimeImmutable();

            if ($request->action === 'approve') {
                $order->approvePayment($request->verifierUserId, $now);
            } elseif ($request->action === 'reject') {
                $reason = $request->rejectionReason ?? 'فیش واریزی نامعتبر است.';
                $order->rejectPayment($reason, $request->verifierUserId, $now);
            } else {
                throw new InvalidArgumentException(sprintf('عملیات اعتبارسنجی نامعتبر: %s (باید approve یا reject باشد).', $request->action));
            }

            $savedOrder = $this->orderRepository->save($order);

            // Dispatch domain events (triggers GenerateLedgerOnPaymentApproved on approval)
            $events = $savedOrder->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return new VerifyPaymentReceiptResponse(
                orderId: $savedOrder->getId() ?? $request->orderId,
                uuid: $savedOrder->getUuid(),
                status: $savedOrder->getStatus()->value,
                paymentStatus: $savedOrder->getPaymentStatus()->value,
                receiptStatus: $savedOrder->getReceiptStatus(),
                verifiedAt: $savedOrder->getReceiptVerifiedAt(),
                rejectionReason: $savedOrder->getReceiptRejectionReason()
            );
        });
    }
}
