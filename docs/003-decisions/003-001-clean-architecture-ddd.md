---
id: product:befroosh/decisions/clean-architecture-ddd
parent: product:befroosh/decisions
title: ADR 001: Adoption of Pure Domain-Driven Design & SQLite WAL
level: product
kind: decision
domains: [catalog, orders, payments, multi-tenancy, support]
keywords: [adr, ddd, clean-architecture, sqlite, wal, architecture]
references: [product:befroosh/blueprint/overview]
code_paths: [src/Domain/**, src/Application/**, src/Infrastructure/**]
test_paths: [test.php]
---

# ADR 001: Adoption of Pure Domain-Driven Design & SQLite WAL

## Context
The legacy application coupled database SQL queries and presentation logic inside routing files (`routes_*.php`). To scale multi-tenant operations, prevent race conditions in inventory reservations, and guarantee financial consistency, a decoupled architecture was required.

## Decision
1. Isolate the pure **Domain Layer** (`src/Domain/`) free from framework, database, or HTTP dependencies.
2. Introduce an **Application Layer** (`src/Application/`) coordinating use-case handlers (`SubmitOrderHandler`, `VerifyPaymentReceiptHandler`, etc.) and domain event subscribers.
3. Use **SQLite 3 in Write-Ahead Logging (WAL) Mode** with `PRAGMA foreign_keys = ON` as the primary persistence engine.
4. Maintain a dual-mode presentation layer (`src/Http/Controllers/`, `routes_api.php`) serving both JSON REST and server-rendered views.

## Consequences
- **Positive**: 100% testable domain logic (153/153 tests pass in ~1.5s without network or web server).
- **Positive**: Database writes are atomic, thread-safe, and self-contained in `storage/database.sqlite`.
