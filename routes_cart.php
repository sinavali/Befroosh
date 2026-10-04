<?php
declare(strict_types=1);

/**
 * routes_cart.php
 * Shop-isolated persistent cart (7-day retention) & Bookmarks / Marked items
 */

// Cart overview: grouped per shop
route('GET', '/cart(?:\.php)?', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    release_expired_reservations();

    $shopIdFilter = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null;
    $cartItems = get_user_cart((int)$user['id'], $shopIdFilter);

    // Group items by shop
    $groupedCart = [];
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

    layout_start('سبد خرید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>سبد خرید شما</h1>
                <div class="page-sub">اقلام شما به تفکیک هر فروشگاه به مدت ۷ روز نگهداری می‌شوند</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/shops"><?= icon('plus', 14) ?> مرور فروشگاه‌ها</a>
    </div>

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
    <?php
    layout_end();
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

// Marked products / Wishlist across all shops
route('GET', '/bookmarks(?:\.php)?', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();

    $stmt = $pdo->prepare("
        SELECT b.id AS bookmark_id, b.created_at AS bookmarked_at,
               p.*, s.name AS shop_name, s.slug AS shop_slug
        FROM product_bookmarks b
        JOIN products p ON p.id = b.product_id
        JOIN shops s ON s.id = b.shop_id
        WHERE b.user_id = ? AND p.deleted_at IS NULL
        ORDER BY b.shop_id ASC, b.id DESC
    ");
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group bookmarks by shop
    $grouped = [];
    foreach ($items as $it) {
        $sid = (int)$it['shop_id'];
        if (!isset($grouped[$sid])) {
            $grouped[$sid] = [
                'shop_name' => $it['shop_name'],
                'shop_slug' => $it['shop_slug'],
                'items' => [],
            ];
        }
        $grouped[$sid]['items'][] = $it;
    }

    layout_start('کالاهای نشان‌شده', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('products', 18) ?></div>
            <div>
                <h1>کالاهای نشان‌شده من</h1>
                <div class="page-sub">لیست کالاهای مورد علاقه شما به تفکیک فروشگاه‌ها</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/shops">مرور فروشگاه‌ها</a>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('هیچ کالایی نشان نشده است', 'با کلیک روی آیکون نشان‌کردن در صفحه محصولات، می‌توانید کالاهای دلخواه خود را ذخیره کنید.', 'products') ?>
            </div>
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:20px;">
            <?php foreach ($grouped as $sid => $g): ?>
                <div class="card">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h2>فروشگاه <?= e($g['shop_name']) ?></h2>
                        <a class="btn btn-outline btn-sm" href="/s/<?= urlencode($g['shop_slug']) ?>">ورود به فروشگاه</a>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:14px;">
                            <?php foreach ($g['items'] as $p): ?>
                                <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px; display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <div style="height:140px; background:#f9fafb; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; overflow:hidden;">
                                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <span style="color:#9ca3af; font-size:0.8rem;">تصویر کالا</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 style="font-size:0.95rem; margin-bottom:6px;">
                                            <a href="/s/<?= urlencode($g['shop_slug']) ?>/p/<?= (int)$p['id'] ?>" style="color:#111827; text-decoration:none;">
                                                <?= e($p['title']) ?>
                                            </a>
                                        </h3>
                                        <div style="font-size:0.9rem; font-weight:800; color:#1d4ed8; margin-bottom:10px;">
                                            <?= format_irr((float)$p['price']) ?>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <form method="post" action="/cart/add" style="flex:1;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button class="btn btn-primary btn-sm" style="width:100%;">افزودن به سبد</button>
                                        </form>
                                        <form method="post" action="/bookmarks/toggle">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                            <button class="btn btn-danger btn-sm" title="حذف از نشان‌شده‌ها"><?= icon('trash', 13) ?></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    layout_end();
});

// Toggle bookmark
route('POST', '/bookmarks/toggle', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $productId = (int)($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, shop_id, title FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        flash('error', 'محصول مورد نظر یافت نشد.');
        safe_redirect_back('/bookmarks');
    }

    $chk = $pdo->prepare("SELECT id FROM product_bookmarks WHERE user_id = ? AND product_id = ?");
    $chk->execute([$user['id'], $productId]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare("DELETE FROM product_bookmarks WHERE id = ?")->execute([$existing['id']]);
        flash('success', "کالای «{$prod['title']}» از لیست نشان‌شده‌ها حذف شد.");
    } else {
        $pdo->prepare("
            INSERT INTO product_bookmarks (user_id, shop_id, product_id, created_at)
            VALUES (?, ?, ?, datetime('now'))
        ")->execute([$user['id'], $prod['shop_id'], $productId]);
        flash('success', "کالای «{$prod['title']}» به نشان‌شده‌های شما اضافه شد.");
    }

    safe_redirect_back('/bookmarks');
});
