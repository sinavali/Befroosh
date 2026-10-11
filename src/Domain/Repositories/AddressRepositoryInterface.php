<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Order\Address;

interface AddressRepositoryInterface
{
    public function findById(int $id): ?Address;

    /**
     * @return array<Address>
     */
    public function findByUserId(int $userId): array;

    public function findDefaultByUserId(int $userId): ?Address;

    public function clearDefaultForUser(int $userId): void;

    public function save(Address $address): Address;

    public function delete(int $id): void;
}
