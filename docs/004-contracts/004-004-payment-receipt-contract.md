---
id: product:befroosh/contracts/payment-receipt-contract
parent: product:befroosh/contracts
title: Payment Receipt Upload & Verification Contract
level: product
kind: contract
parties: [customer-portal, merchant-verification-queue]
domains: [payments, orders]
flows: [receipt-verification-and-accounting]
keywords: [api, payment, card-to-card, receipt, verify, approve, reject]
implements: [flow:receipt-verification-and-accounting]
code_paths: [src/Application/UseCases/Payment/VerifyPaymentReceiptHandler.php, routes_orders_process.php]
test_paths: [tests/challenge_order_lifecycle.php]
---

# Payment Receipt Upload & Verification Contract

## Endpoints

| Method | Path | Request | Response |
|---|---|---|---|
| `POST` | `/orders/{id}/payment/card-to-card` | Multipart form (`receipt_file`, `receipt_ref`, `bank_card_id`) | 302 Redirect to `/orders/{id}` with success notice |
| `POST` | `/orders/{id}/payment/verify` | Form (`action: approve\|reject`, `rejection_reason`) | 302 Redirect to `/merchant/orders` |

## Verification Actions
- `approve`: Commits reserved stock, marks order `paid`, emits `PaymentReceiptApprovedEvent`, generates balanced double-entry vouchers.
- `reject`: Requires non-empty `rejection_reason`, sets `payment_status = 'rejected'`, notifies customer to re-upload.
