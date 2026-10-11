<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\MultiTenancy\SubscriptionPlan;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use PDO;

/**
 * SQLite implementation of SubscriptionPlanRepositoryInterface.
 */
final class SqliteSubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?SubscriptionPlan
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<SubscriptionPlan>
     */
    public function findAllActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_irt ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findDefaultPlan(): SubscriptionPlan
    {
        $plan = $this->findBySlug('free');
        if ($plan !== null) {
            return $plan;
        }

        return new SubscriptionPlan(
            id: 1,
            slug: 'free',
            name: 'طرح پایه رایگان',
            priceIrt: 0.0,
            maxBusinesses: 1,
            maxBranches: 0,
            maxProducts: 50,
            maxManagers: 0,
            hasTicketing: false,
            hasOnlinePayment: false,
            hasTicketEscalation: false,
            reportingYears: 1,
            allowCustomDomain: false,
            allowSmsAlerts: false,
            isActive: true
        );
    }

    public function save(SubscriptionPlan $plan): SubscriptionPlan
    {
        if ($plan->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO subscription_plans (
                    slug, name, price_irt, max_businesses, max_branches,
                    max_products, max_managers, has_ticketing, has_online_payment,
                    has_ticket_escalation, reporting_years, is_active
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");
            $stmt->execute([
                $plan->getSlug(),
                $plan->getName(),
                $plan->getPriceIrt(),
                $plan->getMaxBusinesses(),
                $plan->getMaxBranches(),
                $plan->getMaxProducts(),
                $plan->getMaxManagers(),
                $plan->hasTicketing() ? 1 : 0,
                $plan->hasOnlinePayment() ? 1 : 0,
                $plan->canEscalateTickets() ? 1 : 0,
                $plan->getReportingYears(),
                $plan->isActive() ? 1 : 0,
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $plan->setId($id);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE subscription_plans SET
                    slug = ?, name = ?, price_irt = ?, max_businesses = ?, max_branches = ?,
                    max_products = ?, max_managers = ?, has_ticketing = ?, has_online_payment = ?,
                    has_ticket_escalation = ?, reporting_years = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $plan->getSlug(),
                $plan->getName(),
                $plan->getPriceIrt(),
                $plan->getMaxBusinesses(),
                $plan->getMaxBranches(),
                $plan->getMaxProducts(),
                $plan->getMaxManagers(),
                $plan->hasTicketing() ? 1 : 0,
                $plan->hasOnlinePayment() ? 1 : 0,
                $plan->canEscalateTickets() ? 1 : 0,
                $plan->getReportingYears(),
                $plan->isActive() ? 1 : 0,
                $plan->getId()
            ]);
        }

        return $plan;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): SubscriptionPlan
    {
        return new SubscriptionPlan(
            id: (int)$row['id'],
            slug: (string)$row['slug'],
            name: (string)$row['name'],
            priceIrt: (float)($row['price_irt'] ?? 0.0),
            maxBusinesses: (int)($row['max_businesses'] ?? 1),
            maxBranches: (int)($row['max_branches'] ?? 0),
            maxProducts: (int)($row['max_products'] ?? 50),
            maxManagers: (int)($row['max_managers'] ?? 0),
            hasTicketing: (bool)($row['has_ticketing'] ?? 0),
            hasOnlinePayment: (bool)($row['has_online_payment'] ?? 0),
            hasTicketEscalation: (bool)($row['has_ticket_escalation'] ?? 0),
            reportingYears: (int)($row['reporting_years'] ?? 1),
            allowCustomDomain: (bool)($row['allow_custom_domain'] ?? 0),
            allowSmsAlerts: (bool)($row['allow_sms_alerts'] ?? 0),
            isActive: (bool)($row['is_active'] ?? 1)
        );
    }
}
