---
id: product:befroosh/decisions/four-day-stock-reservation
parent: product:befroosh/decisions
title: ADR 002: 96-Hour Inventory Reservation Engine
level: product
kind: decision
domains: [orders, catalog]
keywords: [adr, inventory, stock-reservation, 96-hour, order-lifecycle]
references: [product:befroosh/blueprint/invariants]
code_paths: [src/Domain/Order/Order.php, src/Application/UseCases/Order/ReleaseExpiredReservationsHandler.php]
test_paths: [tests/test_domain_invariants.php]
---

# ADR 002: 96-Hour Inventory Reservation Engine

## Context
In offline Iranian commerce, customers place orders and transfer money via bank card within hours or days. If stock is not reserved, double-selling occurs. If stock is reserved indefinitely, abandoned orders lock inventory forever.

## Decision
1. When an order is submitted, calculate `reservation_expires_at = now() + 96 hours`.
2. Lock units in `products.reserved_stock`. Available stock is decremented immediately.
3. An automated background worker executes `ReleaseExpiredReservationsHandler`: any unpaid order where `reservation_expires_at < now()` is canceled, emitting `ReservationExpiredEvent` and returning stock.

## Consequences
- **Positive**: Eliminates stock hoarding and overselling.
- **Positive**: Transparent customer countdown timer on checkout and order tracking portals.
