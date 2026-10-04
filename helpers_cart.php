<?php
declare(strict_types=1);

/**
 * helpers_cart.php
 * Cart management, purchase caps, bookmarks/wishlist, and guest sync
 */

function get_cart_items(int $userId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.title AS product_title, p.price, p.image_path, p.stock_quantity, p.max_per_order, p.max_per_month, s.name AS shop_name, s.slug AS shop_slug, s.default_shipping_cost, s.free_shipping_threshold, s.tax_rate
            FROM cart_items c
            JOIN products p ON p.id = c.product_id
            JOIN shops s ON s.id = c.shop_id
            WHERE c.user_id = ? AND c.expires_at > datetime('now')
            ORDER BY c.shop_id ASC, c.id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function get_cart_count(int $userId): int
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ? AND expires_at > datetime('now')");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function get_user_cart(int $userId, ?int $shopId = null): array
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("DELETE FROM cart_items WHERE expires_at < ?")->execute([$now]);

    $sql = "
        SELECT c.*, p.title AS product_title, p.price, p.image_path, p.stock_quantity, p.max_per_order, p.max_per_month, p.unit, p.tax_rate, s.name AS shop_name, s.slug AS shop_slug, s.default_shipping_cost, s.free_shipping_threshold
        FROM cart_items c
        JOIN products p ON p.id = c.product_id
        JOIN shops s ON s.id = c.shop_id
        WHERE c.user_id = ?
    ";
    $params = [$userId];
    if ($shopId !== null) {
        $sql .= " AND c.shop_id = ?";
        $params[] = $shopId;
    }
    $sql .= " ORDER BY c.shop_id ASC, c.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function check_product_purchase_limits(int $customerId, int $productId, float $requestedQty): array
{
    return check_purchase_limits($customerId, $productId, $requestedQty);
}

function check_purchase_limits(int $userId, int $productId, float $requestQty): array

{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT max_per_order, max_per_month, stock_quantity, title FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            return ['ok' => false, 'error' => 'محصول یافت نشد.'];
        }

        $maxOrder = (float)($prod['max_per_order'] ?? 0);
        $maxMonth = (float)($prod['max_per_month'] ?? 0);
        $stock = (float)($prod['stock_quantity'] ?? 0);

        if ($requestQty > $stock) {
            return ['ok' => false, 'error' => 'تعداد درخواستی بیشتر از موجودی انبار است (موجودی: ' . en_to_fa_digits((string)$stock) . ').'];
        }

        if ($maxOrder > 0 && $requestQty > $maxOrder) {
            return ['ok' => false, 'error' => 'حداکثر سقف خرید این کالا در هر سفارش ' . en_to_fa_digits((string)$maxOrder) . ' عدد است.'];
        }

        if ($maxMonth > 0 && $userId > 0) {
            $monthStmt = $pdo->prepare("
                SELECT COALESCE(SUM(oi.quantity), 0)
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.customer_id = ? AND oi.product_id = ? AND o.status NOT IN ('canceled')
                  AND o.created_at >= datetime('now', '-30 days')
            ");
            $monthStmt->execute([$userId, $productId]);
            $alreadyBoughtMonth = (float) $monthStmt->fetchColumn();

            if (($alreadyBoughtMonth + $requestQty) > $maxMonth) {
                $rem = max(0, $maxMonth - $alreadyBoughtMonth);
                return ['ok' => false, 'error' => 'سقف ماهانه خرید این محصول ' . en_to_fa_digits((string)$maxMonth) . ' عدد است. شما در ۳۰ روز گذشته ' . en_to_fa_digits((string)$alreadyBoughtMonth) . ' عدد خریداری کرده‌اید.'];
            }
        }

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'خطا در ارزیابی سقف خرید: ' . $e->getMessage()];
    }
}

function add_to_cart(int $userId, int $productId, float $qty = 1): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT shop_id, stock_quantity FROM products WHERE id = ? AND active = 1 AND deleted_at IS NULL");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            return ['ok' => false, 'error' => 'کالای انتخابی موجود نیست.'];
        }

        $check = check_purchase_limits($userId, $productId, $qty);
        if (!$check['ok']) {
            return $check;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + 7 * 86400); // 7-day retention
        $ins = $pdo->prepare("
            INSERT INTO cart_items (user_id, shop_id, product_id, quantity, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(user_id, product_id) DO UPDATE SET
                quantity = cart_items.quantity + excluded.quantity,
                expires_at = excluded.expires_at,
                updated_at = datetime('now')
        ");
        $ins->execute([$userId, $prod['shop_id'], $productId, $qty, $expiresAt]);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function is_bookmarked(int $userId, int $productId): bool
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM product_bookmarks WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function sync_guest_storage_to_db(int $userId, array $cartItems, array $favIds): void
{
    global $pdo;
    try {
        // Sync Bookmarks
        if (!empty($favIds)) {
            $favStmt = $pdo->prepare("
                INSERT OR IGNORE INTO product_bookmarks (user_id, shop_id, product_id, created_at)
                SELECT ?, shop_id, id, datetime('now') FROM products WHERE id = ?
            ");
            foreach ($favIds as $fId) {
                if (is_numeric($fId)) {
                    $favStmt->execute([$userId, (int)$fId]);
                }
            }
        }

        // Sync Cart Items
        if (!empty($cartItems)) {
            foreach ($cartItems as $c) {
                $pId = (int)($c['id'] ?? $c['product_id'] ?? 0);
                $qty = (float)($c['qty'] ?? $c['quantity'] ?? 1);
                if ($pId > 0 && $qty > 0) {
                    add_to_cart($userId, $pId, $qty);
                }
            }
        }
    } catch (Throwable $e) {}
}
