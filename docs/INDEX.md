---
id: product:befroosh/docs
title: Documentation Map
level: product
kind: blueprint
---

# Documentation Map

Routing only. Which domains exist, which flows exist, and which domains each flow spans.
This file stays tiny. It is the first thing an orchestrator reads.

## Domains

- `catalog` — Product definitions, inventory stock counts, SKU, categories, bookmarks
- `multi-tenancy` — Businesses (`shops`), branches, logistics shipping groups, user assignments
- `orders` — 5-stage order lifecycle, purchase limits, addresses, Iran Post barcodes
- `payments` — Card-to-Card offline bank slips, receipt audit modal, double-entry ledger vouchers
- `support` — Threaded customer tickets, order attachments, SuperAdmin VIP escalation

## Flows

- `guest-sync` — Anonymous shopping, local cart caching, and auto-sync on registration
- `checkout-card-to-card` — Multi-step checkout, 4-day reservation lock, bank transfer receipt upload
- `receipt-verification-and-accounting` — Merchant slip verification and balanced ledger voucher creation
- `reservation-expiry` — 96-hour stock reservation expiration and inventory replenishment
- `iran-post-dispatch` — 24-digit Iran Post barcode assignment and shipment dispatch
- `support-ticket-escalation` — Customer ticketing and Ultimate-tier SuperAdmin SLA escalation

## Cross-domain flows

- `guest-sync` spans: `catalog`, `orders`
- `checkout-card-to-card` spans: `orders`, `catalog`, `payments`
- `receipt-verification-and-accounting` spans: `payments`, `orders`, `catalog`
- `reservation-expiry` spans: `orders`, `catalog`
- `iran-post-dispatch` spans: `orders`, `multi-tenancy`
- `support-ticket-escalation` spans: `support`, `orders`, `multi-tenancy`
