<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Payment\BankCard;
use App\Domain\Repositories\BankCardRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of BankCardRepositoryInterface.
 */
final class SqliteBankCardRepository implements BankCardRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?BankCard
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shop_bank_cards WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<BankCard>
     */
    public function findByShopId(int $shopId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM shop_bank_cards WHERE shop_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$shopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(BankCard $card): BankCard
    {
        $now = date('Y-m-d H:i:s');

        if ($card->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO shop_bank_cards (
                    shop_id, card_number, card_holder, bank_name, shaba_number, active, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $card->getShopId(),
                $card->getCardNumber(),
                $card->getCardHolder(),
                $card->getBankName(),
                $card->getShabaNumber(),
                $card->isActive() ? 1 : 0,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $card->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE shop_bank_cards SET
                    shop_id = ?, card_number = ?, card_holder = ?, bank_name = ?,
                    shaba_number = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $card->getShopId(),
                $card->getCardNumber(),
                $card->getCardHolder(),
                $card->getBankName(),
                $card->getShabaNumber(),
                $card->isActive() ? 1 : 0,
                $card->getId()
            ]);
        }

        return $card;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM shop_bank_cards WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BankCard
    {
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;

        return new BankCard(
            shopId: (int)$row['shop_id'],
            cardNumber: (string)$row['card_number'],
            cardHolder: (string)$row['card_holder'],
            bankName: isset($row['bank_name']) ? (string)$row['bank_name'] : null,
            shabaNumber: isset($row['shaba_number']) ? (string)$row['shaba_number'] : null,
            active: (bool)($row['active'] ?? 1),
            createdAt: $createdAt,
            id: (int)$row['id']
        );
    }
}
