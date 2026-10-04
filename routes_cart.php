<?php
declare(strict_types=1);

/**
 * routes_cart.php
 * Shop-isolated persistent cart (7-day retention) & Bookmarks / Marked items
 */

// Cart overview: grouped per shop
// Cart overview: grouped per shop (Zero staff sidebar, public storefront layout)
route('GET', '/cart(?:\.php)?', [], function () use ($pdo) {
    $user = current_user();
    release_expired_reservations();

    $groupedCart = [];
    if ($user) {
        $shopIdFilter = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null;
        $cartItems = get_user_cart((int)$user['id'], $shopIdFilter);

        foreach ($cartItems as $it) {
            $sid = (int)$it['shop_id'];
            if (!isset($groupedCart[$sid])) {
                $groupedCart[$sid] = [
                    'shop_id' => $sid,
                    'shop_name' => $it['shop_name'],
                    'shop_slug' => $it['shop_slug'],
                    'default_shipping' => (float)$it['default_shipping_cost'],
                    'free_shipping_threshold' => (float)$it['free_shipping_threshold'],
                    'items' => [],
                    'subtotal' => 0.0,
                ];
            }
            $lineTotal = (float)$it['price'] * (float)$it['quantity'];
            $it['line_total'] = $lineTotal;
            $groupedCart[$sid]['items'][] = $it;
            $groupedCart[$sid]['subtotal'] += $lineTotal;
        }
    }

    layout_public_start('سبد خرید', null, $user);
    ?>
    <div style="margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin-bottom:4px;">سبد خرید شما</h1>
                <div style="font-size:0.85rem; color:#64748b;">اقلام به تفکیک فروشگاه به مدت ۷ روز نگهداری می‌شوند</div>
            </div>
            <a class="btn btn-outline" href="/shops"><?= icon('plus', 14) ?> مرور فروشگاه‌ها</a>
        </div>
    </div>

    <?php if ($user): ?>
        <?php if (empty($groupedCart)): ?>
            <div class="card">
                <div class="card-body">
                    <?= empty_state('سبد خرید شما در حال حاضر خالی است', 'می‌توانید با مراجعه به ویترین فروشگاه‌ها، کالاهای مورد نیاز خود را به سبد خرید اضافه نمایید.', 'orders') ?>
                    <div class="mt-3" style="text-align:center;">
                        <a class="btn btn-primary" href="/shops">مشاهده فروشگاه‌ها</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($groupedCart as $sid => $shopCart): ?>
                    <?php
                    $subtotal = $shopCart['subtotal'];
                    $isFreeShipping = ($shopCart['free_shipping_threshold'] > 0 && $subtotal >= $shopCart['free_shipping_threshold']);
                    $shipping = $isFreeShipping ? 0.0 : $shopCart['default_shipping'];
                    $estimatedTotal = $subtotal + $shipping;
                    ?>
                    <div class="card">
                        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <div>
                                <h2 style="font-size:1.1rem;"><?= e($shopCart['shop_name']) ?></h2>
                                <div style="font-size:0.75rem; color:var(--muted);">سفارش مجزا از این فروشگاه ارسال خواهد شد</div>
                            </div>
                            <a class="btn btn-outline btn-sm" href="/s/<?= urlencode($shopCart['shop_slug']) ?>">مشاهده ویترین فروشگاه</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>تصویر</th>
                                        <th>عنوان کالا</th>
                                        <th>قیمت واحد</th>
                                        <th>تعداد</th>
                                        <th>جمع خط</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shopCart['items'] as $item): ?>
                                        <tr>
                                            <td style="width:50px;">
                                                <?php if (!empty($item['image_path']) && file_exists(STORAGE_PATH . '/' . $item['image_path'])): ?>
                                                    <img src="/storage/<?= e($item['image_path']) ?>" alt="" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">
                                                <?php else: ?>
                                                    <div style="width:42px; height:42px; background:#f3f4f6; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:0.7rem;">عکس</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="/s/<?= urlencode($shopCart['shop_slug']) ?>/p/<?= (int)$item['product_id'] ?>" style="font-weight:700; color:#1e40af; text-decoration:none;">
                                                    <?= e($item['product_title']) ?>
                                                </a>
                                                <div style="font-size:0.75rem; color:#6b7280;">واحد: <?= e($item['unit'] ?: 'عدد') ?></div>
                                            </td>
                                            <td><?= format_irr((float)$item['price']) ?></td>
                                            <td>
                                                <form method="post" action="/cart/update" style="display:inline-flex; align-items:center; gap:6px;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="cart_id" value="<?= (int)$item['id'] ?>">
                                                    <input type="number" name="quantity" value="<?= (float)$item['quantity'] ?>" min="1" step="1" class="input" style="width:70px; padding:4px 8px; font-size:0.85rem;" required>
                                                    <button class="btn btn-outline btn-sm" title="به‌روزرسانی تعداد">ثبت</button>
                                                </form>
                                            </td>
                                            <td><strong><?= format_irr((float)$item['line_total']) ?></strong></td>
                                            <td>
                                                <form method="post" action="/cart/remove" class="inline-form" data-confirm="این کالا از سبد خرید حذف شود؟">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="cart_id" value="<?= (int)$item['id'] ?>">
                                                    <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?> حذف</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; background:#f9fafb;">
                            <div>
                                <div>جمع کالاها: <strong><?= format_irr($subtotal) ?></strong></div>
                                <div style="font-size:0.8rem; color:#6b7280; margin-top:2px;">
                                    هزینه ارسال: <?= $isFreeShipping ? '<span class="badge badge-emerald">رایگان</span>' : format_irr($shipping) ?>
                                    <?php if (!$isFreeShipping && $shopCart['free_shipping_threshold'] > 0): ?>
                                        <span style="font-size:0.75rem;">(ارسال رایگان برای خریدهای بالای <?= format_irr($shopCart['free_shipping_threshold']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:1rem; font-weight:800; color:#1e3a8a; margin-top:4px;">مبلغ کل برآوردی: <?= format_irr($estimatedTotal) ?></div>
                            </div>
                            <a class="btn btn-primary" href="/orders/create?shop_id=<?= (int)$sid ?>">
                                <?= icon('check', 14) ?> تسویه حساب و ثبت سفارش از این فروشگاه
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div id="guestCartContainer">
            <div class="card" style="padding:30px; text-align:center;">
                <p style="color:#64748b;">در حال دریافت اقلام سبد خرید...</p>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var items = window.BefrooshStore ? window.BefrooshStore.getCart() : [];
            var box = document.getElementById('guestCartContainer');
            if (!items.length) {
                box.innerHTML = '<div class="card"><div class="card-body" style="text-align:center; padding:40px;"><p style="color:#64748b; font-size:1.1rem; margin-bottom:16px;">سبد خرید شما در حال حاضر خالی است.</p><a class="btn btn-primary" href="/shops">مشاهده فروشگاه‌ها</a></div></div>';
                return;
            }
            var subtotal = 0;
            var rows = '';
            items.forEach(function(it, idx) {
                var total = (it.price || 0) * (it.qty || 1);
                subtotal += total;
                rows += '<tr>' +
                    '<td style="font-weight:bold;">' + (it.title || 'کالا') + '</td>' +
                    '<td>' + Number(it.price || 0).toLocaleString('fa-IR') + ' ریال</td>' +
                    '<td>' +
                        '<div style="display:inline-flex; align-items:center; gap:6px;">' +
                            '<button type="button" class="btn btn-outline btn-sm" onclick="window.updateMiniCartQty(' + idx + ', -1); location.reload();">-</button>' +
                            '<span style="font-weight:bold; min-width:24px; text-align:center;">' + Number(it.qty || 1).toLocaleString('fa-IR') + '</span>' +
                            '<button type="button" class="btn btn-outline btn-sm" onclick="window.updateMiniCartQty(' + idx + ', 1); location.reload();">+</button>' +
                        '</div>' +
                    '</td>' +
                    '<td><strong>' + Number(total).toLocaleString('fa-IR') + ' ریال</strong></td>' +
                    '<td><button type="button" class="btn btn-danger btn-sm" onclick="window.removeMiniCartItem(' + idx + '); location.reload();">حذف</button></td>' +
                '</tr>';
            });
            box.innerHTML = '<div class="card">' +
                '<div class="card-header"><h2>اقلام سبد خرید مهمان</h2></div>' +
                '<div class="table-responsive"><table class="table"><thead><tr><th>عنوان کالا</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th><th>عملیات</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
                '<div class="card-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">' +
                    '<div><strong>جمع کل اقلام: ' + Number(subtotal).toLocaleString('fa-IR') + ' ریال</strong></div>' +
                    '<a class="btn btn-primary" href="/login?redirect=/cart">ورود به حساب کاربری جهت ثبت نهایی سفارش</a>' +
                '</div>' +
            '</div>';
        });
        </script>
    <?php endif; ?>
    <?php
    layout_public_end(null);
});

