---
id: product:befroosh/flows/checkout-and-card-to-card-payment
parent: product:befroosh/flows
title: Flow 2: Multi-Step Checkout, 4-Day Reservation & Card-to-Card Payment
level: product
kind: blueprint
domains: [orders, catalog, payments]
flows: [checkout-card-to-card]
keywords: [flow, checkout, reservation, card-to-card, receipt-upload]
references: [product:befroosh/blueprint/invariants, product:befroosh/contracts/order-api]
code_paths: [src/Application/UseCases/Order/SubmitOrderHandler.php, routes_orders_create.php]
test_paths: [tests/test_application_usecases.php, test_e2e_archetypes.php]
---

# Flow 2: Multi-Step Checkout, 4-Day Reservation & Card-to-Card Payment

## Purpose
Orchestrates order placement, inventory reservation for 96 hours, bank details display, and payment slip upload.

## Participants
- `catalog`: Stock reservation and anti-hoarding limit enforcement
- `orders`: Order submission, price snapshot, 4-day reservation timer
- `payments`: Destination bank card accounts and receipt image upload

## Steps
1. Customer enters `/checkout`, selects delivery address, and reviews order breakdown.
2. `SubmitOrderHandler` validates available stock, min/max caps, snapshots price/address, and reserves inventory.
3. Order created with `status = 'submitted'`, `payment_status = 'unpaid'`, and countdown timer displayed (`96 hours`).
4. Customer transfers money via mobile banking to the displayed `shop_bank_cards` account.
5. Customer uploads screenshot receipt and enters bank reference number (`POST /orders/{id}/payment/card-to-card`).
6. Order transitions to `payment_status = 'pending_verification'`, alerting merchant staff.

## Failure Modes
- `InsufficientStockException`: Order rejected if on-hand units were claimed.
- `PurchaseLimitExceededException`: Order rejected if customer exceeded 30-day limits.
