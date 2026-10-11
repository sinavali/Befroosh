<?php
declare(strict_types=1);

/**
 * routes_storefront_branches.php
 * Public Storefronts for Businesses and Independent Branches (/b/{biz_slug} and /b/{biz_slug}/{branch_slug})
 * Designed under Befroosh Design System: Neutral Precision, Apple Tactility, Flat & Clean
 */

// 1. Product Detail via Business URL: /b/{biz_slug}/p/{product}
route('GET', '/b/([^/]+)/p/([^/]+)', [], function ($bizSlug, $prodIdentifier) use ($pdo) {
    $shop = resolve_shop($bizSlug);
    if (!$shop) {
        error_page(404, 'کسب‌وکار یافت نشد', 'کسب‌وکار مورد نظر در سیستم یافت نشد.');
    }
    $shopId = (int)$shop['id'];
    $user = current_user();

    $prodStmt = $pdo->prepare("
        SELECT p.*, c.name AS cat_name, b.name AS branch_name, b.slug AS branch_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN branches b ON b.id = p.branch_id
        WHERE p.shop_id = ? AND (p.slug = ? OR p.id = ?) AND p.active = 1 AND p.deleted_at IS NULL
    ");
    $prodStmt->execute([$shopId, $prodIdentifier, is_numeric($prodIdentifier) ? (int)$prodIdentifier : 0]);
    $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        error_page(404, 'محصول یافت نشد', 'کالای مورد نظر در این فروشگاه یافت نشد یا حذف شده است.');
    }

    $stock = (float)$prod['stock_quantity'];
    $isAvailable = $stock > 0;

    layout_public_start($prod['title'] . ' | ' . $shop['name'], $shop, $user);
    ?>
    <div style="max-width:1100px; margin:0 auto;">
        <nav style="display:flex; align-items:center; gap:8px; font-size:0.84rem; color:var(--muted); margin-bottom:20px;">
            <a href="/b/<?= e($shop['slug']) ?>" style="color:var(--primary); font-weight:600;"><?= e($shop['name']) ?></a>
            <span>/</span>
            <?php if (!empty($prod['branch_name'])): ?>
                <a href="/b/<?= e($shop['slug']) ?>/<?= e($prod['branch_slug']) ?>" style="color:var(--primary); font-weight:600;"><?= e($prod['branch_name']) ?></a>
                <span>/</span>
            <?php endif; ?>
            <?php if (!empty($prod['cat_name'])): ?>
                <span><?= e($prod['cat_name']) ?></span>
                <span>/</span>
            <?php endif; ?>
            <span style="color:var(--ink);"><?= e($prod['title']) ?></span>
        </nav>

        <div class="card" style="padding:32px; border-radius:var(--radius-lg);">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:36px; align-items:start;">
                <!-- Product Gallery / Image -->
                <div style="background:var(--surface-muted); border:1px solid var(--border); border-radius:var(--radius); padding:24px; display:flex; align-items:center; justify-content:center; min-height:360px;">
                    <?php if (!empty($prod['image_path']) && file_exists(STORAGE_PATH . '/' . $prod['image_path'])): ?>
                        <img src="/storage/<?= e($prod['image_path']) ?>" alt="<?= e($prod['title']) ?>" style="max-width:100%; max-height:320px; object-fit:contain;">
                    <?php else: ?>
                        <span style="color:var(--placeholder);"><?= icon('products', 64) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Product Attributes & Purchasing -->
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div>
                            <?php if (!empty($prod['cat_name'])): ?>
                                <span class="badge badge-muted" style="margin-bottom:8px;"><?= e($prod['cat_name']) ?></span>
                            <?php endif; ?>
                            <h1 style="font-size:1.6rem; font-weight:800; color:var(--ink); line-height:1.35; margin:0;"><?= e($prod['title']) ?></h1>
                        </div>
                        <button type="button" class="btn btn-ghost fav-heart-btn" data-product-id="<?= (int)$prod['id'] ?>" onclick="window.BefrooshStore.toggleFav(<?= (int)$prod['id'] ?>, this)" title="نشان کردن کالا" style="padding:6px;">
                            <?= icon('heart', 22) ?>
                        </button>
                    </div>

                    <!-- Price readout -->
                    <div style="display:flex; align-items:baseline; gap:8px;">
                        <span style="font-size:1.6rem; font-weight:800; color:var(--emerald);" class="tabular-nums">
                            <?= number_format((float)$prod['price'], 0, '.', ',') ?>
                        </span>
                        <span style="font-size:0.95rem; font-weight:600; color:var(--muted);">تومان</span>
                    </div>

                    <!-- Stock Status Badge -->
                    <div>
                        <?php if ($stock > 5): ?>
                            <span class="badge badge-emerald">موجود در انبار (تحویل فوری)</span>
                        <?php elseif ($stock > 0): ?>
                            <span class="badge badge-amber">تنها <?= en_to_fa_digits((string)(int)$stock) ?> عدد در انبار باقی‌مانده</span>
                        <?php else: ?>
                            <span class="badge badge-rose">در حال حاضر ناموجود</span>
                        <?php endif; ?>
                    </div>

                    <!-- Specifications Box -->
                    <div style="background:var(--surface-muted); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 18px; font-size:0.86rem; color:var(--slate); display:flex; flex-direction:column; gap:6px;">
                        <div>شناسه کالا (SKU): <code class="font-mono"><?= e($prod['sku'] ?: '—') ?></code></div>
                        <?php if (!empty($prod['barcode'])): ?>
                            <div>بارکد محصول: <code class="font-mono"><?= e($prod['barcode']) ?></code></div>
                        <?php endif; ?>
                        <?php if (!empty($prod['branch_name'])): ?>
                            <div>توزیع اختصاصی از: <strong><?= e($prod['branch_name']) ?></strong></div>
                        <?php else: ?>
                            <div>توزیع متمرکز: <strong>انبار مرکزی کسب‌وکار</strong></div>
                        <?php endif; ?>
                        <?php if ((float)$prod['max_per_order'] > 0): ?>
                            <div style="color:var(--amber);">حداکثر سقف مجاز در هر سفارش: <strong><?= en_to_fa_digits((string)(float)$prod['max_per_order']) ?> <?= e($prod['unit'] ?: 'عدد') ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <!-- CTA Action Button -->
                    <div style="margin-top:8px;">
                        <?php if ($isAvailable): ?>
                            <button type="button" class="btn btn-primary btn-lg" style="width:100%; gap:10px;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$prod['id'] ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($prod['title'])) ?>', price: <?= (float)$prod['price'] ?>, qty: 1 })">
                                <?= icon('cart', 18) ?> افزودن به سبد خرید و رزرو کالا
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-lg" disabled style="width:100%; opacity:0.6; cursor:not-allowed;">
                                موجودی این کالا به اتمام رسیده است
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-top:36px; border-top:1px solid var(--border); padding-top:24px;">
                <h3 style="font-size:1.1rem; font-weight:700; color:var(--ink); margin-bottom:12px;">توضیحات و مشخصات تکمیلی</h3>
                <div style="color:var(--slate); line-height:1.9; font-size:0.92rem;">
                    <?= !empty($prod['description']) ? safe_html($prod['description']) : 'توضیحات تکمیلی برای این محصول درج نشده است.' ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    layout_public_end($shop);
});

