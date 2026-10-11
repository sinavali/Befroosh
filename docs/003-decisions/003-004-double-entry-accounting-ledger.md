---
id: product:befroosh/decisions/double-entry-accounting-ledger
parent: product:befroosh/decisions
title: ADR 004: Balanced Double-Entry General Ledger
level: product
kind: decision
domains: [payments, orders]
keywords: [adr, accounting, ledger, double-entry, trial-balance, cogs]
references: [product:befroosh/blueprint/invariants]
code_paths: [src/Domain/Payment/DoubleEntryLedgerEntry.php, src/Domain/Payment/LedgerTransaction.php, src/Application/Events/Subscribers/GenerateLedgerOnPaymentApproved.php]
test_paths: [tests/challenge_double_entry.php]
---

# ADR 004: Balanced Double-Entry General Ledger

## Context
Merchants require statutory financial tracking for Iranian tax reporting (9% VAT) and operational profit/loss visibility.

## Decision
1. Enforce a built-in double-entry general journal table (`accounting_ledger`).
2. Verification of order payment automatically triggers `GenerateLedgerOnPaymentApproved` subscriber:
   - Debit `1010` (Cash/Bank)
   - Debit `4090` (Sales Discounts)
   - Credit `4010` (Sales Revenue)
   - Credit `4020` (Shipping Revenue)
   - Credit `2050` (9% VAT Tax Payable)
3. Inventory intake automatically debits `1050` (Inventory Asset) and credits `1010` (Cash/Bank).
4. Every transaction group MUST balance ($\sum Debit = \sum Credit$).

## Consequences
- **Positive**: Instant 4-column trial balance and quarterly VAT reports without external accounting software.
- **Positive**: Any corrupt or unbalanced entry throws `UnbalancedLedgerException`.
