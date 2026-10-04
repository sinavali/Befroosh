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

    $productData = array_map(fn($p) => [
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'price' => (float)$p['price'],
        'unit' => $p['unit'] ?: 'عدد',
        'stock' => (float)$p['stock_quantity'],
        'max_order' => (float)$p['max_per_order'],
        'max_month' => (float)$p['max_per_month']
    ], $products);

    layout_start('ثبت سفارش جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>ثبت سفارش — <?= e($shop['name']) ?></h1>
                <div class="page-sub">
                    مشتری: <strong><?= e($customer['nickname']) ?></strong> (<?= format_phone($customer['phone']) ?>)
                </div>
            </div>
        </div>
        <a class="btn btn-outline" href="/cart">بازگشت به سبد خرید</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($addresses)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('آدرسی برای تحویل ثبت نشده است', 'برای تکمیل سفارش، ابتدا باید حداقل یک نشانی معتبر ثبت فرمایید.', 'location') ?>
                <div class="mt-3" style="text-align:center;">
                    <a class="btn btn-primary" href="/account/addresses/create">افزودن نشانی جدید</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <form method="post" id="order-form">
            <?= csrf_field() ?>
            <input type="hidden" name="shop_id" value="<?= $shopId ?>">
            <input type="hidden" name="customer_id" value="<?= $customerId ?>">

            <!-- STEP 1: DELIVERY ADDRESS -->
            <div class="card mb-3">
                <div class="card-header">
                    <h2>۱. اطلاعات و آدرس تحویل سفارش</h2>
                    <a class="btn btn-outline btn-sm" href="/account/addresses/create" target="_blank">+ آدرس جدید</a>
                </div>
                <div class="card-body">
                    <div class="form-group mb-2">
                        <label>آدرس تحویل گیرنده *</label>
                        <select class="select" name="address_id" required>
                            <?php foreach ($addresses as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= $a['is_default'] ? 'selected' : '' ?>>
                                    <?= e($a['state']) ?>، <?= e($a['city']) ?> — <?= e($a['address']) ?><?= $a['is_default'] ? ' (پیش‌فرض)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>توضیحات و هماهنگی ارسال (اختیاری)</label>
                        <textarea class="textarea" name="description" placeholder="نکات ضروری در مورد تحویل مرسوله..."></textarea>
                    </div>
                </div>
            </div>

            <!-- STEP 2: ORDER ITEMS -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>۲. اقلام سفارش و تعداد</h2>
                    <button type="button" class="btn btn-outline btn-sm" id="add-line"><?= icon('plus', 13) ?> افزودن سطر کالا</button>
                </div>
                <div class="card-body">
                    <div id="order-lines"></div>

                    <div class="order-total-box mt-3" style="background:#f8fafc; border:1px solid #cbd5e1; color:#0f172a; padding:12px;">
                        <div style="display:flex; justify-content:space-between; width:100%;">
                            <span>جمع اقلام:</span>
                            <span id="order-subtotal"><?= format_irr(0) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; width:100%; font-size:0.85rem; color:#64748b; margin-top:4px;">
                            <span>هزینه ارسال پیشتاز:</span>
                            <span id="order-shipping"><?= format_irr((float)$shop['default_shipping_cost']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; width:100%; font-size:1.05rem; font-weight:800; color:#1e3a8a; margin-top:8px; border-top:1px solid #e2e8f0; padding-top:6px;">
                            <span>مبلغ کل برآوردی:</span>
                            <span id="order-total"><?= format_irr(0) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: PAYMENT METHOD & SUBMIT -->
            <div class="card">
                <div class="card-header">
                    <h2>۳. اطلاعات پرداخت کارت‌به‌کارت</h2>
                </div>
                <div class="card-body">
                    <div style="margin-bottom:12px; font-size:0.85rem; color:#475569; line-height:1.6;">
                        روش پرداخت این فروشگاه <strong>کارت‌به‌کارت</strong> می‌باشد. پس از ثبت نهایی، اقلام به مدت <strong><?= en_to_fa_digits((string)($shop['reservation_days'] ?: 4)) ?> روز</strong> در انبار رزرو می‌ماند و می‌توانید فیش واریزی خود را بارگذاری فرمایید.
                    </div>

                    <?php if (!empty($activeCards)): ?>
                        <div class="form-group mb-2">
                            <label>شماره کارت جهت واریز:</label>
                            <select class="select" name="payment_card_id">
                                <?php foreach ($activeCards as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        <?= format_card_number($c['card_number']) ?> — به نام <?= e($c['card_holder']) ?> (<?= e($c['bank_name'] ?: 'بانک') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> تایید و ثبت نهایی سفارش</button>
                </div>
            </div>
        </form>

        <script id="products-data" type="application/json"><?= json_encode($productData, JSON_UNESCAPED_UNICODE) ?></script>
        <script id="items-data" type="application/json"><?= json_encode($prefilledItems, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <?php
    layout_end();
});
