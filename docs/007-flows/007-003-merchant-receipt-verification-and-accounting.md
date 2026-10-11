---
id: product:befroosh/flows/merchant-receipt-verification-and-accounting
parent: product:befroosh/flows
title: Flow 4: Merchant Receipt Verification & Double-Entry Accounting
level: product
kind: blueprint
domains: [payments, orders, catalog]
flows: [receipt-verification-and-accounting]
keywords: [flow, verification, receipt, approve, accounting, ledger]
references: [product:befroosh/decisions/double-entry-accounting-ledger, product:befroosh/contracts/payment-receipt-contract]
code_paths: [src/Application/UseCases/Payment/VerifyPaymentReceiptHandler.php, src/Application/Events/Subscribers/GenerateLedgerOnPaymentApproved.php]
test_paths: [tests/challenge_double_entry.php, tests/challenge_order_lifecycle.php]
---

# Flow 4: Merchant Receipt Verification & Double-Entry Accounting

## Purpose
Provides merchant staff with a split-view audit interface to inspect bank transfer slips, approving or rejecting them while automatically issuing balanced accounting journal entries.

## Participants
- `payments`: Bank slip OCR checks, double-entry general ledger
- `orders`: Order state transition (`paid`), customer notification
- `catalog`: Final commitment of reserved stock

## Steps
1. Merchant staff inspects "Pending Slips" queue on `/merchant/orders`.
2. Staff clicks "Audit Receipt" opening side-by-side inspection modal (slip scan on left, order total and bank card on right).
3. If approved, staff clicks "Approve Receipt & Post Ledger":
   - Order transitions to `status = 'paid'`, `payment_status = 'paid'`, `receipt_status = 'verified'`.
   - Reserved stock is committed to physical deduction (`Product::commitReservation`).
   - Domain subscriber automatically generates balanced accounting voucher (Debit `cash_bank`, Credit `sales_revenue`, `shipping_revenue`, `tax_payable`).
4. If rejected, staff enters reason and clicks "Reject Receipt":
   - Order transitions to `payment_status = 'rejected'`, customer is notified to re-upload receipt.
