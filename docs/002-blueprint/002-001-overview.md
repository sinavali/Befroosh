---
id: product:befroosh/blueprint/overview
parent: product:befroosh/blueprint
title: Platform Intent & Architectural Overview
level: product
kind: blueprint
domains: [catalog, multi-tenancy, orders, payments, support]
keywords: [overview, architecture, ddd, multi-tenant, iran, ecommerce]
references: [product:befroosh/blueprint/invariants]
---

# Platform Intent & Architectural Overview

Befroosh is a specialized Iranian multi-tenant e-commerce platform engineered specifically around the operational realities of domestic Iranian commerce:

1. **Card-to-Card Offline Payments**: In Iran, traditional payment gateways frequently experience throttling, high commission rates, or tax friction. Befroosh empowers merchants with Card-to-Card bank transfer workflows, receipt upload dropzones, and visual optical verification queues.
2. **4-Day (96-Hour) Inventory Reservations**: When customers submit an order without instant online settlement, physical inventory is locked for exactly 96 hours. If payment receipt is verified, stock is committed; if the reservation expires without payment, inventory is automatically restored.
3. **Iran Post 24-Digit Tracking**: Direct native integration with Iran Post barcodes linking to statutory tracking portals (`tracking.post.ir`).
4. **Balanced Double-Entry Financial Accounting**: An internal general ledger enforces strict mathematical equilibrium ($\sum Debit = \sum Credit$) on every verified payment, inventory intake, and damage write-off.
