<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\Shop;
use App\Domain\Repositories\ShopRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of ShopRepositoryInterface.
 */
final class SqliteShopRepository implements ShopRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Shop
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shops WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Shop
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shops WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Shop>
     */
    public function findByOwnerId(int $ownerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shops WHERE owner_id = ? ORDER BY id ASC');
        $stmt->execute([$ownerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Shop>
     */
    public function findAllActive(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shops WHERE active = 1 ORDER BY id ASC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function existsBySlug(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM shops WHERE slug = ? AND id != ?');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM shops WHERE slug = ?');
            $stmt->execute([$slug]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM shops')->fetchColumn();
    }

    public function save(Shop $shop): Shop
    {
        $methods = json_encode($shop->getPaymentMethods(), JSON_UNESCAPED_UNICODE);
        $expiresAt = $shop->getSubscriptionExpiresAt()?->format('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');

        if ($shop->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO shops (
                    name, slug, owner_id, phone, email, address,
                    national_id, economic_code, card_number, card_holder, bank_name, shaba_number,
                    card_to_card_enabled, reservation_days, payment_methods, tax_rate,
                    default_shipping_cost, free_shipping_threshold, active,
                    subscription_plan_id, subscription_expires_at, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $shop->getName(),
                $shop->getSlug(),
                $shop->getOwnerId(),
                $shop->getPhone(),
                $shop->getEmail(),
                $shop->getAddress(),
                $shop->getNationalId(),
                $shop->getEconomicCode(),
                $shop->getCardNumber(),
                $shop->getCardHolder(),
                $shop->getBankName(),
                $shop->getShabaNumber(),
                $shop->isCardToCardEnabled() ? 1 : 0,
                $shop->getReservationDays(),
                $methods,
                $shop->getTaxRate(),
                $shop->getDefaultShippingCost(),
                $shop->getFreeShippingThreshold(),
                $shop->isActive() ? 1 : 0,
                $shop->getSubscriptionPlanId(),
                $expiresAt,
                $now,
                $now
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $shop->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE shops SET
                    name = ?, slug = ?, owner_id = ?, phone = ?, email = ?, address = ?,
                    national_id = ?, economic_code = ?, card_number = ?, card_holder = ?, bank_name = ?, shaba_number = ?,
                    card_to_card_enabled = ?, reservation_days = ?, payment_methods = ?, tax_rate = ?,
                    default_shipping_cost = ?, free_shipping_threshold = ?, active = ?,
                    subscription_plan_id = ?, subscription_expires_at = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $shop->getName(),
                $shop->getSlug(),
                $shop->getOwnerId(),
                $shop->getPhone(),
                $shop->getEmail(),
                $shop->getAddress(),
                $shop->getNationalId(),
                $shop->getEconomicCode(),
                $shop->getCardNumber(),
                $shop->getCardHolder(),
                $shop->getBankName(),
                $shop->getShabaNumber(),
                $shop->isCardToCardEnabled() ? 1 : 0,
                $shop->getReservationDays(),
                $methods,
                $shop->getTaxRate(),
                $shop->getDefaultShippingCost(),
                $shop->getFreeShippingThreshold(),
                $shop->isActive() ? 1 : 0,
                $shop->getSubscriptionPlanId(),
                $expiresAt,
                $now,
                $shop->getId()
            ]);
        }

        return $shop;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM shops WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Shop
    {
        $methods = !empty($row['payment_methods']) ? json_decode((string)$row['payment_methods'], true) : ['card_to_card'];
        if (!is_array($methods)) {
            $methods = ['card_to_card'];
        }

        $expiresAt = !empty($row['subscription_expires_at']) ? new DateTimeImmutable((string)$row['subscription_expires_at']) : null;
        $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
        $updatedAt = !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null;

        return new Shop(
            name: (string)$row['name'],
            slug: (string)$row['slug'],
            ownerId: isset($row['owner_id']) ? (int)$row['owner_id'] : null,
            phone: isset($row['phone']) ? (string)$row['phone'] : null,
            email: isset($row['email']) ? (string)$row['email'] : null,
            address: isset($row['address']) ? (string)$row['address'] : null,
            nationalId: isset($row['national_id']) ? (string)$row['national_id'] : null,
            economicCode: isset($row['economic_code']) ? (string)$row['economic_code'] : null,
            cardNumber: isset($row['card_number']) ? (string)$row['card_number'] : null,
            cardHolder: isset($row['card_holder']) ? (string)$row['card_holder'] : null,
            bankName: isset($row['bank_name']) ? (string)$row['bank_name'] : null,
            shabaNumber: isset($row['shaba_number']) ? (string)$row['shaba_number'] : null,
            cardToCardEnabled: (bool)($row['card_to_card_enabled'] ?? 1),
            reservationDays: (int)($row['reservation_days'] ?? 4),
            paymentMethods: $methods,
            taxRate: (float)($row['tax_rate'] ?? 0.0),
            defaultShippingCost: (float)($row['default_shipping_cost'] ?? 250000.0),
            freeShippingThreshold: (float)($row['free_shipping_threshold'] ?? 2000000.0),
            active: (bool)($row['active'] ?? 1),
            subscriptionPlanId: (int)($row['subscription_plan_id'] ?? 1),
            subscriptionExpiresAt: $expiresAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            id: (int)$row['id']
        );
    }
}
