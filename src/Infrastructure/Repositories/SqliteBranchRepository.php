<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\Branch;
use App\Domain\Repositories\BranchRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of BranchRepositoryInterface.
 */
final class SqliteBranchRepository implements BranchRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Branch
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByBusinessAndSlug(int $businessId, string $slug): ?Branch
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE business_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$businessId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Branch>
     */
    public function findByBusinessId(int $businessId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE business_id = ? ORDER BY is_main DESC, id ASC');
        $stmt->execute([$businessId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Branch>
     */
    public function findActiveByBusinessId(int $businessId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE business_id = ? AND active = 1 ORDER BY is_main DESC, id ASC');
        $stmt->execute([$businessId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findMainBranch(int $businessId): ?Branch
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE business_id = ? AND is_main = 1 LIMIT 1');
        $stmt->execute([$businessId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function countActiveByBusinessId(int $businessId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM branches WHERE business_id = ? AND active = 1');
        $stmt->execute([$businessId]);

        return (int)$stmt->fetchColumn();
    }

    public function existsBySlug(int $businessId, string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM branches WHERE business_id = ? AND slug = ? AND id != ?');
            $stmt->execute([$businessId, $slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM branches WHERE business_id = ? AND slug = ?');
            $stmt->execute([$businessId, $slug]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    public function save(Branch $branch): Branch
    {
        $now = date('Y-m-d H:i:s');

        if ($branch->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO branches (
                    business_id, name, slug, logo_path, phone, address,
                    catalog_mode, shipping_group_id, default_shipping_cost,
                    free_shipping_threshold, is_main, active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $branch->getBusinessId(),
                $branch->getName(),
                $branch->getSlug(),
                $branch->getLogoPath(),
                $branch->getPhone(),
                $branch->getAddress(),
                $branch->getCatalogMode(),
                $branch->getShippingGroupId(),
                $branch->getDefaultShippingCost(),
                $branch->getFreeShippingThreshold(),
                $branch->isMain() ? 1 : 0,
                $branch->isActive() ? 1 : 0,
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $branch->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE branches SET
                    business_id = ?, name = ?, slug = ?, logo_path = ?, phone = ?, address = ?,
                    catalog_mode = ?, shipping_group_id = ?, default_shipping_cost = ?,
                    free_shipping_threshold = ?, is_main = ?, active = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $branch->getBusinessId(),
                $branch->getName(),
                $branch->getSlug(),
                $branch->getLogoPath(),
                $branch->getPhone(),
                $branch->getAddress(),
                $branch->getCatalogMode(),
                $branch->getShippingGroupId(),
                $branch->getDefaultShippingCost(),
                $branch->getFreeShippingThreshold(),
                $branch->isMain() ? 1 : 0,
                $branch->isActive() ? 1 : 0,
                $now,
                $branch->getId()
            ]);
        }

        return $branch;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM branches WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Branch
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
        $updatedAt = !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null;

        return new Branch(
            businessId: (int)$row['business_id'],
            name: (string)$row['name'],
            slug: (string)$row['slug'],
            logoPath: isset($row['logo_path']) ? (string)$row['logo_path'] : null,
            phone: isset($row['phone']) ? (string)$row['phone'] : null,
            address: isset($row['address']) ? (string)$row['address'] : null,
            catalogMode: (string)($row['catalog_mode'] ?? 'both'),
            shippingGroupId: isset($row['shipping_group_id']) ? (int)$row['shipping_group_id'] : null,
            defaultShippingCost: (float)($row['default_shipping_cost'] ?? 250000.0),
            freeShippingThreshold: (float)($row['free_shipping_threshold'] ?? 2000000.0),
            isMain: (bool)($row['is_main'] ?? 0),
            active: (bool)($row['active'] ?? 1),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            id: (int)$row['id']
        );
    }
}
