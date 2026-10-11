<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Order\Address;
use App\Domain\Repositories\AddressRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of AddressRepositoryInterface.
 */
final class SqliteAddressRepository implements AddressRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Address
    {
        $stmt = $this->pdo->prepare('SELECT * FROM addresses WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Address>
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findDefaultByUserId(int $userId): ?Address
    {
        $stmt = $this->pdo->prepare('SELECT * FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function clearDefaultForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function save(Address $address): Address
    {
        $now = date('Y-m-d H:i:s');

        if ($address->isDefault()) {
            $this->clearDefaultForUser($address->getUserId());
        }

        if ($address->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO addresses (
                    user_id, state, city, address, recipient_name, recipient_phone,
                    postal_code, description, is_default, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $address->getUserId(),
                $address->getState(),
                $address->getCity(),
                $address->getAddress(),
                $address->getRecipientName(),
                $address->getRecipientPhone(),
                $address->getPostalCode(),
                $address->getDescription(),
                $address->isDefault() ? 1 : 0,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $address->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE addresses SET
                    user_id = ?, state = ?, city = ?, address = ?,
                    recipient_name = ?, recipient_phone = ?, postal_code = ?,
                    description = ?, is_default = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $address->getUserId(),
                $address->getState(),
                $address->getCity(),
                $address->getAddress(),
                $address->getRecipientName(),
                $address->getRecipientPhone(),
                $address->getPostalCode(),
                $address->getDescription(),
                $address->isDefault() ? 1 : 0,
                $address->getId()
            ]);
        }

        return $address;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM addresses WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Address
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new Address(
            userId: (int)$row['user_id'],
            state: (string)$row['state'],
            city: (string)$row['city'],
            address: (string)$row['address'],
            recipientName: isset($row['recipient_name']) ? (string)$row['recipient_name'] : null,
            recipientPhone: isset($row['recipient_phone']) ? (string)$row['recipient_phone'] : null,
            postalCode: isset($row['postal_code']) ? (string)$row['postal_code'] : null,
            description: isset($row['description']) ? (string)$row['description'] : null,
            isDefault: (bool)($row['is_default'] ?? 0),
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
