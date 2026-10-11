<?php

declare(strict_types=1);

namespace App\Application\UseCases\MultiTenancy;

use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\SubscriptionLimitExceededException;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TicketRepositoryInterface;
use App\Domain\Support\Ticket;

/**
 * Use-Case: Escalates a shop ticket to SuperAdmin, enforcing that ticket escalation
 * is restricted to Ultimate tier subscription plans.
 */
final readonly class EscalateTicketHandler
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
        private ShopRepositoryInterface $shopRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository
    ) {}

    public function handle(int $ticketId, int $elevatingUserId): Ticket
    {
        $ticket = $this->ticketRepository->findById($ticketId);
        if ($ticket === null) {
            throw EntityNotFoundException::forEntity('Ticket', $ticketId);
        }

        $shopId = $ticket->getShopId();
        if ($shopId === null) {
            throw new \LogicException('تنها تیکت‌های مربوط به فروشگاه امکان ارجاع دارند.');
        }

        $shop = $this->shopRepository->findById($shopId);
        if ($shop === null) {
            throw EntityNotFoundException::forEntity('Shop', $shopId);
        }

        $plan = $this->subscriptionPlanRepository->findById($shop->getSubscriptionPlanId())
            ?? $this->subscriptionPlanRepository->findDefaultPlan();

        if (!$plan->hasTicketEscalation()) {
            throw SubscriptionLimitExceededException::forTicketEscalation($shopId, $plan->getSlug());
        }

        $ticket->escalateToSuperadmin($elevatingUserId);

        return $this->ticketRepository->save($ticket);
    }
}
