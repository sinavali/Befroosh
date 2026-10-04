<?php
declare(strict_types=1);

/**
 * routes_public_products.php
 * Public product catalog with granular top controls, numbered pagination, and single product view
 */

// 1. PUBLIC PRODUCT CATALOG: /shop/{slug}/products
route('GET', '/shop/([^/]+)/products', [], function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) error_page(404, 'فروشگاه یافت نشد', 'فروشگاه مورد نظر وجود ندارد.');
    $shopId = (int)$shop['id'];
    $user = current_user();

    // Filters
    $q = trim($_GET['q'] ?? '');
    $catId = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
    $minPrice = !empty($_GET['min_price']) ? (float)fa_to_en_digits($_GET['min_price']) : 0;
    $maxPrice = !empty($_GET['max_price']) ? (float)fa_to_en_digits($_GET['max_price']) : 0;
    $inStockOnly = !empty($_GET['in_stock']) ? 1 : 0;
    $sort = $_GET['sort'] ?? 'newest';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 12;

    $where = "WHERE p.shop_id = ? AND p.active = 1 AND p.deleted_at IS NULL";
    $params = [$shopId];

    if ($q !== '') {
        $where .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }
    if ($catId > 0) {
        $where .= " AND p.category_id = ?";
        $params[] = $catId;
    }
    if ($minPrice > 0) {
        $where .= " AND p.price >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice > 0) {
        $where .= " AND p.price <= ?";
        $params[] = $maxPrice;
    }
    if ($inStockOnly) {
        $where .= " AND p.stock_quantity > 0";
    }

    $sortSql = match ($sort) {
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'stock' => 'p.stock_quantity DESC',
        default => 'p.id DESC',
    };

    // Total Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p {$where}");
    $cntStmt->execute($params);
    $totalCount = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $perPage));

    // Fetch Products
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id {$where} ORDER BY {$sortSql} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Categories for filter
    $categories = $pdo->query("SELECT id, name FROM categories WHERE shop_id = {$shopId} AND active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start('کاتالوگ محصولات - ' . $shop['name'], $shop, $user);
    ?>
    <!-- GRANULAR TOP CONTROLS BAR -->
    <div class="card" style="padding:16px 20px; margin-bottom:24px;">
        <form method="get" action="/shop/<?= e($shop['slug']) ?>/products" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">جستجوی عنوان / بارکد:</label>
                <input type="text" name="q" class="input" placeholder="نام یا بارکد کالا..." value="<?= e($q) ?>">
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">دسته‌بندی:</label>
                <select name="category" class="select">
                    <option value="">همه دسته‌ها</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">ترتیب نمایش:</label>
                <select name="sort" class="select">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین محصولات</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
                    <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>بیشترین موجودی</option>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">حداقل قیمت (ریال):</label>
                <input type="number" name="min_price" class="input" placeholder="از..." value="<?= $minPrice > 0 ? (int)$minPrice : '' ?>" style="font-size:0.85rem;">
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">حداکثر قیمت (ریال):</label>
                <input type="number" name="max_price" class="input" placeholder="تا..." value="<?= $maxPrice > 0 ? (int)$maxPrice : '' ?>" style="font-size:0.85rem;">
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:bold; margin-bottom:4px; color:#475569;">فیلتر موجودی:</label>
                <label style="display:flex; align-items:center; gap:8px; height:38px; cursor:pointer; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:0 12px; font-size:0.85rem; font-weight:bold;">
                    <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?>>
                    <span>فقط کالاهای موجود</span>
                </label>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">اعمال فیلتر</button>
                <?php if ($q || $catId || $minPrice > 0 || $maxPrice > 0 || $inStockOnly || $sort !== 'newest'): ?>
                    <a href="/shop/<?= e($shop['slug']) ?>/products" class="btn btn-outline" title="پاکسازی فیلترها">✕</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PRODUCTS GRID -->
    <?php if (empty($products)): ?>
        <div class="card" style="padding:40px; text-align:center;">
            <?= empty_state('محصولی با مشخصات انتخابی یافت نشد', 'می‌توانید فیلترهای جستجو را بازنشانی فرمایید.', 'search') ?>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:20px;">
            <?php foreach ($products as $p): ?>
                <div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative;">
                    <div style="position:absolute; top:12px; left:12px; z-index:5;">
                        <button type="button" class="btn btn-ghost fav-heart-btn" data-product-id="<?= (int)$p['id'] ?>" onclick="window.BefrooshStore.toggleFav(<?= (int)$p['id'] ?>, this)" title="علاقه‌مندی">
                            <?= icon('heart', 18) ?>
                        </button>
                    </div>

                    <a href="/s/<?= e($shop['slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>">
                        <div style="height:160px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; overflow:hidden;">
                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; object-fit:contain;">
                            <?php else: ?>
                                <span style="color:#cbd5e1;"><?= icon('products', 36) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($p['cat_name'])): ?>
                            <span style="font-size:0.75rem; color:#64748b; font-weight:bold; display:block; margin-bottom:4px;"><?= e($p['cat_name']) ?></span>
                        <?php endif; ?>
                        <h3 style="font-size:0.92rem; font-weight:bold; color:#1e293b; margin-bottom:8px; line-height:1.4;"><?= e($p['title']) ?></h3>
                    </a>

                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <span style="font-size:0.95rem; font-weight:800; color:#059669;"><?= format_irr((float)$p['price']) ?></span>
                            <?php if ((float)$p['stock_quantity'] > 0): ?>
                                <span class="badge badge-emerald" style="font-size:0.7rem;">موجود</span>
                            <?php else: ?>
                                <span class="badge badge-rose" style="font-size:0.7rem;">ناموجود</span>
                            <?php endif; ?>
                        </div>

                        <?php if ((float)$p['stock_quantity'] > 0): ?>
                            <button type="button" class="btn btn-primary btn-sm" style="width:100%;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$p['id'] ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($p['title'])) ?>', price: <?= (float)$p['price'] ?>, qty: 1 })">
                                <?= icon('plus', 13) ?> افزودن به سبد
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" disabled style="width:100%; opacity:0.6;">ناموجود</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NUMBERED PAGINATION -->
        <?= render_pagination($page, $totalPages, "/shop/{$shop['slug']}/products", $_GET) ?>
    <?php endif; ?>
    <?php
    layout_public_end($shop);
});

