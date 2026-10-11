---
id: product:befroosh/decisions/offline-card-to-card-workflow
parent: product:befroosh/decisions
title: ADR 003: Offline Card-to-Card Receipt Verification Workflow
level: product
kind: decision
domains: [payments, orders]
keywords: [adr, card-to-card, receipt, bank-transfer, verification-modal]
references: [product:befroosh/blueprint/invariants]
code_paths: [src/Domain/Payment/BankCard.php, src/Domain/Payment/CardReceipt.php, src/Application/UseCases/Payment/VerifyPaymentReceiptHandler.php]
test_paths: [tests/challenge_order_lifecycle.php]
---

# ADR 003: Offline Card-to-Card Receipt Verification Workflow

## Context
Direct card-to-card bank transfers are the dominant payment method for boutique and social commerce in Iran. Automating bank reconciliation requires either fragile SMS parsers or trusted manual slip audits.

## Decision
1. Maintain active merchant bank card accounts in `shop_bank_cards` with card number, holder, and Sheba IBAN.
2. Customers upload receipts (PNG, JPG, PDF) with the bank transaction reference number.
3. Merchant operators inspect receipts in a side-by-side split-view modal with zoom toggles and verification badges.
4. Approval commits stock and automatically emits `PaymentReceiptApprovedEvent`. Rejection requires a mandatory reason.

## Consequences
- **Positive**: High trust, zero gateway transaction fees, works with any Iranian bank.
- **Positive**: Clear audit trail with stored receipt images.
