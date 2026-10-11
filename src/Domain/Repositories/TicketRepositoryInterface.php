<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Support\Ticket;

interface TicketRepositoryInterface
{
    public function findById(int $id): ?Ticket;

    /**
     * @return array<Ticket>
     */
    public function findByCustomerId(int $customerId, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<Ticket>
     */
    public function findByShopId(int $shopId, ?string $status = null, int $limit = 50, int $offset = 0): array;

    /**
     * Finds tickets elevated to Superadmin.
     * Gated exclusively to Ultimate subscription tier or Superadmin staff.
     *
     * @return array<Ticket>
     */
    public function findEscalatedToSuperadmin(int $limit = 50, int $offset = 0): array;

    public function save(Ticket $ticket): Ticket;

    public function delete(int $id): void;
}