// Add to cart
route('POST', '/cart/add', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (float)fa_to_en_digits($_POST['quantity'] ?? '1'));

    $stmt = $pdo->prepare("SELECT id, shop_id, title, active, deleted_at FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod || !$prod['active'] || $prod['deleted_at'] !== null) {
        flash('error', 'محصول مورد نظر یافت نشد یا غیرفعال است.');
        safe_redirect_back('/cart');
    }

    $shopId = (int)$prod['shop_id'];

    // Check existing item in user cart to compute new total
    $chkStmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $chkStmt->execute([$user['id'], $productId]);
    $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

    $newTotal = $existing ? ((float)$existing['quantity'] + $qty) : $qty;

    $limitCheck = check_product_purchase_limits((int)$user['id'], $productId, $newTotal);
    if (!$limitCheck['ok']) {
        flash('error', $limitCheck['error']);
        safe_redirect_back('/cart');
    }

    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400)); // 7 days retention

    if ($existing) {
        $pdo->prepare("UPDATE cart_items SET quantity = ?, expires_at = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([$newTotal, $expiresAt, $existing['id']]);
    } else {
        $pdo->prepare("
            INSERT INTO cart_items (user_id, shop_id, product_id, quantity, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ")->execute([$user['id'], $shopId, $productId, $qty, $expiresAt]);
    }

    flash('success', "کالای «{$prod['title']}» با موفقیت به سبد خرید اضافه شد.");
    if (!empty($_POST['redirect_to_cart'])) {
        redirect('/cart?shop_id=' . $shopId);
    }
    safe_redirect_back('/cart?shop_id=' . $shopId);
});

// Update cart quantity
route('POST', '/cart/update', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $cartId = (int)($_POST['cart_id'] ?? 0);
    $qty = max(1, (float)fa_to_en_digits($_POST['quantity'] ?? '1'));

    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $user['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        flash('error', 'آیتم مورد نظر در سبد خرید یافت نشد.');
        redirect('/cart');
    }

    $limitCheck = check_product_purchase_limits((int)$user['id'], (int)$item['product_id'], $qty);
    if (!$limitCheck['ok']) {
        flash('error', $limitCheck['error']);
        redirect('/cart');
    }

    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
    $pdo->prepare("UPDATE cart_items SET quantity = ?, expires_at = ?, updated_at = datetime('now') WHERE id = ?")
        ->execute([$qty, $expiresAt, $cartId]);

    flash('success', 'تعداد کالا در سبد خرید به‌روزرسانی شد.');
    redirect('/cart?shop_id=' . $item['shop_id']);
});

