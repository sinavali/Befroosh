---
id: product:befroosh/contracts/order-api
parent: product:befroosh/contracts
title: Order REST API Contract
level: product
kind: contract
parties: [checkout-frontend, order-service]
domains: [orders, catalog]
flows: [checkout-card-to-card, reservation-expiry]
keywords: [api, orders, submit, checkout, cancel, json]
implements: [flow:checkout-card-to-card]
code_paths: [src/Http/Controllers/OrderApiController.php, src/Application/UseCases/Order/SubmitOrderHandler.php]
test_paths: [tests/test_application_usecases.php]
---

# Order REST API Contract

## Endpoints

| Method | Path | Request | Response |
|---|---|---|---|
| `POST` | `/api/v1/orders` | `SubmitOrderRequest` JSON | `{"status": "success", "order_id": 12, "uuid": "ORD-...", "expires_at": "..."}` |
| `GET` | `/api/v1/orders/{id}` | Order ID | Order detail JSON snapshot |
| `POST` | `/api/v1/orders/{id}/cancel` | `{"reason": "..."}` | `{"status": "success", "message": "Order canceled and stock restored"}` |

## Schemas

### SubmitOrderRequest
```json
{
  "shop_id": 1,
  "branch_id": 2,
  "address_id": 5,
  "items": [
    {"product_id": 101, "quantity": 1}
  ]
}
```