// 2. PUBLIC SINGLE PRODUCT PAGE: /s/{shop}/p/{product}
route('GET', '/s/([^/]+)/p/([^/]+)', [], function ($shopSlug, $prodSlug) use ($pdo) {
    $shop = resolve_shop($shopSlug);
    if (!$shop) error_page(404, 'فروشگاه یافت نشد', 'فروشگاه مورد نظر وجود ندارد.');
    $shopId = (int)$shop['id'];
    $prod = resolve_product($shopId, $prodSlug);
    if (!$prod) error_page(404, 'کالا یافت نشد', 'کالای مورد نظر در این فروشگاه یافت نشد.');
    $prodId = (int)$prod['id'];
    $user = current_user();

    $catName = '';
    if (!empty($prod['category_id'])) {
        $cStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $cStmt->execute([$prod['category_id']]);
        $catName = (string)$cStmt->fetchColumn();
    }

    $isAvailable = (float)$prod['stock_quantity'] > 0;
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'befroosh.local');
    $productPageUrl = $baseUrl . "/s/" . urlencode($shop['slug']) . "/p/" . urlencode($prod['slug'] ?: (string)$prod['id']);

    layout_public_start($prod['title'] . ' | ' . $shop['name'], $shop, $user);
    ?>
    <div class="card" style="padding:28px; max-width:1000px; margin:0 auto;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:32px;">
            <!-- IMAGE -->
            <div>
                <div style="height:320px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                    <?php if (!empty($prod['image_path']) && file_exists(STORAGE_PATH . '/' . $prod['image_path'])): ?>
                        <img src="/storage/<?= e($prod['image_path']) ?>" alt="<?= e($prod['title']) ?>" style="max-height:100%; object-fit:contain;">
                    <?php else: ?>
                        <span style="color:#cbd5e1;"><?= icon('products', 48) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DETAILS & PURCHASE -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <?php if ($catName): ?><span class="badge badge-blue" style="margin-bottom:8px;"><?= e($catName) ?></span><?php endif; ?>
                        <h1 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin-bottom:8px;"><?= e($prod['title']) ?></h1>
                    </div>
                    <button type="button" class="btn btn-ghost fav-heart-btn" data-product-id="<?= $prodId ?>" onclick="window.BefrooshStore.toggleFav(<?= $prodId ?>, this)" title="نشان کردن">
                        <?= icon('heart', 24) ?>
                    </button>
                </div>

                <div style="font-size:1.25rem; font-weight:900; color:#059669; margin:16px 0;">
                    <?= format_irr((float)$prod['price']) ?>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:0.85rem; color:#475569;">
                    <div>وضعیت انبار: <?= $isAvailable ? '<strong style="color:#059669;">موجود در انبار</strong>' : '<strong style="color:#dc2626;">ناموجود</strong>' ?></div>
                    <div>کد محصول (SKU): <code><?= e($prod['sku'] ?: '—') ?></code></div>
                    <?php if (!empty($prod['barcode'])): ?><div>بارکد کالا: <code><?= e($prod['barcode']) ?></code></div><?php endif; ?>
                </div>

                <div style="color:#334155; line-height:1.9; font-size:0.92rem; margin-bottom:24px;">
                    <?= !empty($prod['description']) ? safe_html($prod['description']) : 'توضیحات تکمیلی برای این محصول ثبت نشده است.' ?>
                </div>

                <?php if ($isAvailable): ?>
                    <button type="button" class="btn btn-primary" style="padding:12px 24px; font-size:1rem; width:100%;" onclick="window.BefrooshStore.addToCart({ id: <?= $prodId ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($prod['title'])) ?>', price: <?= (float)$prod['price'] ?>, qty: 1 })">
                        <?= icon('cart', 18) ?> افزودن به سبد خرید
                    </button>
                <?php else: ?>
                    <button class="btn btn-outline" disabled style="width:100%; opacity:0.6;">این کالا در حال حاضر ناموجود است</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    layout_public_end($shop);
});
