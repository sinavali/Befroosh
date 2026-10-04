<?php
declare(strict_types=1);

/**
 * routes_storefront_branches.php
 * Public Storefronts for Businesses and Independent Branches (/b/{biz_slug} and /b/{biz_slug}/{branch_slug})
 */

// 1. Business Level Storefront: /b/{biz_slug}
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

    // Fetch business catalog products (where branch_id IS NULL or 0)
    $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND (branch_id IS NULL OR branch_id = 0) AND active = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 12");
    $stmt->execute([$shopId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start($shop['name'] . ' - شعب و کاتالوگ مرکزی', $shop, $user);
    ?>
    <div style="background:linear-gradient(135deg, #1e3a8a, #0f172a); border-radius:18px; padding:36px 28px; color:#fff; margin-bottom:32px;">
        <div style="max-width:800px;">
            <div style="display:inline-block; background:rgba(255,255,255,0.15); padding:4px 12px; border-radius:99px; font-size:0.8rem; font-weight:bold; margin-bottom:10px;">
                کسب‌وکار چند شعبه‌ای
            </div>
            <h1 style="font-size:1.8rem; font-weight:900; margin-bottom:10px; color:#fff;"><?= e($shop['name']) ?></h1>
            <p style="color:#cbd5e1; font-size:0.95rem; line-height:1.8; margin-bottom:16px;">
                <?= e($shop['description'] ?: 'ارائه‌دهنده کالاها و خدمات با شعب حضوری و توزیع آنلاین در سراسر کشور.') ?>
            </p>
            <div style="display:flex; gap:12px; flex-wrap:wrap; font-size:0.85rem; color:#94a3b8;">
                <?php if ($shop['phone']): ?><div>تلفن مرکزی: <strong><?= en_to_fa_digits($shop['phone']) ?></strong></div><?php endif; ?>
                <?php if ($shop['address']): ?><div>نشانی مرکزی: <strong><?= e($shop['address']) ?></strong></div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- BRANCHES SECTION -->
    <div style="margin-bottom:40px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h2 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;">شعب فعال کسب‌وکار</h2>
            <span style="font-size:0.85rem; color:#64748b;"><?= en_to_fa_digits((string)count($branches)) ?> شعبه فعال</span>
        </div>

        <?php if (empty($branches)): ?>
            <div class="card" style="padding:24px; text-align:center; color:#64748b;">
                شعبه مستقلی برای این کسب‌وکار ثبت نشده است. کاتالوگ مرکزی در دسترس است.
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                <?php foreach ($branches as $br): ?>
                    <div class="card" style="padding:20px; display:flex; flex-direction:column; justify-content:space-between; border-radius:14px; position:relative;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <h3 style="font-size:1.05rem; font-weight:800; color:#0f172a; margin:0;"><?= e($br['name']) ?></h3>
                                <?php if ($br['is_main']): ?>
                                    <span class="badge badge-blue">شعبه مرکزی</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($br['group_name'])): ?>
                                <div style="font-size:0.75rem; color:#059669; margin-bottom:8px; font-weight:bold;">
                                    ✓ عضو گروه ارسال متمرکز: <?= e($br['group_name']) ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-size:0.84rem; color:#64748b; line-height:1.7; margin-bottom:12px;">
                                <?php if ($br['address']): ?><div>📍 <?= e($br['address']) ?></div><?php endif; ?>
                                <?php if ($br['phone']): ?><div>📞 <?= en_to_fa_digits($br['phone']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <a href="/b/<?= e($shop['slug']) ?>/<?= e($br['slug']) ?>" class="btn btn-primary btn-sm" style="width:100%; text-align:center;">
                            ورود به ویترین شعبه ←
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- BUSINESS CATALOG PRODUCTS -->
    <?php if (!empty($products)): ?>
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h2 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;">کاتالوگ مرکزی کسب‌وکار</h2>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:18px;">
                <?php foreach ($products as $p): ?>
                    <div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative;">
                        <a href="/s/<?= e($shop['slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>">
                            <div style="height:140px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; overflow:hidden;">
                                <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                    <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; object-fit:contain;">
                                <?php else: ?>
                                    <span style="color:#cbd5e1;"><?= icon('products', 32) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 style="font-size:0.9rem; font-weight:bold; color:#1e293b; margin-bottom:8px; line-height:1.4;"><?= e($p['title']) ?></h3>
                        </a>
                        <div>
                            <div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:10px;"><?= format_irt((float)$p['price']) ?></div>
                            <a href="/s/<?= e($shop['slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>" class="btn btn-outline btn-sm" style="width:100%;">
                                مشاهده جزییات
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
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
    $stmt = $pdo->prepare("SELECT b.*, sg.name AS group_name, sg.shipping_cost AS group_shipping_cost, sg.free_shipping_threshold AS group_free_threshold FROM branches b LEFT JOIN shipping_groups sg ON sg.id = b.shipping_group_id WHERE b.business_id = ? AND b.slug = ? AND b.active = 1");
    $stmt->execute([$shopId, $branchSlug]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        error_page(404, 'شعبه یافت نشد', 'شعبه مورد نظر برای این کسب‌وکار یافت نشد یا غیرفعال است.');
    }

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
        $whereCatalog .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $prodStmt = $pdo->prepare("SELECT p.* FROM products p {$whereCatalog} ORDER BY p.id DESC LIMIT 40");
    $prodStmt->execute($params);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start($branch['name'] . ' - ' . $shop['name'], $shop, $user);
    ?>
    <!-- BRANCH HEADER -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding:24px 28px; margin-bottom:28px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <h1 style="font-size:1.5rem; font-weight:900; color:#0f172a; margin:0;"><?= e($branch['name']) ?></h1>
                    <?php if ($branch['is_main']): ?><span class="badge badge-blue">شعبه اصلی</span><?php endif; ?>
                </div>
                <div style="font-size:0.88rem; color:#64748b; margin-bottom:10px;">
                    شعبه رسمی کسب‌وکار <a href="/b/<?= e($shop['slug']) ?>" style="color:#2563eb; font-weight:bold;"><?= e($shop['name']) ?></a>
                </div>
                <div style="display:flex; gap:18px; flex-wrap:wrap; font-size:0.85rem; color:#475569;">
                    <?php if ($branch['address']): ?><div>📍 نشانی: <strong><?= e($branch['address']) ?></strong></div><?php endif; ?>
                    <?php if ($branch['phone']): ?><div>📞 تماس: <strong><?= en_to_fa_digits($branch['phone']) ?></strong></div><?php endif; ?>
                    <?php if ($branch['default_shipping_cost'] > 0): ?><div>📦 ارسال پیش‌فرض: <strong><?= format_irt((float)$branch['default_shipping_cost']) ?></strong></div><?php endif; ?>
                </div>
            </div>
            <a href="/b/<?= e($shop['slug']) ?>" class="btn btn-outline btn-sm">
                مشاهده همه شعب این کسب‌وکار
            </a>
        </div>

        <?php if (!empty($branch['group_name'])): ?>
            <div style="margin-top:16px; padding:10px 14px; background:#ecfdf5; border-radius:8px; border:1px solid #a7f3d0; font-size:0.84rem; color:#065f46;">
                🚚 <strong>گروه ارسال متمرکز (<?= e($branch['group_name']) ?>):</strong> خریدهای شما از این شعبه و سایر شعب هم‌گروه، با یک هزینه ارسال مشترک و به صورت یکجا تحویل می‌گردد.
            </div>
        <?php endif; ?>
    </div>

    <!-- SEARCH & CONTROLS -->
    <div style="margin-bottom:24px;">
        <form method="get" action="/b/<?= e($shop['slug']) ?>/<?= e($branch['slug']) ?>" style="display:flex; gap:10px; max-width:500px;">
            <input type="text" name="q" class="input" placeholder="جستجو در کالاهای این شعبه..." value="<?= e($q) ?>">
            <button class="btn btn-primary"><?= icon('search', 14) ?> جستجو</button>
            <?php if ($q !== ''): ?>
                <a href="/b/<?= e($shop['slug']) ?>/<?= e($branch['slug']) ?>" class="btn btn-outline">پاکسازی</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- PRODUCTS GRID -->
    <?php if (empty($products)): ?>
        <div class="card" style="padding:32px; text-align:center; color:#64748b;">
            <?= empty_state('کالایی در این شعبه یافت نشد', 'در حال حاضر محصولی با شرایط جستجو در ویترین این شعبه موجود نیست.', 'products') ?>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:20px;">
            <?php foreach ($products as $p): ?>
                <div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative;">
                    <a href="/b/<?= e($shop['slug']) ?>/<?= e($branch['slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>">
                        <div style="height:150px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; overflow:hidden;">
                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" style="max-height:100%; object-fit:contain;">
                            <?php else: ?>
                                <span style="color:#cbd5e1;"><?= icon('products', 32) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 style="font-size:0.92rem; font-weight:bold; color:#1e293b; margin-bottom:8px; line-height:1.4;"><?= e($p['title']) ?></h3>
                    </a>
                    <div>
                        <div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:12px;"><?= format_irt((float)$p['price']) ?></div>
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
    <?php endif; ?>
    <?php
    layout_public_end($shop);
});

