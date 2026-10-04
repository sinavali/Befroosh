<?php
declare(strict_types=1);

/**
 * routes_orders_create.php
 * Multi-Step Checkout & Order Creation
 * - Validates max_per_order and max_per_month purchase limits
 * - 4-day automated inventory reservation
 * - Auto-marks 0 IRR / free orders as 'paid'
 * - Multi-card selection for Card-to-Card payments
 */

route('GET|POST', '/(?:orders/create|checkout)(?:\.php)?', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    release_expired_reservations();

    $isAdminOrOwner = in_array($user['role'], ['superadmin', 'admin', 'shop_owner', 'shop_manager'], true);

    // Determine target shop
    $shopId = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : (!empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : active_shop_id());
    $shop = get_shop($shopId);

    if (!$shop) {
        $shops = all_active_shops();
        layout_start('انتخاب فروشگاه', $user);
        ?>
        <div class="page-header">
            <div class="page-title-wrap">
                <div class="page-icon"><?= icon('orders', 18) ?></div>
                <div>
                    <h1>ثبت سفارش جدید</h1>
                    <div class="page-sub">لطفاً ابتدا فروشگاه مورد نظر خود را انتخاب فرمایید</div>
                </div>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
            <?php foreach ($shops as $s): ?>
                <div class="card">
                    <div class="card-body">
                        <h3><?= e($s['name']) ?></h3>
                        <p style="font-size:0.85rem; color:#6b7280; margin:8px 0;"><?= e($s['address'] ?: 'ایران') ?></p>
                        <a class="btn btn-primary btn-sm" href="/orders/create?shop_id=<?= (int)$s['id'] ?>">انتخاب این فروشگاه</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        layout_end();
        return;
    }

    // Determine customer ID
    $customerId = (int)$user['id'];
    if ($isAdminOrOwner && !empty($_GET['customer_id'])) {
        $customerId = (int)$_GET['customer_id'];
    } elseif ($isAdminOrOwner && !empty($_POST['customer_id'])) {
        $customerId = (int)$_POST['customer_id'];
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        flash('error', 'مشتری مورد نظر یافت نشد.');
        redirect('/orders');
    }

    // Fetch customer addresses
    $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $addrStmt->execute([$customerId]);
    $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch shop active products
    $prodStmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY title ASC");
    $prodStmt->execute([$shopId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Active bank cards for this shop
    $activeCards = get_shop_active_cards($shopId);

    // Pre-filled items from direct purchase or cart
    $directProdId = !empty($_GET['direct_product_id']) ? (int)$_GET['direct_product_id'] : 0;
    $cartItems = get_user_cart($customerId, $shopId);

    $prefilledItems = [];
    if ($directProdId > 0) {
        $prefilledItems[] = ['product_id' => $directProdId, 'quantity' => 1];
    } elseif (!empty($cartItems)) {
        foreach ($cartItems as $ci) {
            $prefilledItems[] = ['product_id' => (int)$ci['product_id'], 'quantity' => (float)$ci['quantity']];
        }
    }

    $error = '';

    // Handle POST order submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        if (empty($addresses)) {
            $error = 'برای ثبت سفارش، مشتری باید حداقل یک آدرس ثبت‌شده داشته باشد.';
        } else {
            $addressId = (int)($_POST['address_id'] ?? 0);
            $desc = trim($_POST['description'] ?? '') ?: null;
            $selectedCardId = !empty($_POST['payment_card_id']) ? (int)$_POST['payment_card_id'] : null;
            $productIds = $_POST['product_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];

            $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
            $addrStmt->execute([$addressId, $customerId]);
            $addr = $addrStmt->fetch(PDO::FETCH_ASSOC);

            if (!$addr) {
                $error = 'آدرس تحویل انتخاب‌شده معتبر نیست.';
            } elseif (empty($productIds)) {
                $error = 'حداقل یک محصول باید برای سفارش انتخاب شود.';
            } else {
                $pdo->beginTransaction();

                try {
                    $uuid = generate_uuid();
                    $reservationDays = (int)($shop['reservation_days'] ?: 4);
                    $reservationExpires = date('Y-m-d H:i:s', time() + ($reservationDays * 86400));

                    $subtotal = 0.0;
                    $totalTax = 0.0;
                    $validLines = 0;
                    $lineData = [];

                    foreach ($productIds as $i => $pid) {
                        $pid = (int)$pid;
                        $qty = (float)($quantities[$i] ?? 0);
                        if (!$pid || $qty <= 0) continue;

                        $pStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND shop_id = ? AND active = 1 AND deleted_at IS NULL");
                        $pStmt->execute([$pid, $shopId]);
                        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$prod) {
                            throw new Exception("کالای انتخابی با شناسه {$pid} در این فروشگاه نامعتبر است.");
                        }

                        // Check limits
                        $limits = check_product_purchase_limits($customerId, $pid, $qty);
                        if (!$limits['ok']) {
                            throw new Exception($limits['error']);
                        }

                        $unitPrice = (float)$prod['price'];
                        $lineTotal = $unitPrice * $qty;
                        $taxRate = (float)($prod['tax_rate'] ?? 0);
                        $lineTax = $lineTotal * $taxRate;

                        $subtotal += $lineTotal;
                        $totalTax += $lineTax;
                        $validLines++;

                        $lineData[] = [
                            'prod' => $prod,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'line_total' => $lineTotal,
                            'tax_amount' => $lineTax,
                        ];
                    }

                    if ($validLines === 0) {
                        throw new Exception('حداقل یک کالای معتبر با تعداد بیشتر از صفر وارد نمایید.');
                    }

                    // Compute shipping
                    $freeThreshold = (float)($shop['free_shipping_threshold'] ?? 0);
                    $defaultShipping = (float)($shop['default_shipping_cost'] ?? 0);
                    $shippingCost = ($freeThreshold > 0 && $subtotal >= $freeThreshold) ? 0.0 : $defaultShipping;
                    $grandTotal = $subtotal + $shippingCost + $totalTax;

                    // Auto-pay if total is 0 (Free order)
                    $isFreeOrder = ($grandTotal <= 0);
                    $status = $isFreeOrder ? 'paid' : 'submitted';
                    $paymentStatus = $isFreeOrder ? 'paid' : 'unpaid';
                    $paidAt = $isFreeOrder ? date('Y-m-d H:i:s') : null;

                    $addressSnapshot = json_snapshot([
                        'state' => $addr['state'],
                        'city' => $addr['city'],
                        'address' => $addr['address'],
                        'postal_code' => $addr['postal_code'],
                        'description' => $addr['description'],
                    ]);

                    $customerSnapshot = json_snapshot([
                        'username' => $customer['username'],
                        'nickname' => $customer['nickname'],
                        'phone' => $customer['phone'],
                    ]);

                    $seenByCustomer = ($user['role'] === 'customer') ? 1 : 0;
                    $seenByAdmin = ($user['role'] === 'customer') ? 0 : 1;

                    // Insert Order
                    $insOrder = $pdo->prepare("
                        INSERT INTO orders (
                            uuid, shop_id, customer_id, created_by_type, created_by_id, status,
                            subtotal, shipping_cost, tax_amount, estimated_total, final_total,
                            payment_method, payment_status, payment_card_id, paid_at, reservation_expires_at,
                            shipping_method, address_id, address_snapshot, customer_snapshot, description,
                            seen_by_customer, seen_by_admin, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?,
                            'card_to_card', ?, ?, ?, ?,
                            'پست پیشتاز', ?, ?, ?, ?,
                            ?, ?, datetime('now'), datetime('now')
                        )
                    ");
                    $insOrder->execute([
                        $uuid, $shopId, $customerId, $user['role'], $user['id'], $status,
                        $subtotal, $shippingCost, $totalTax, $grandTotal, $grandTotal,
                        $paymentStatus, $selectedCardId, $paidAt, $reservationExpires,
                        $addressId, $addressSnapshot, $customerSnapshot, $desc,
                        $seenByCustomer, $seenByAdmin
                    ]);

                    $orderId = (int)$pdo->lastInsertId();

                    // Insert order items and deduct inventory
                    $insItem = $pdo->prepare("
                        INSERT INTO order_items (
                            order_id, product_id, product_title, product_sku, unit,
                            unit_price, unit_cost_price, quantity, line_total, tax_amount,
                            created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?,
                            datetime('now'), datetime('now')
                        )
                    ");

                    foreach ($lineData as $item) {
                        $p = $item['prod'];
                        $insItem->execute([
                            $orderId, $p['id'], $p['title'], $p['sku'], $p['unit'] ?: 'عدد',
                            $item['unit_price'], (float)$p['cost_price'], $item['qty'], $item['line_total'], $item['tax_amount']
                        ]);

                        // Reserve inventory
                        record_inventory_tx(
                            $shopId,
                            (int)$p['id'],
                            'outward_order',
                            $item['qty'],
                            (float)$p['cost_price'],
                            'order',
                            $orderId,
                            "ثبت سفارش #{$uuid} و رزرو انبار به مدت {$reservationDays} روز",
                            (int)$user['id']
                        );
                    }

                    // Clear items from cart
                    $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND shop_id = ?")->execute([$customerId, $shopId]);

                    $pdo->commit();

                    flash('success', $isFreeOrder ? 'سفارش رایگان با موفقیت ثبت و تایید گردید.' : "سفارش شما با موفقیت ثبت شد. اقلام به مدت {$reservationDays} روز در انبار برای شما رزرو گردید.");
                    redirect('/orders/' . $orderId);
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $error = $ex->getMessage();
                }
            }
        }
    }

require_once __DIR__ . '/routes_orders_form.php';

    render_order_create_form($user, $shop, $customer, $addresses, $activeCards, $products, $prefilledItems, $error);
});

