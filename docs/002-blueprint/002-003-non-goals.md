---
id: product:befroosh/blueprint/non-goals
parent: product:befroosh/blueprint
title: Non-Goals & Architectural Boundaries
level: product
kind: blueprint
domains: [orders, payments, catalog]
keywords: [non-goals, limits, boundaries, constraints]
references: [product:befroosh/blueprint/overview]
---

# Non-Goals & Architectural Boundaries

To maintain high velocity, clean architecture, and operational simplicity, the following are explicit non-goals:

1. **International Fiat Currencies**: Befroosh strictly operates in Iranian Toman / Rial. Multi-currency exchange rate conversions are out of scope.
2. **Third-Party Enterprise ERP Sync**: The platform does not support heavy bi-directional sync with enterprise ERPs (e.g. SAP, Microsoft Dynamics). Financial data is exported via standard balanced trial balance and CSV reports.
3. **Synchronous Payment Gateways as Sole Dependency**: The core architecture must not depend on real-time internet payment gateways (IPG) being online; offline Card-to-Card is first-class.
4. **Microservice Splitting**: Befroosh operates as a unified, highly optimized Domain-Driven Design application powered by SQLite WAL mode and PHP 8.4. Distributed microservice transactions are non-goals.
