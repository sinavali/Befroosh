---
id: product:befroosh/cross-cutting/subscription-quotas
parent: product:befroosh/cross-cutting
title: Subscription Plan Quotas & VIP Escalation SLA
level: product
kind: cross-cutting
domains: [multi-tenancy, support]
keywords: [subscription, plans, quotas, limits, tiers, escalation]
code_paths: [src/Domain/MultiTenancy/SubscriptionPlan.php, routes_subscription_plans.php]
test_paths: [tests/test_domain_invariants.php, tests/test_application_usecases.php]
---

# Subscription Plan Quotas & VIP Escalation SLA

## 4-Tier Plan Hierarchy

| Tier | Branches | Products | Staff Managers | Accounting | VIP Ticket Escalation |
|---|---|---|---|---|---|
| **Free** | 1 | 50 | 1 | No | No |
| **Plus** | 3 | 500 | 3 | Basic | No |
| **Pro** | 10 | Unlimited | 10 | Full Double-Entry | No |
| **Ultimate** | Unlimited | Unlimited | Unlimited | Full Double-Entry | **Yes (Emergency SLA)** |

Enforced at the domain level via `SubscriptionLimitExceededException`.
