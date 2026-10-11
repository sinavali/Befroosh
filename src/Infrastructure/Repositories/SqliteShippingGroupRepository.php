<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\ShippingGroup;
use App\Domain\Repositories\ShippingGroupRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of ShippingGroupRepositoryInterface.
 */
final class SqliteShippingGroupRepository implements ShippingGroupRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?ShippingGroup
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shipping_groups WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<ShippingGroup>
     */
    public function findByBusinessId(int $businessId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shipping_groups WHERE business_id = ? ORDER BY id ASC');
        $stmt->execute([$businessId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ShippingGroup $shippingGroup): ShippingGroup
    {
        $now = date('Y-m-d H:i:s');

        if ($shippingGroup->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO shipping_groups (
                    business_id, name, shipping_cost, free_shipping_threshold, central_hub_address, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $shippingGroup->getBusinessId(),
                $shippingGroup->getName(),
                $shippingGroup->getShippingCost(),
                $shippingGroup->getFreeShippingThreshold(),
                $shippingGroup->getCentralHubAddress(),
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $shippingGroup->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE shipping_groups SET
                    business_id = ?, name = ?, shipping_cost = ?,
                    free_shipping_threshold = ?, central_hub_address = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $shippingGroup->getBusinessId(),
                $shippingGroup->getName(),
                $shippingGroup->getShippingCost(),
                $shippingGroup->getFreeShippingThreshold(),
                $shippingGroup->getCentralHubAddress(),
                $shippingGroup->getId()
            ]);
        }

        return $shippingGroup;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM shipping_groups WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ShippingGroup
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new ShippingGroup(
            businessId: (int)$row['business_id'],
            name: (string)$row['name'],
            shippingCost: (float)($row['shipping_cost'] ?? 250000.0),
            freeShippingThreshold: (float)($row['free_shipping_threshold'] ?? 2000000.0),
            centralHubAddress: isset($row['central_hub_address']) ? (string)$row['central_hub_address'] : null,
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
