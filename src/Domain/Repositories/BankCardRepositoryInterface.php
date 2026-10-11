<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Payment\BankCard;

interface BankCardRepositoryInterface
{
    public function findById(int $id): ?BankCard;

    /**
     * @return array<BankCard>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true): array;

    public function save(BankCard $card): BankCard;

    public function delete(int $id): void;
}
