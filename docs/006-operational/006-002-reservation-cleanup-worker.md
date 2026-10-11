---
id: product:befroosh/operational/reservation-cleanup-worker
parent: product:befroosh/operational
title: 96-Hour Reservation Expiry Background Worker
level: product
kind: operational
domains: [orders, catalog]
keywords: [worker, cron, reservation, cleanup, expiry, scheduler]
code_paths: [src/Application/UseCases/Order/ReleaseExpiredReservationsHandler.php]
test_paths: [tests/test_application_usecases.php]
---

# 96-Hour Reservation Expiry Background Worker

## Execution
Runs periodically (every 10 minutes via cron or systemd timer):

```bash
php -r "require 'bootstrap.php'; (new Befroosh\Application\UseCases\Order\ReleaseExpiredReservationsHandler())->handle();"
```

## Functionality
1. Queries `orders` where `status = 'submitted'` AND `payment_status = 'unpaid'` AND `reservation_expires_at < datetime('now')`.
2. For each expired order:
   - Emits `ReservationExpiredEvent`.
   - Restores reserved units in `products.reserved_stock`.
   - Transitions order to `canceled` with reason `'انقضای مهلت رزرو ۴ روزه'`.
