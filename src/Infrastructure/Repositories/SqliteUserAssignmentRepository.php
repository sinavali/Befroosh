<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\Role;
use App\Domain\MultiTenancy\UserAssignment;
use App\Domain\Repositories\UserAssignmentRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of UserAssignmentRepositoryInterface.
 */
final class SqliteUserAssignmentRepository implements UserAssignmentRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?UserAssignment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branch_user_assignments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUserAndBranch(int $userId, int $branchId): ?UserAssignment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branch_user_assignments WHERE user_id = ? AND branch_id = ? LIMIT 1');
        $stmt->execute([$userId, $branchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<UserAssignment>
     */
    public function findByBusinessId(int $businessId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branch_user_assignments WHERE business_id = ? ORDER BY id ASC');
        $stmt->execute([$businessId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<UserAssignment>
     */
    public function findByBranchId(int $branchId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branch_user_assignments WHERE branch_id = ? ORDER BY id ASC');
        $stmt->execute([$branchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<UserAssignment>
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branch_user_assignments WHERE user_id = ? ORDER BY id ASC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countManagersByBusinessId(int $businessId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM branch_user_assignments 
            WHERE business_id = ? 
              AND role IN ('branch_manager', 'manager', 'shop_manager')
        ");
        $stmt->execute([$businessId]);

        return (int)$stmt->fetchColumn();
    }

    public function save(UserAssignment $assignment): UserAssignment
    {
        $now = date('Y-m-d H:i:s');

        if ($assignment->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO branch_user_assignments (
                    user_id, branch_id, business_id, role, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $assignment->getUserId(),
                $assignment->getBranchId(),
                $assignment->getBusinessId(),
                $assignment->getRole()->value,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $assignment->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE branch_user_assignments SET
                    user_id = ?, branch_id = ?, business_id = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $assignment->getUserId(),
                $assignment->getBranchId(),
                $assignment->getBusinessId(),
                $assignment->getRole()->value,
                $assignment->getId()
            ]);
        }

        return $assignment;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM branch_user_assignments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteByUserAndBranch(int $userId, int $branchId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM branch_user_assignments WHERE user_id = ? AND branch_id = ?');
        $stmt->execute([$userId, $branchId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UserAssignment
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new UserAssignment(
            userId: (int)$row['user_id'],
            branchId: (int)$row['branch_id'],
            businessId: (int)$row['business_id'],
            role: Role::from((string)$row['role']),
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