// 3. Branch Product Detail: /b/{biz_slug}/{branch_slug}/p/{product}
route('GET', '/b/([^/]+)/([^/]+)/p/([^/]+)', [], function ($bizSlug, $branchSlug, $prodIdentifier) use ($pdo) {
    $shop = resolve_shop($bizSlug);
    if (!$shop) error_page(404, 'کسب‌وکار یافت نشد', 'کسب‌وکار یافت نشد.');
    $shopId = (int)$shop['id'];
    $user = current_user();

    $stmt = $pdo->prepare("SELECT * FROM branches WHERE business_id = ? AND slug = ? AND active = 1");
    $stmt->execute([$shopId, $branchSlug]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) error_page(404, 'شعبه یافت نشد', 'شعبه یافت نشد.');

    $prodStmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.shop_id = ? AND (p.slug = ? OR p.id = ?) AND p.active = 1 AND p.deleted_at IS NULL");
    $prodStmt->execute([$shopId, $prodIdentifier, is_numeric($prodIdentifier) ? (int)$prodIdentifier : 0]);
    $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prod) error_page(404, 'محصول یافت نشد', 'کالای مورد نظر در این فروشگاه یافت نشد.');

    layout_public_start($prod['title'] . ' - ' . $branch['name'], $shop, $user);
    ?>
    <div style="margin-bottom:16px;">
        <a href="/b/<?= e($shop['slug']) ?>/<?= e($branch['slug']) ?>" style="font-size:0.86rem; color:#2563eb; font-weight:bold;">
            ← بازگشت به کاتالوگ شعبه <?= e($branch['name']) ?>
        </a>
    </div>

    <div class="card" style="padding:28px; border-radius:18px;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:32px;">
            <div>
                <div style="height:320px; background:#f8fafc; border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid #e2e8f0;">
                    <?php if (!empty($prod['image_path']) && file_exists(STORAGE_PATH . '/' . $prod['image_path'])): ?>
                        <img src="/storage/<?= e($prod['image_path']) ?>" alt="<?= e($prod['title']) ?>" style="max-height:100%; object-fit:contain;">
                    <?php else: ?>
                        <span style="color:#cbd5e1;"><?= icon('products', 64) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div style="font-size:0.8rem; color:#64748b; margin-bottom:6px;">شعبه: <?= e($branch['name']) ?> | دسته: <?= e($prod['cat_name'] ?: 'عمومی') ?></div>
                <h1 style="font-size:1.5rem; font-weight:900; color:#0f172a; margin-bottom:14px; line-height:1.4;"><?= e($prod['title']) ?></h1>
                <div style="font-size:1.4rem; font-weight:900; color:#059669; margin-bottom:18px;"><?= format_irt((float)$prod['price']) ?></div>
                <div style="margin-bottom:20px; font-size:0.9rem; color:#475569; line-height:1.9;">
                    <?= !empty($prod['description']) ? $prod['description'] : '<p style="color:#94a3b8;">توضیحاتی برای این کالا ثبت نشده است.</p>' ?>
                </div>
                <div style="margin-bottom:24px; padding:12px; background:#f8fafc; border-radius:8px; font-size:0.85rem; color:#64748b;">
                    <div>وضعیت موجودی: <strong><?= (float)$prod['stock_quantity'] > 0 ? en_to_fa_digits((string)$prod['stock_quantity']) . ' عدد در انبار' : '<span style="color:#ef4444;">ناموجود</span>' ?></strong></div>
                    <?php if (!empty($prod['sku'])): ?><div>شناسه کالا (SKU): <code><?= e($prod['sku']) ?></code></div><?php endif; ?>
                </div>
                <?php if ((float)$prod['stock_quantity'] > 0): ?>
                    <button type="button" class="btn btn-primary" style="padding:12px 28px; font-size:1rem; width:100%;" onclick="window.BefrooshStore.addToCart({ id: <?= (int)$prod['id'] ?>, shop_id: <?= $shopId ?>, title: '<?= addslashes(e($prod['title'])) ?>', price: <?= (float)$prod['price'] ?>, qty: 1 })">
                        <?= icon('plus', 16) ?> افزودن به سبد خرید
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
