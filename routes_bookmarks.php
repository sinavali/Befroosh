<?php
declare(strict_types=1);

/**
 * routes_bookmarks.php
 * Favorites / Wishlist across all shops
 * Supports guest localStorage viewing as well as authenticated database sync
 */

// 1. Favorites Page: /favorites (and aliases /favorits and /bookmarks)
$favoritesPageHandler = function () use ($pdo) {
    $user = current_user();
    $bookmarks = [];
    $grouped = [];

    if ($user) {
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

        foreach ($bookmarks as $bm) {
            $grouped[$bm['shop_id']]['shop_name'] = $bm['shop_name'];
            $grouped[$bm['shop_id']]['shop_slug'] = $bm['shop_slug'];
            $grouped[$bm['shop_id']]['items'][] = $bm;
        }
    }

    // Always use public storefront layout (NO sidebar) for customer wishlist!
    layout_public_start('کالاهای نشان‌شده و علاقه‌مندی‌ها', null, $user);
    ?>
    <div style="max-width:1100px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <span style="color:#ef4444;"><?= icon('heart-filled', 22) ?></span>
                    <span>علاقه‌مندی‌های من</span>
                </h1>
                <div style="font-size:0.85rem; color:#64748b; margin-top:4px;">محصولات ذخیره‌شده شما در فروشگاه‌های پلتفرم</div>
            </div>
            <a class="btn btn-outline" href="/shops"><?= icon('store', 14) ?> ویترین فروشگاه‌ها</a>
        </div>

        <?php if (!$user): ?>
            <!-- Guest Favorites Container (Loaded dynamically from localStorage) -->
            <div id="guest-favorites-loading" class="card" style="padding:40px; text-align:center;">
                <p style="color:#64748b;">در حال بارگذاری لیست علاقه‌مندی‌های ذخیره‌شده شما...</p>
            </div>
            <div id="guest-favorites-container" style="display:none;"></div>
            <div id="guest-favorites-empty" class="card" style="padding:40px; text-align:center; display:none;">
                <?= empty_state('لیست علاقه‌مندی‌های شما خالی است', 'با کلیک روی آیکون قلب در کنار هر محصول، آن را برای خرید ذخیره کنید.', 'heart') ?>
                <div style="margin-top:16px;">
                    <a class="btn btn-primary" href="/shops">مشاهده محصولات فروشگاه‌ها</a>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var favs = window.BefrooshStore ? window.BefrooshStore.getFavs() : [];
                document.getElementById('guest-favorites-loading').style.display = 'none';
                if (!favs || favs.length === 0) {
                    document.getElementById('guest-favorites-empty').style.display = 'block';
                } else {
                    // Fetch product details for guest favorites
                    fetch('/api/products-by-ids', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: favs })
                    }).then(function(res){ return res.json(); }).then(function(data){
                        if (!data.products || data.products.length === 0) {
                            document.getElementById('guest-favorites-empty').style.display = 'block';
                            return;
                        }
                        var container = document.getElementById('guest-favorites-container');
                        container.style.display = 'grid';
                        container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(240px, 1fr))';
                        container.style.gap = '16px';
                        var html = '';
                        data.products.forEach(function(p) {
                            html += '<div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative;">' +
                                '<div style="position:absolute; top:12px; left:12px; z-index:5;">' +
                                    '<button type="button" class="btn btn-ghost fav-heart-btn is-active" data-product-id="' + p.id + '" onclick="window.BefrooshStore.toggleFav(' + p.id + ', this); location.reload();">' +
                                        '<?= icon("heart", 18) ?>' +
                                    '</button>' +
                                '</div>' +
                                '<a href="/s/' + p.shop_slug + '/p/' + (p.slug || p.id) + '">' +
                                    '<div style="height:140px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; overflow:hidden;">' +
                                        (p.image_path ? '<img src="/storage/' + p.image_path + '" style="max-height:100%; object-fit:contain;">' : '<?= icon("products", 32) ?>') +
                                    '</div>' +
                                    '<div style="font-size:0.75rem; color:#64748b; font-weight:bold;">' + (p.shop_name || '') + '</div>' +
                                    '<h3 style="font-size:0.92rem; font-weight:bold; color:#1e293b; margin:4px 0 8px; line-height:1.4;">' + p.title + '</h3>' +
                                '</a>' +
                                '<div>' +
                                    '<div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:12px;">' + Number(p.price).toLocaleString("fa-IR") + ' تومان</div>' +
                                    '<button type="button" class="btn btn-primary btn-sm" style="width:100%;" onclick="window.BefrooshStore.addToCart({ id: ' + p.id + ', shop_id: ' + p.shop_id + ', title: \'' + p.title.replace(/'/g, "\\'") + '\', price: ' + p.price + ', qty: 1 })">' +
                                        '<?= icon("cart", 13) ?> افزودن به سبد' +
                                    '</button>' +
                                '</div>' +
                            '</div>';
                        });
                        container.innerHTML = html;
                    }).catch(function(){
                        document.getElementById('guest-favorites-empty').style.display = 'block';
                    });
                }
            });
            </script>
        <?php elseif (empty($grouped)): ?>
            <div class="card" style="padding:40px; text-align:center;">
                <?= empty_state('لیست علاقه‌مندی‌های شما خالی است', 'با کلیک روی آیکون قلب در کنار هر محصول، آن را برای خرید ذخیره کنید.', 'heart') ?>
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

                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:16px;">
                            <?php foreach ($g['items'] as $p): ?>
                                <div class="card" style="padding:14px; margin:0; display:flex; flex-direction:column; justify-content:space-between; position:relative; border-color:#e2e8f0;">
                                    <div style="position:absolute; top:12px; left:12px; z-index:5;">
                                        <button type="button" class="btn btn-ghost fav-heart-btn is-active" data-product-id="<?= (int)$p['id'] ?>" onclick="window.BefrooshStore.toggleFav(<?= (int)$p['id'] ?>, this); location.reload();" title="حذف از نشان‌شده‌ها">
                                            <?= icon('heart', 18) ?>
                                        </button>
                                    </div>

                                    <a href="/s/<?= urlencode($g['shop_slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>">
                                        <div style="height:140px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; overflow:hidden;">
                                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; object-fit:contain;">
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;"><?= icon('products', 32) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 style="font-size:0.92rem; font-weight:bold; color:#0f172a; margin-bottom:8px; line-height:1.4;">
                                            <?= e($p['title']) ?>
                                        </h3>
                                    </a>

                                    <div>
                                        <div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:12px;">
                                            <?= format_irr((float)$p['price']) ?>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-sm" style="width:100%;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$p['id'] ?>, shop_id: <?= (int)$shopId ?>, title: '<?= addslashes(e($p['title'])) ?>', price: <?= (float)$p['price'] ?>, qty: 1 })">
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
    </div>
    <?php
    layout_public_end(null);
};

