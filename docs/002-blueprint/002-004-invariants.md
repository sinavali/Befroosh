---
id: product:befroosh/blueprint/invariants
parent: product:befroosh/blueprint
title: Core Domain Invariants
level: product
kind: blueprint
domains: [catalog, orders, payments, multi-tenancy]
keywords: [invariants, business rules, limits, caps, double-entry, reservation]
references: [product:befroosh/blueprint/overview]
code_paths: [src/Domain/Catalog/Product.php, src/Domain/Order/Order.php, src/Domain/Payment/LedgerTransaction.php]
test_paths: [tests/test_domain_invariants.php, tests/test_adversarial_domain_invariants.php]
---

# Core Domain Invariants

The following invariants MUST NEVER be violated:

1. **Available Stock Formula**: Available inventory is strictly $Stock_{physical} - Stock_{reserved}$. Orders cannot be submitted if requested quantity exceeds available stock (`InsufficientStockException`).
2. **4-Day (96-Hour) Reservation Lifetime**: Unpaid submitted orders lock stock for exactly 96 hours. After 96 hours, reservations expire and return stock to physical inventory. Active reservations cannot be prematurely canceled without authorization (`OrderReservationNotExpiredException`).
3. **Double-Entry Equilibrium**: Every general journal voucher MUST enforce $\sum Debit = \sum Credit$. Any discrepancy, even $\Delta \ge 0.01$, throws `UnbalancedLedgerException`.
4. **Triple-Tier Anti-Hoarding Caps**: Product purchases are capped at three levels: minimum order quantity (`min_order_qty`), maximum units per order (`max_per_order`), and rolling 30-day customer limit (`max_per_month`).
5. **Postal Tracking Validation**: Iran Post tracking codes MUST be exactly 24 numeric digits (`PostalTrackingCodeInvalidException`).
6. **Payment Idempotency**: Verified orders cannot be approved twice (`PaymentAlreadyVerifiedException`).
