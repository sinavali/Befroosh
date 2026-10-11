<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\TicketRepositoryInterface;
use App\Domain\Support\Ticket;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of TicketRepositoryInterface.
 */
final class SqliteTicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Ticket>
     */
    public function findByCustomerId(int $customerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE customer_id = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $customerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Ticket>
     */
    public function findByShopId(int $shopId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM tickets WHERE shop_id = ?';
        $params = [$shopId];

        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

        $stmt = $this->pdo->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Finds tickets elevated to Superadmin.
     *
     * @return array<Ticket>
     */
    public function findEscalatedToSuperadmin(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tickets 
            WHERE is_escalated_to_superadmin = 1 OR is_elevated_to_superadmin = 1 
            ORDER BY id DESC LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(Ticket $ticket): Ticket
    {
        $now = date('Y-m-d H:i:s');
        $isEscalated = $ticket->isEscalatedToSuperadmin() ? 1 : 0;

        if ($ticket->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO tickets (
                    shop_id, customer_id, subject, body, status,
                    created_by_type, created_by_id, is_escalated_to_superadmin,
                    is_elevated_to_superadmin, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    'customer', ?, ?,
                    ?, ?, ?
                )
            ");
            $stmt->execute([
                $ticket->getShopId(),
                $ticket->getCustomerId(),
                $ticket->getTitle(),
                $ticket->getTitle(),
                $ticket->getStatus(),
                $ticket->getCustomerId(),
                $isEscalated,
                $isEscalated,
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $ticket->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE tickets SET
                    shop_id = ?, customer_id = ?, subject = ?, status = ?,
                    is_escalated_to_superadmin = ?, is_elevated_to_superadmin = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $ticket->getShopId(),
                $ticket->getCustomerId(),
                $ticket->getTitle(),
                $ticket->getStatus(),
                $isEscalated,
                $isEscalated,
                $now,
                $ticket->getId()
            ]);
        }

        return $ticket;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Ticket
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
        $updatedAt = !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null;
        $isEscalated = (bool)($row['is_escalated_to_superadmin'] ?? $row['is_elevated_to_superadmin'] ?? 0);

        return new Ticket(
            shopId: (int)($row['shop_id'] ?? 0),
            customerId: (int)$row['customer_id'],
            title: (string)($row['subject'] ?? $row['title'] ?? ''),
            status: (string)($row['status'] ?? Ticket::STATUS_OPEN),
            priority: (string)($row['priority'] ?? 'medium'),
            isEscalatedToSuperadmin: $isEscalated,
            assignedToUserId: isset($row['assigned_to_user_id']) ? (int)$row['assigned_to_user_id'] : null,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            id: (int)$row['id']
        );
    }
}