route('GET', '/favorites(?:\.php)?', [], $favoritesPageHandler);
route('GET', '/favorits(?:\.php)?', [], $favoritesPageHandler);
route('GET', '/bookmarks(?:\.php)?', [], $favoritesPageHandler);

// 2. Toggle Bookmark POST Form
route('POST', '/bookmarks/toggle', [], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $productId = (int)($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, shop_id, title FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prod) {
        $chk = $pdo->prepare("SELECT id FROM product_bookmarks WHERE user_id = ? AND product_id = ?");
        $chk->execute([$user['id'], $productId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->prepare("DELETE FROM product_bookmarks WHERE id = ?")->execute([$existing['id']]);
            flash('success', "کالای «{$prod['title']}» از لیست نشان‌شده‌ها حذف شد.");
        } else {
            $pdo->prepare("INSERT INTO product_bookmarks (user_id, shop_id, product_id, created_at) VALUES (?, ?, ?, datetime('now'))")
                ->execute([$user['id'], $prod['shop_id'], $productId]);
            flash('success', "کالای «{$prod['title']}» به نشان‌شده‌های شما اضافه شد.");
        }
    }

    safe_redirect_back('/favorites');
});

// 3. Instant Ajax Toggle Bookmark API
route('POST', '/api/toggle-favorite', [], function () use ($pdo) {
    header('Content-Type: application/json; charset=utf-8');
    $user = current_user();
    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'Guest']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $productId = (int)($data['product_id'] ?? 0);

    if ($productId <= 0) {
        echo json_encode(['ok' => false]);
        exit;
    }

    $chk = $pdo->prepare("SELECT id FROM product_bookmarks WHERE user_id = ? AND product_id = ?");
    $chk->execute([$user['id'], $productId]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare("DELETE FROM product_bookmarks WHERE id = ?")->execute([$existing['id']]);
        echo json_encode(['ok' => true, 'bookmarked' => false]);
    } else {
        $stmt = $pdo->prepare("SELECT shop_id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $shopId = (int)$stmt->fetchColumn() ?: 1;

        $pdo->prepare("INSERT INTO product_bookmarks (user_id, shop_id, product_id, created_at) VALUES (?, ?, ?, datetime('now'))")
            ->execute([$user['id'], $shopId, $productId]);
        echo json_encode(['ok' => true, 'bookmarked' => true]);
    }
    exit;
});

// 4. Products by IDs API (for guest wishlist rendering)
route('POST', '/api/products-by-ids', [], function () use ($pdo) {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $ids = array_filter(array_map('intval', (array)($data['ids'] ?? [])));

    if (empty($ids)) {
        echo json_encode(['products' => []]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.price, p.slug, p.image_path, p.shop_id, s.name AS shop_name, s.slug AS shop_slug
        FROM products p
        LEFT JOIN shops s ON s.id = p.shop_id
        WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL AND p.active = 1
    ");
    $stmt->execute(array_values($ids));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['products' => $products]);
    exit;
});
