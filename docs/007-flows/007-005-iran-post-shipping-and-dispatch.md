---
id: product:befroosh/flows/iran-post-shipping-and-dispatch
parent: product:befroosh/flows
title: Flow 6: Packaging, 24-Digit Postal Code Assignment & Dispatch
level: product
kind: blueprint
domains: [orders, multi-tenancy]
flows: [iran-post-dispatch]
keywords: [flow, shipping, iran-post, tracking, barcode, dispatch]
references: [product:befroosh/blueprint/invariants]
code_paths: [src/Application/UseCases/Order/ShipOrderHandler.php, routes_orders_process.php]
test_paths: [tests/test_application_usecases.php, test_e2e_archetypes.php]
---

# Flow 6: Packaging, 24-Digit Postal Code Assignment & Dispatch

## Purpose
Enables warehouse operators to package paid orders, generate Iran Post dispatch labels, strictly validate 24-digit barcodes, and update tracking steppers.

## Participants
- `orders`: 24-digit barcode validation, status transition to `shipped`
- `multi-tenancy`: Sender address from fulfilling branch or shipping group hub

## Steps
1. Warehouse operator views paid orders and clicks "Print Shipping Label" (`/orders/{id}/shipping-label`).
2. Package is prepared and dispatched to Iran Post.
3. Operator enters 24-digit tracking number via `POST /orders/{id}/ship`.
4. `ShipOrderHandler` validates the barcode format using `PostalTracking`.
5. Order transitions to `status = 'shipped'`, and customer tracking portal renders active milestone stepper and external link to `tracking.post.ir`.