// 2. Branch Storefront: /b/{biz_slug}/{branch_slug}
route('GET', '/b/([^/]+)/([^/]+)', [], function ($bizSlug, $branchSlug) use ($pdo) {
    $shop = resolve_shop($bizSlug);
    if (!$shop) {
        error_page(404, 'کسب‌وکار یافت نشد', 'کسب‌وکار مورد نظر یافت نشد.');
    }
    $shopId = (int)$shop['id'];
    $user = current_user();

    // Fetch branch
    $stmt = $pdo->prepare("
        SELECT b.*, sg.name AS group_name, sg.shipping_cost AS group_shipping_cost, sg.free_shipping_threshold AS group_free_threshold
        FROM branches b
        LEFT JOIN shipping_groups sg ON sg.id = b.shipping_group_id
        WHERE b.business_id = ? AND b.slug = ? AND b.active = 1
    ");
    $stmt->execute([$shopId, $branchSlug]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        error_page(404, 'شعبه یافت نشد', 'شعبه مورد نظر برای این کسب‌وکار یافت نشد یا غیرفعال است.');
    }

    // Fetch all active branches of business for switcher
    $allBranches = $pdo->prepare("SELECT id, name, slug, is_main FROM branches WHERE business_id = ? AND active = 1 ORDER BY is_main DESC, name ASC");
    $allBranches->execute([$shopId]);
    $branchesList = $allBranches->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories
    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? AND active = 1 ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$shopId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $catalogMode = $branch['catalog_mode'] ?? 'both';
    $params = [$shopId];

    if ($catalogMode === 'branch_only') {
        $whereCatalog = "WHERE p.shop_id = ? AND p.branch_id = ? AND p.active = 1 AND p.deleted_at IS NULL";
        $params[] = (int)$branch['id'];
    } elseif ($catalogMode === 'business_only') {
        $whereCatalog = "WHERE p.shop_id = ? AND (p.branch_id IS NULL OR p.branch_id = 0) AND p.active = 1 AND p.deleted_at IS NULL";
    } else { // both
        $whereCatalog = "WHERE p.shop_id = ? AND (p.branch_id = ? OR p.branch_id IS NULL OR p.branch_id = 0) AND p.active = 1 AND p.deleted_at IS NULL";
        $params[] = (int)$branch['id'];
    }

    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $whereCatalog .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $selectedCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
    if ($selectedCategory > 0) {
        $whereCatalog .= " AND p.category_id = ?";
        $params[] = $selectedCategory;
    }

    $prodStmt = $pdo->prepare("
        SELECT p.*, c.name AS cat_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        {$whereCatalog}
        ORDER BY p.id DESC
        LIMIT 60
    ");
    $prodStmt->execute($params);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start($branch['name'] . ' - ' . $shop['name'], $shop, $user);
    ?>
    <!-- STORE HEADER & BRANCH SWITCHER BAR -->
    <div class="card" style="padding:20px 24px; border-radius:var(--radius-lg); margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                    <h1 style="font-size:1.4rem; font-weight:800; color:var(--ink); margin:0;"><?= e($branch['name']) ?></h1>
                    <?php if ($branch['is_main']): ?>
                        <span class="badge badge-blue">شعبه اصلی</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.84rem; color:var(--muted);">
                    شعبه رسمی <a href="/b/<?= e($shop['slug']) ?>" style="color:var(--primary); font-weight:700;"><?= e($shop['name']) ?></a>
                    <?php if ($branch['address']): ?> — نشانی: <?= e($branch['address']) ?><?php endif; ?>
                    <?php if ($branch['phone']): ?> — تلفن: <span dir="ltr"><?= en_to_fa_digits($branch['phone']) ?></span><?php endif; ?>
                </div>
            </div>

            <!-- Branch context switcher pills -->
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:0.8rem; font-weight:600; color:var(--slate);">انتخاب شعبه:</span>
                <a href="/b/<?= e($shop['slug']) ?>" class="btn btn-outline btn-sm">همه کاتالوگ</a>
                <?php foreach ($branchesList as $b): ?>
                    <a href="/b/<?= e($shop['slug']) ?>/<?= e($b['slug']) ?>" class="btn btn-sm <?= $b['slug'] === $branchSlug ? 'btn-primary' : 'btn-outline' ?>">
                        <?= e($b['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($branch['group_name'])): ?>
            <div style="margin-top:14px; padding:10px 14px; background:#ecfdf5; border-radius:var(--radius-sm); border:1px solid #a7f3d0; font-size:0.82rem; color:#065f46;">
                <strong>گروه ارسال متمرکز (<?= e($branch['group_name']) ?>):</strong> سفارش‌های ثبت‌شده از این شعبه با هزینه ارسال یکپارچه تحویل داده می‌شوند.
            </div>
        <?php endif; ?>
    </div>

    <!-- LIVE SEARCH AUTOCOMPLETE & CATEGORY FILTER PILLS -->
    <div style="margin-bottom:24px; display:flex; flex-direction:column; gap:14px;">
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div style="flex:1; max-width:540px; position:relative;">
                <form method="get" action="/b/<?= e($shop['slug']) ?>/<?= e($branchSlug) ?>" id="searchForm">
                    <input type="text" name="q" id="storeLiveSearchInput" class="input" placeholder="جستجوی عنوان، بارکد یا کد کالا... (میانبر /)" value="<?= e($q) ?>" autocomplete="off">
                    <?php if ($selectedCategory > 0): ?>
                        <input type="hidden" name="category" value="<?= $selectedCategory ?>">
                    <?php endif; ?>
                </form>
                <div id="autocompleteDropdown" style="display:none; position:absolute; top:calc(100% + 4px); right:0; left:0; background:#fff; border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-lift); z-index:100; max-height:300px; overflow-y:auto;"></div>
            </div>

            <?php if ($q !== '' || $selectedCategory > 0): ?>
                <a href="/b/<?= e($shop['slug']) ?>/<?= e($branchSlug) ?>" class="btn btn-outline btn-sm">پاکسازی فیلترها</a>
            <?php endif; ?>
        </div>

        <!-- Category pills -->
        <?php if (!empty($categories)): ?>
            <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; align-items:center;">
                <a href="/b/<?= e($shop['slug']) ?>/<?= e($branchSlug) ?><?= $q ? '?q=' . urlencode($q) : '' ?>" class="badge <?= $selectedCategory === 0 ? 'badge-blue' : 'badge-muted' ?>" style="padding:6px 14px; font-size:0.82rem; cursor:pointer;">
                    همه دسته‌ها
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="/b/<?= e($shop['slug']) ?>/<?= e($branchSlug) ?>?category=<?= (int)$cat['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?>" class="badge <?= $selectedCategory === (int)$cat['id'] ? 'badge-blue' : 'badge-muted' ?>" style="padding:6px 14px; font-size:0.82rem; cursor:pointer;">
                        <?= e($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RESPONSIVE PRODUCT GRID -->
    <?php if (empty($products)): ?>
        <div class="card" style="padding:40px 20px; text-align:center;">
            <?= empty_state('محصولی در این شعبه یافت نشد', 'کالایی با شرایط جستجوی انتخابی موجود نیست یا موجودی آن خاتمه یافته است.', 'products') ?>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:20px;">
            <?php foreach ($products as $p): ?>
                <?php
                $stock = (float)$p['stock_quantity'];
                $isAvailable = $stock > 0;
                $pUrl = "/b/" . e($shop['slug']) . "/p/" . e($p['slug'] ?: (string)$p['id']);
                ?>
                <div class="card" style="display:flex; flex-direction:column; justify-content:space-between; padding:16px; border-radius:var(--radius); margin:0;">
                    <div>
                        <!-- Image Container with Hover Lift -->
                        <a href="<?= $pUrl ?>" style="display:block; height:160px; background:var(--surface-muted); border-radius:var(--radius-sm); border:1px solid var(--border); overflow:hidden; position:relative; margin-bottom:12px; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; max-width:100%; object-fit:contain; transition:transform 0.2s ease;">
                            <?php else: ?>
                                <span style="color:var(--placeholder);"><?= icon('products', 36) ?></span>
                            <?php endif; ?>

                            <!-- Live stock badge inside image -->
                            <div style="position:absolute; top:8px; right:8px;">
                                <?php if ($stock > 5): ?>
                                    <span class="badge badge-emerald" style="font-size:0.68rem;">موجود</span>
                                <?php elseif ($stock > 0): ?>
                                    <span class="badge badge-amber" style="font-size:0.68rem;"><?= en_to_fa_digits((string)(int)$stock) ?> عدد باقی‌مانده</span>
                                <?php else: ?>
                                    <span class="badge badge-rose" style="font-size:0.68rem;">ناموجود</span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div style="font-size:0.75rem; color:var(--muted); margin-bottom:4px;"><?= e($p['cat_name'] ?: 'عمومی') ?></div>
                        <a href="<?= $pUrl ?>">
                            <h3 style="font-size:0.92rem; font-weight:700; color:var(--ink); line-height:1.45; margin-bottom:10px; min-height:42px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                <?= e($p['title']) ?>
                            </h3>
                        </a>
                    </div>

                    <div>
                        <!-- Price -->
                        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:12px;">
                            <span style="font-size:1.1rem; font-weight:800; color:var(--emerald);" class="tabular-nums">
                                <?= number_format((float)$p['price'], 0, '.', ',') ?>
                            </span>
                            <span style="font-size:0.75rem; color:var(--muted); font-weight:600;">تومان</span>
                        </div>

                        <!-- Add to Bag CTA -->
                        <?php if ($isAvailable): ?>
                            <button type="button" class="btn btn-primary btn-sm" style="width:100%; gap:6px;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$p['id'] ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($p['title'])) ?>', price: <?= (float)$p['price'] ?>, qty: 1 })">
                                <?= icon('cart', 14) ?> افزودن به سبد
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" disabled style="width:100%; opacity:0.6; cursor:not-allowed;">ناموجود</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
    // Live Search Autocomplete
    (function(){
        const input = document.getElementById('storeLiveSearchInput');
        const dropdown = document.getElementById('autocompleteDropdown');
        if (!input || !dropdown) return;

        // Quick shortcut '/' to focus
        document.addEventListener('keydown', function(e){
            if (e.key === '/' && document.activeElement !== input) {
                e.preventDefault();
                input.focus();
            }
        });

        let timer = null;
        input.addEventListener('input', function(){
            clearTimeout(timer);
            const val = input.value.trim();
            if (val.length < 2) {
                dropdown.style.display = 'none';
                return;
            }
            timer = setTimeout(function(){
                fetch('/api/shops/<?= e($shop['slug']) ?>/products?branch_id=<?= (int)$branch['id'] ?>&limit=10&q=' + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(res => {
                        const items = (res.products || res.data || []);
                        if (items.length === 0) {
                            dropdown.style.display = 'none';
                            return;
                        }
                        dropdown.innerHTML = items.map(p => `
                            <a href="/b/<?= e($shop['slug']) ?>/p/${p.slug || p.id}" style="display:flex; align-items:center; gap:12px; padding:10px 14px; border-bottom:1px solid #f1f5f9; transition:background 0.1s;">
                                <div style="font-size:0.85rem; font-weight:700; color:#0f172a; flex:1;">${p.title}</div>
                                <div style="font-size:0.82rem; color:#059669; font-weight:800;" class="tabular-nums">${Number(p.price).toLocaleString('fa-IR')} تومان</div>
                            </a>
                        `).join('');
                        dropdown.style.display = 'block';
                    }).catch(() => { dropdown.style.display = 'none'; });
            }, 250);
        });

        document.addEventListener('click', function(e){
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    })();
    </script>
    <?php
    layout_public_end($shop);
});

// 3. Business Level Storefront: /b/{biz_slug}
route('GET', '/b/([^/]+)', [], function ($bizSlug) use ($pdo) {
    $shop = resolve_shop($bizSlug);
    if (!$shop) {
        error_page(404, 'کسب‌وکار یافت نشد', 'کسب‌وکار مورد نظر در سیستم ثبت نشده یا غیرفعال است.');
    }
    $shopId = (int)$shop['id'];
    $user = current_user();

    // Fetch active branches
    $branches = [];
    try {
        $stmt = $pdo->prepare("SELECT b.*, sg.name AS group_name FROM branches b LEFT JOIN shipping_groups sg ON sg.id = b.shipping_group_id WHERE b.business_id = ? AND b.active = 1 ORDER BY b.is_main DESC, b.name ASC");
        $stmt->execute([$shopId]);
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // Fetch categories
    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? AND active = 1 ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$shopId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Filters
    $q = trim($_GET['q'] ?? '');
    $selectedCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;

    $where = "WHERE p.shop_id = ? AND p.active = 1 AND p.deleted_at IS NULL";
    $params = [$shopId];

    if ($q !== '') {
        $where .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    if ($selectedCategory > 0) {
        $where .= " AND p.category_id = ?";
        $params[] = $selectedCategory;
    }

    $prodStmt = $pdo->prepare("
        SELECT p.*, c.name AS cat_name, b.name AS branch_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN branches b ON b.id = p.branch_id
        {$where}
        ORDER BY p.id DESC
        LIMIT 48
    ");
    $prodStmt->execute($params);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start($shop['name'] . ' - شعب و کاتالوگ مرکزی', $shop, $user);
    ?>
    <!-- HERO HEADER -->
    <div style="background:var(--canvas); border:1px solid var(--border); border-radius:var(--radius-lg); padding:32px 28px; margin-bottom:28px;">
        <div style="max-width:800px;">
            <div style="display:inline-flex; align-items:center; gap:6px; background:#eff6ff; border:1px solid #dbeafe; padding:4px 12px; border-radius:var(--radius-full); font-size:0.78rem; font-weight:700; color:var(--primary); margin-bottom:12px;">
                کسب‌وکار متصل به بفروش | شعب حضوری و فروش آنلاین
            </div>
            <h1 style="font-size:1.85rem; font-weight:800; color:var(--ink); margin-bottom:10px;"><?= e($shop['name']) ?></h1>
            <p style="color:var(--slate); font-size:0.95rem; line-height:1.8; margin-bottom:16px;">
                <?= e($shop['description'] ?: 'ارائه‌دهنده کالاها و خدمات با شعب حضوری و توزیع آنلاین در سراسر کشور با رزرو ۴ روزه کالا.') ?>
            </p>
            <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:0.85rem; color:var(--muted);">
                <?php if ($shop['phone']): ?><div>تلفن مرکزی: <strong dir="ltr"><?= en_to_fa_digits($shop['phone']) ?></strong></div><?php endif; ?>
                <?php if ($shop['address']): ?><div>نشانی مرکزی: <strong><?= e($shop['address']) ?></strong></div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- BRANCH CONTEXT SWITCHER -->
    <?php if (!empty($branches)): ?>
        <div style="margin-bottom:32px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="font-size:1.2rem; font-weight:800; color:var(--ink); margin:0;">شعب فعال کسب‌وکار (انتخاب شعبه)</h2>
                <span style="font-size:0.82rem; color:var(--muted);"><?= en_to_fa_digits((string)count($branches)) ?> شعبه فعال</span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                <?php foreach ($branches as $br): ?>
                    <div class="card" style="padding:18px; display:flex; flex-direction:column; justify-content:space-between; border-radius:var(--radius); margin:0;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <h3 style="font-size:1rem; font-weight:700; color:var(--ink); margin:0;"><?= e($br['name']) ?></h3>
                                <?php if ($br['is_main']): ?>
                                    <span class="badge badge-blue">مرکزی</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($br['group_name'])): ?>
                                <div style="font-size:0.74rem; color:var(--emerald); margin-bottom:6px; font-weight:600;">
                                    ارسال متمرکز: <?= e($br['group_name']) ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-size:0.82rem; color:var(--muted); line-height:1.6; margin-bottom:12px;">
                                <?php if ($br['address']): ?><div>نشانی: <?= e($br['address']) ?></div><?php endif; ?>
                                <?php if ($br['phone']): ?><div>تلفن: <span dir="ltr"><?= en_to_fa_digits($br['phone']) ?></span></div><?php endif; ?>
                            </div>
                        </div>
                        <a href="/b/<?= e($shop['slug']) ?>/<?= e($br['slug']) ?>" class="btn btn-outline btn-sm" style="width:100%; justify-content:center;">
                            ورود به ویترین این شعبه
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- LIVE SEARCH & CATEGORY PILLS -->
    <div style="margin-bottom:24px; display:flex; flex-direction:column; gap:14px;">
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div style="flex:1; max-width:540px; position:relative;">
                <form method="get" action="/b/<?= e($shop['slug']) ?>" id="bizSearchForm">
                    <input type="text" name="q" id="bizLiveSearchInput" class="input" placeholder="جستجوی کالا، بارکد یا شناسه... (میانبر /)" value="<?= e($q) ?>" autocomplete="off">
                    <?php if ($selectedCategory > 0): ?>
                        <input type="hidden" name="category" value="<?= $selectedCategory ?>">
                    <?php endif; ?>
                </form>
                <div id="bizAutocompleteDropdown" style="display:none; position:absolute; top:calc(100% + 4px); right:0; left:0; background:#fff; border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-lift); z-index:100; max-height:300px; overflow-y:auto;"></div>
            </div>

            <?php if ($q !== '' || $selectedCategory > 0): ?>
                <a href="/b/<?= e($shop['slug']) ?>" class="btn btn-outline btn-sm">نمایش همه</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($categories)): ?>
            <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; align-items:center;">
                <a href="/b/<?= e($shop['slug']) ?><?= $q ? '?q=' . urlencode($q) : '' ?>" class="badge <?= $selectedCategory === 0 ? 'badge-blue' : 'badge-muted' ?>" style="padding:6px 14px; font-size:0.82rem; cursor:pointer;">
                    همه کالاها
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="/b/<?= e($shop['slug']) ?>?category=<?= (int)$cat['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?>" class="badge <?= $selectedCategory === (int)$cat['id'] ? 'badge-blue' : 'badge-muted' ?>" style="padding:6px 14px; font-size:0.82rem; cursor:pointer;">
                        <?= e($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RESPONSIVE PRODUCT GRID -->
    <?php if (empty($products)): ?>
        <div class="card" style="padding:40px 20px; text-align:center;">
            <?= empty_state('محصولی یافت نشد', 'کالایی با شرایط جستجو یافت نشد.', 'products') ?>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:20px;">
            <?php foreach ($products as $p): ?>
                <?php
                $stock = (float)$p['stock_quantity'];
                $isAvailable = $stock > 0;
                $pUrl = "/b/" . e($shop['slug']) . "/p/" . e($p['slug'] ?: (string)$p['id']);
                ?>
                <div class="card" style="display:flex; flex-direction:column; justify-content:space-between; padding:16px; border-radius:var(--radius); margin:0;">
                    <div>
                        <!-- Image -->
                        <a href="<?= $pUrl ?>" style="display:block; height:160px; background:var(--surface-muted); border-radius:var(--radius-sm); border:1px solid var(--border); overflow:hidden; position:relative; margin-bottom:12px; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; max-width:100%; object-fit:contain;">
                            <?php else: ?>
                                <span style="color:var(--placeholder);"><?= icon('products', 36) ?></span>
                            <?php endif; ?>

                            <!-- Live stock badge inside image -->
                            <div style="position:absolute; top:8px; right:8px;">
                                <?php if ($stock > 5): ?>
                                    <span class="badge badge-emerald" style="font-size:0.68rem;">موجود</span>
                                <?php elseif ($stock > 0): ?>
                                    <span class="badge badge-amber" style="font-size:0.68rem;"><?= en_to_fa_digits((string)(int)$stock) ?> باقی‌مانده</span>
                                <?php else: ?>
                                    <span class="badge badge-rose" style="font-size:0.68rem;">ناموجود</span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div style="font-size:0.75rem; color:var(--muted); margin-bottom:4px;">
                            <?= e($p['cat_name'] ?: 'عمومی') ?>
                            <?php if (!empty($p['branch_name'])): ?> • <span style="color:var(--primary);"><?= e($p['branch_name']) ?></span><?php endif; ?>
                        </div>
                        <a href="<?= $pUrl ?>">
                            <h3 style="font-size:0.92rem; font-weight:700; color:var(--ink); line-height:1.45; margin-bottom:10px; min-height:42px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                <?= e($p['title']) ?>
                            </h3>
                        </a>
                    </div>

                    <div>
                        <!-- Price -->
                        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:12px;">
                            <span style="font-size:1.1rem; font-weight:800; color:var(--emerald);" class="tabular-nums">
                                <?= number_format((float)$p['price'], 0, '.', ',') ?>
                            </span>
                            <span style="font-size:0.75rem; color:var(--muted); font-weight:600;">تومان</span>
                        </div>

                        <!-- Add to Bag CTA -->
                        <?php if ($isAvailable): ?>
                            <button type="button" class="btn btn-primary btn-sm" style="width:100%; gap:6px;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$p['id'] ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($p['title'])) ?>', price: <?= (float)$p['price'] ?>, qty: 1 })">
                                <?= icon('cart', 14) ?> افزودن به سبد
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" disabled style="width:100%; opacity:0.6; cursor:not-allowed;">ناموجود</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
    (function(){
        const input = document.getElementById('bizLiveSearchInput');
        const dropdown = document.getElementById('bizAutocompleteDropdown');
        if (!input || !dropdown) return;

        document.addEventListener('keydown', function(e){
            if (e.key === '/' && document.activeElement !== input) {
                e.preventDefault();
                input.focus();
            }
        });

        let timer = null;
        input.addEventListener('input', function(){
            clearTimeout(timer);
            const val = input.value.trim();
            if (val.length < 2) {
                dropdown.style.display = 'none';
                return;
            }
            timer = setTimeout(function(){
                fetch('/api/shops/<?= e($shop['slug']) ?>/products?limit=12&q=' + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(res => {
                        const items = (res.products || res.data || []);
                        if (items.length === 0) {
                            dropdown.style.display = 'none';
                            return;
                        }
                        dropdown.innerHTML = items.map(p => `
                            <a href="/b/<?= e($shop['slug']) ?>/p/${p.slug || p.id}" style="display:flex; align-items:center; gap:12px; padding:10px 14px; border-bottom:1px solid #f1f5f9; transition:background 0.1s;">
                                <div style="font-size:0.85rem; font-weight:700; color:#0f172a; flex:1;">${p.title}</div>
                                <div style="font-size:0.82rem; color:#059669; font-weight:800;" class="tabular-nums">${Number(p.price).toLocaleString('fa-IR')} تومان</div>
                            </a>
                        `).join('');
                        dropdown.style.display = 'block';
                    }).catch(() => { dropdown.style.display = 'none'; });
            }, 250);
        });

        document.addEventListener('click', function(e){
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    })();
    </script>
    <?php
    layout_public_end($shop);
});
