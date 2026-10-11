---
id: product:befroosh/cross-cutting/multi-tenancy-and-rbac
parent: product:befroosh/cross-cutting
title: Multi-Tenancy Scoping & Branch Isolation
level: product
kind: cross-cutting
domains: [multi-tenancy, catalog, orders]
keywords: [multi-tenancy, tenant, branches, rbac, catalog-mode, isolation]
code_paths: [src/Domain/MultiTenancy/**, routes_branches.php]
test_paths: [test.php]
---

# Multi-Tenancy Scoping & Branch Isolation

1. **Tenant Scoping**: All queries within merchant panels are automatically scoped by `shop_id`.
2. **Catalog Isolation Modes**:
   - `business_only`: Branch displays products belonging to the parent business.
   - `branch_only`: Branch displays only products explicitly assigned to `branch_id`.
   - `both`: Blends parent store products with local branch items.
3. **Staff Assignments**: Managers are elevated via mobile phone and assigned to specific branches via `branch_user_assignments`.
