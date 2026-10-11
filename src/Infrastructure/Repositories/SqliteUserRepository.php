<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\Role;
use App\Domain\MultiTenancy\User;
use App\Domain\Repositories\UserRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of UserRepositoryInterface.
 */
final class SqliteUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPhone(string $phone): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<User>
     */
    public function findByShopId(int $shopId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE shop_id = ? ORDER BY id ASC');
        $stmt->execute([$shopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @param array<string> $roles
     * @return array<User>
     */
    public function findByShopIdAndRoles(int $shopId, array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE shop_id = ? AND role IN ($placeholders) ORDER BY id ASC");
        $params = array_merge([$shopId], $roles);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->execute([$username]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    public function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE phone = ? AND id != ?');
            $stmt->execute([$phone, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    public function countByShopId(int $shopId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE shop_id = ? AND active = 1');
        $stmt->execute([$shopId]);

        return (int)$stmt->fetchColumn();
    }

    public function save(User $user): User
    {
        $now = date('Y-m-d H:i:s');

        if ($user->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    shop_id, username, password, role, nickname, phone, email, active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $user->getShopId(),
                $user->getUsername(),
                $user->getPasswordHash(),
                $user->getRole()->value,
                $user->getNickname(),
                $user->getPhone(),
                $user->getEmail(),
                $user->isActive() ? 1 : 0,
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $user->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE users SET
                    shop_id = ?, username = ?, password = ?, role = ?,
                    nickname = ?, phone = ?, email = ?, active = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $user->getShopId(),
                $user->getUsername(),
                $user->getPasswordHash(),
                $user->getRole()->value,
                $user->getNickname(),
                $user->getPhone(),
                $user->getEmail(),
                $user->isActive() ? 1 : 0,
                $now,
                $user->getId()
            ]);
        }

        return $user;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        $role = Role::from((string)$row['role']);
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new User(
            username: (string)$row['username'],
            nickname: (string)($row['nickname'] ?? $row['username']),
            role: $role,
            passwordHash: (string)($row['password'] ?? ''),
            phone: isset($row['phone']) ? (string)$row['phone'] : null,
            email: isset($row['email']) ? (string)$row['email'] : null,
            shopId: isset($row['shop_id']) ? (int)$row['shop_id'] : null,
            active: (bool)($row['active'] ?? 1),
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
