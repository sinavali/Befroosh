<?php
declare(strict_types=1);

/**
 * routes_bookmarks.php
 * Marked products / Wishlist across all shops
 */

route('GET', '/bookmarks(?:\.php)?', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_auth();

    $stmt = $pdo->prepare("
        SELECT b.id AS bookmark_id, b.created_at AS bookmarked_at,
               p.*, s.name AS shop_name, s.slug AS shop_slug
        FROM product_bookmarks b
        JOIN products p ON p.id = b.product_id
        JOIN shops s ON s.id = b.shop_id
        WHERE b.user_id = ? AND p.deleted_at IS NULL
        ORDER BY b.id DESC
    ");
    $stmt->execute([$user['id']]);
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group bookmarks by shop
    $grouped = [];
    foreach ($bookmarks as $bm) {
        $grouped[$bm['shop_id']]['shop_name'] = $bm['shop_name'];
        $grouped[$bm['shop_id']]['shop_slug'] = $bm['shop_slug'];
        $grouped[$bm['shop_id']]['items'][] = $bm;
    }

    layout_start('کالاهای نشان‌شده و علاقه‌مندی‌ها', $user);
    ?>
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0;">کالاهای نشان‌شده (علاقه‌مندی‌ها)</h1>
            <div style="font-size:0.85rem; color:#64748b;">محصولات ذخیره‌شده شما در فروشگاه‌های مختلف</div>
        </div>
        <a class="btn btn-outline" href="/shops"><?= icon('store', 14) ?> ویترین فروشگاه‌ها</a>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="card" style="padding:40px; text-align:center;">
            <?= empty_state('لیست علاقه‌مندی‌های شما خالی است', 'با کلیک روی آیکون قلب در کنار هر محصول، آن را برای خرید بعدی ذخیره کنید.', 'heart') ?>
            <div style="margin-top:16px;">
                <a class="btn btn-primary" href="/shops">مشاهده محصولات فروشگاه‌ها</a>
            </div>
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:24px;">
            <?php foreach ($grouped as $shopId => $g): ?>
                <div class="card" style="padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                        <h2 style="font-size:1.05rem; font-weight:bold; margin:0; color:#1e293b;">
                            <?= icon('store', 16) ?> فروشگاه «<?= e($g['shop_name']) ?>»
                        </h2>
                        <a href="/shop/<?= e($g['shop_slug']) ?>" class="btn btn-outline btn-sm">مشاهده فروشگاه</a>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px;">
                        <?php foreach ($g['items'] as $p): ?>
                            <div class="card" style="padding:14px; margin:0; display:flex; flex-direction:column; justify-content:space-between; border-color:#e2e8f0;">
                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <span class="badge badge-emerald">موجود</span>
                                        <form method="post" action="/bookmarks/toggle" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                            <button class="btn btn-ghost btn-sm" title="حذف از نشان‌شده‌ها" style="padding:2px; color:#ef4444;">
                                                <?= icon('heart', 18, true) ?>
                                            </button>
                                        </form>
                                    </div>
                                    <h3 style="font-size:0.92rem; font-weight:bold; margin-bottom:8px;">
                                        <a href="/s/<?= urlencode($g['shop_slug']) ?>/p/<?= (int)$p['id'] ?>" style="color:#0f172a;">
                                            <?= e($p['title']) ?>
                                        </a>
                                    </h3>
                                    <div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:12px;">
                                        <?= format_irr((float)$p['price']) ?>
                                    </div>
                                </div>

                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn btn-primary btn-sm" style="flex:1;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$p['id'] ?>, shop_id: <?= (int)$shopId ?>, title: '<?= addslashes(e($p['title'])) ?>', price: <?= (float)$p['price'] ?>, qty: 1 })">
                                        <?= icon('cart', 13) ?> افزودن به سبد
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
    $user = require_auth();
    if (!verify_csrf()) {
        die('خطای اعتبارسنجی امنیتی.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, shop_id, title FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        set_flash('error', 'محصول مورد نظر یافت نشد.');
        safe_redirect_back('/bookmarks');
    }

    $chk = $pdo->prepare("SELECT id FROM product_bookmarks WHERE user_id = ? AND product_id = ?");
    $chk->execute([$user['id'], $productId]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare("DELETE FROM product_bookmarks WHERE id = ?")->execute([$existing['id']]);
        set_flash('success', "کالای «{$prod['title']}» از لیست نشان‌شده‌ها حذف شد.");
    } else {
        $pdo->prepare("
            INSERT INTO product_bookmarks (user_id, shop_id, product_id, created_at)
            VALUES (?, ?, ?, datetime('now'))
        ")->execute([$user['id'], $prod['shop_id'], $productId]);
        set_flash('success', "کالای «{$prod['title']}» به نشان‌شده‌های شما اضافه شد.");
    }

    safe_redirect_back('/bookmarks');
});