// Remove item from cart
route('POST', '/cart/remove', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $cartId = (int)($_POST['cart_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $user['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $pdo->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$cartId]);
        flash('success', 'کالا از سبد خرید حذف شد.');
        redirect('/cart?shop_id=' . $item['shop_id']);
    }

    redirect('/cart');
});

// Direct purchase link / Instant Buy: /buy/{id}
route('GET', '/buy/(\d+)', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($productId) use ($pdo) {
    $user = require_login();
    $productId = (int)$productId;

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        error_page(404, 'کالا یافت نشد', 'کالای مورد نظر برای خرید سریع وجود ندارد یا غیرفعال است.');
    }

    $shopId = (int)$prod['shop_id'];
    $limitCheck = check_product_purchase_limits((int)$user['id'], $productId, 1);
    if (!$limitCheck['ok']) {
        flash('error', $limitCheck['error']);
        redirect('/cart?shop_id=' . $shopId);
    }

    // Upsert into cart
    $chk = $pdo->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?");
    $chk->execute([$user['id'], $productId]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
    if (!$existing) {
        $pdo->prepare("
            INSERT INTO cart_items (user_id, shop_id, product_id, quantity, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, datetime('now'), datetime('now'))
        ")->execute([$user['id'], $shopId, $productId, $expiresAt]);
    }

    // Redirect straight to order checkout with this product pre-filled
    flash('success', "کالای «{$prod['title']}» آماده ثبت سفارش است. اطلاعات تحویل را تایید نمایید.");
    redirect("/orders/create?shop_id={$shopId}&direct_product_id={$productId}");
});

