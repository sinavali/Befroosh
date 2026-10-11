<?php

declare(strict_types=1);

namespace App\Application\Events\Subscribers;

use App\Domain\Events\PaymentReceiptApprovedEvent;
use App\Domain\Payment\LedgerTransaction;
use App\Domain\Repositories\LedgerRepositoryInterface;

/**
 * Domain Event Subscriber: Automatically generates balanced double-entry accounting records
 * when a payment receipt is approved.
 */
final readonly class GenerateLedgerOnPaymentApproved
{
    public function __construct(
        private LedgerRepositoryInterface $ledgerRepository
    ) {}

    public function __invoke(PaymentReceiptApprovedEvent $event): void
    {
        $tx = LedgerTransaction::forOrderPayment(
            shopId: $event->shopId,
            orderId: $event->orderId,
            orderUuid: $event->orderUuid,
            subtotal: $event->subtotal,
            taxAmount: $event->taxAmount,
            shippingCost: $event->shippingCost,
            discountAmount: $event->discountAmount,
            grandTotal: $event->grandTotal,
            verifierId: $event->verifiedByUserId,
            date: $event->verifiedAt
        );

        $this->ledgerRepository->saveTransaction($tx);
    }
}
