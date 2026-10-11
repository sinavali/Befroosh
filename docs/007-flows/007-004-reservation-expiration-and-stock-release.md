---
id: product:befroosh/flows/reservation-expiration-and-stock-release
parent: product:befroosh/flows
title: Flow 5: Automated 4-Day Reservation Expiry & Stock Restoration
level: product
kind: blueprint
domains: [orders, catalog]
flows: [reservation-expiry]
keywords: [flow, expiry, reservation, cancel, restore, stock]
references: [product:befroosh/decisions/four-day-stock-reservation]
code_paths: [src/Application/UseCases/Order/ReleaseExpiredReservationsHandler.php, src/Application/Events/Subscribers/ReleaseStockOnOrderCanceled.php]
test_paths: [tests/test_application_usecases.php]
---

# Flow 5: Automated 4-Day Reservation Expiry & Stock Restoration

## Purpose
Automates the detection and cancellation of unpaid orders after 96 hours, returning reserved inventory to available stock without human intervention.

## Participants
- `orders`: Expiration scanner, state transition to `canceled`
- `catalog`: Reserved stock release and replenishment

## Steps
1. Background worker triggers `ReleaseExpiredReservationsHandler::handle()`.
2. System queries for orders where `status = 'submitted'` AND `payment_status = 'unpaid'` AND `reservation_expires_at < now()`.
3. For each match, `Order::cancel()` is invoked with reason `'انقضای مهلت رزرو ۴ روزه'`.
4. `ReservationExpiredEvent` and `OrderCanceledEvent` are dispatched.
5. `ReleaseStockOnOrderCanceled` subscriber decrements `products.reserved_stock`, restoring available inventory.
