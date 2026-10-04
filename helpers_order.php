<?php
declare(strict_types=1);

/**
 * helpers_order.php
 * Order state workflow, stock reservation management, and tracking utilities
 */

function order_status_label(string $status): string
{
    $labels = [
        'submitted' => 'ثبت‌شده (در انتظار بررسی)',
        'paid' => 'تأیید پرداخت (در حال آماده‌سازی)',
        'shipped' => 'ارسال‌شده به پست',
        'completed' => 'تکمیل و تحویل‌شده',
        'canceled' => 'لغو‌شده',
        'placed' => 'ثبت اولیه',
        'finalised' => 'نهایی‌شده',
    ];
    return $labels[$status] ?? $status;
}

function payment_status_label(string $status): string
{
    $labels = [
        'unpaid' => 'در انتظار پرداخت و فیش',
        'paid' => 'پرداخت تأیید شد',
        'rejected' => 'فیش نامعتبر / رد شده',
    ];
    return $labels[$status] ?? $status;
}

function generate_tracking_code(): string
{
    return '24' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT) . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT) . str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function process_expired_reservations(): int
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id, shop_id FROM orders 
            WHERE status = 'submitted' 
              AND reservation_expires_at IS NOT NULL 
              AND reservation_expires_at < datetime('now')
        ");
        $stmt->execute();
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($expired as $ord) {
            $ordId = (int)$ord['id'];
            release_order_inventory($ordId);
            $pdo->prepare("
                UPDATE orders 
                SET status = 'canceled', 
                    cancellation_reason = 'انقضای مهلت واریز و رزرو کالا', 
                    canceled_at = datetime('now'),
                    updated_at = datetime('now')
                WHERE id = ?
            ")->execute([$ordId]);
            $count++;
        }
        return $count;
    } catch (Throwable $e) {
        return 0;
    }
}

function reserve_order_inventory(int $orderId): void
{
    global $pdo;
    try {
        $items = $pdo->query("SELECT product_id, quantity FROM order_items WHERE order_id = {$orderId}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['quantity'];
            $pdo->prepare("UPDATE products SET reserved_quantity = reserved_quantity + ? WHERE id = ?")->execute([$qty, $pId]);
        }
    } catch (Throwable $e) {}
}

function release_order_inventory(int $orderId): void
{
    global $pdo;
    try {
        $items = $pdo->query("SELECT product_id, quantity FROM order_items WHERE order_id = {$orderId}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['quantity'];
            $pdo->prepare("UPDATE products SET reserved_quantity = MAX(0, reserved_quantity - ?) WHERE id = ?")->execute([$qty, $pId]);
        }
    } catch (Throwable $e) {}
}

function fulfill_order_inventory(int $orderId, int $shopId, ?int $userId = null): void
{
    global $pdo;
    try {
        $items = $pdo->query("SELECT product_id, quantity, unit_price, unit_cost_price FROM order_items WHERE order_id = {$orderId}")->fetchAll(PDO::FETCH_ASSOC);
        $insInv = $pdo->prepare("
            INSERT INTO inventory_transactions (shop_id, product_id, type, quantity, unit_cost, reference_type, reference_id, notes, created_by_id)
            VALUES (?, ?, 'outward_order', ?, ?, 'order', ?, 'تحویل سفارش و کسر از موجودی', ?)
        ");

        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['quantity'];
            $cost = (float)($item['unit_cost_price'] ?: ($item['unit_price'] * 0.75));

            $pdo->prepare("
                UPDATE products 
                SET stock_quantity = MAX(0, stock_quantity - ?),
                    reserved_quantity = MAX(0, reserved_quantity - ?)
                WHERE id = ?
            ")->execute([$qty, $qty, $pId]);

            $insInv->execute([$shopId, $pId, $qty, $cost, $orderId, $userId]);
        }
    } catch (Throwable $e) {}
}

function record_order_accounting_entry(int $orderId, int $shopId, float $subtotal, float $shipping, float $tax, ?int $userId = null): void
{
    global $pdo;
    try {
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("
            INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description, created_by_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Sale revenue
        if ($subtotal > 0) {
            $ins->execute([$shopId, $now, 'sale_revenue', $orderId, 0, $subtotal, 'درآمد حاصل از فروش کالا', "سفارش #{$orderId}", $userId]);
        }
        // Shipping revenue
        if ($shipping > 0) {
            $ins->execute([$shopId, $now, 'shipping_revenue', $orderId, 0, $shipping, 'درآمد هزینه ارسال', "کرایه حمل سفارش #{$orderId}", $userId]);
        }
        // Tax payable
        if ($tax > 0) {
            $ins->execute([$shopId, $now, 'tax_payable', $orderId, 0, $tax, 'مالیات بر ارزش افزوده پرداختنی', "مالیات سفارش #{$orderId}", $userId]);
        }
        // Payment received
        $total = $subtotal + $shipping + $tax;
        if ($total > 0) {
            $ins->execute([$shopId, $now, 'payment_received', $orderId, $total, 0, 'موجودی نقد و بانک', "وصول فیش سفارش #{$orderId}", $userId]);
        }
    } catch (Throwable $e) {}
}

function record_inventory_tx(
    int $shopId,
    int $productId,
    string $type,
    float $quantity,
    ?float $unitCost = null,
    ?string $referenceType = null,
    ?int $referenceId = null,
    ?string $notes = null,
    ?int $userId = null
): void {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO inventory_transactions (shop_id, product_id, type, quantity, unit_cost, reference_type, reference_id, notes, created_by_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$shopId, $productId, $type, $quantity, $unitCost, $referenceType, $referenceId, $notes, $userId]);

    $delta = in_array($type, ['inward', 'adjustment_plus', 'return_in'], true) ? $quantity : -$quantity;
    $upd = $pdo->prepare("UPDATE products SET stock_quantity = MAX(0, stock_quantity + ?) WHERE id = ?");
    $upd->execute([$delta, $productId]);
}

function record_accounting_entry(
    int $shopId,
    string $entryType,
    ?int $orderId,
    float $debit,
    float $credit,
    string $account,
    string $description,
    ?int $userId = null,
    ?string $date = null
): void {
    global $pdo;
    $date = $date ?: gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description, created_by_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$shopId, $date, $entryType, $orderId, $debit, $credit, $account, $description, $userId]);
}

function release_expired_reservations(): int
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT id, shop_id, uuid FROM orders 
        WHERE status = 'submitted' 
        AND reservation_expires_at IS NOT NULL 
        AND reservation_expires_at < ?
    ");
    $stmt->execute([$now]);
    $expiredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($expiredOrders as $order) {
        $items = $pdo->prepare("SELECT product_id, quantity, unit_cost_price FROM order_items WHERE order_id = ?");
        $items->execute([$order['id']]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
            record_inventory_tx(
                (int)$order['shop_id'],
                (int)$it['product_id'],
                'return_in',
                (float)$it['quantity'],
                (float)$it['unit_cost_price'],
                'reservation_expired',
                (int)$order['id'],
                'لغو خودکار به دلیل انقضای مهلت رزرو کالا'
            );
        }

        $pdo->prepare("
            UPDATE orders 
            SET status = 'canceled', cancellation_reason = 'انقضای مهلت رزرو ۴ روزه و عدم پرداخت', canceled_at = datetime('now'), updated_at = datetime('now') 
            WHERE id = ?
        ")->execute([$order['id']]);
        $count++;
    }
    return $count;
}

