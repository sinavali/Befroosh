<?php
declare(strict_types=1);

/**
 * routes_public.php
 * Public Shop Landing & Single Product Pages
 * - UTF-8 human-readable URLs (truncated safely to 150 chars)
 * - SEO & AEO (AI Engine Optimization) with complete Schema.org JSON-LD
 * - Dynamic QR generation for Shop Landing, Product Page & Direct Buy link
 * - Direct buy & add-to-cart workflows
 * - Editable shortcuts for Shop Owner & Shop Manager
 * - Full filter & sort capabilities
 */

// Helper to resolve shop from slug or ID
function resolve_shop(PDO $pdo, string $slugOrId): ?array
{
    $decoded = urldecode($slugOrId);
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE (slug = ? OR id = ?) AND active = 1");
    $stmt->execute([$decoded, is_numeric($decoded) ? (int)$decoded : 0]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);
    return $shop ?: null;
}

// Helper to resolve product from shop and slug or ID
function resolve_product(PDO $pdo, int $shopId, string $slugOrId): ?array
{
    $decoded = urldecode($slugOrId);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND (slug = ? OR id = ?) AND deleted_at IS NULL");
    $stmt->execute([$shopId, $decoded, is_numeric($decoded) ? (int)$decoded : 0]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    return $prod ?: null;
}

// -------------------------------------------------------------
// 1. PUBLIC SHOP LANDING PAGE: /s/{slug}
// -------------------------------------------------------------
route('GET', '/s/([^/]+)', [], function ($shopSlug) use ($pdo) {
    $user = current_user();
    $shop = resolve_shop($pdo, $shopSlug);

    if (!$shop) {
        error_page(404, 'فروشگاه یافت نشد', 'فروشگاه مورد نظر وجود ندارد یا غیرفعال شده است.');
    }

    $shopId = (int)$shop['id'];
    $canManage = can_manage_shop($user, $shopId);

    // Fetch active bank cards
    $activeCards = get_shop_active_cards($shopId);

    // Fetch categories for this shop
    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? AND active = 1 ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$shopId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Filters and Sorting
    $search = trim($_GET['q'] ?? '');
    $catFilter = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
    $minPrice = !empty($_GET['min_price']) ? (float)fa_to_en_digits($_GET['min_price']) : 0;
    $maxPrice = !empty($_GET['max_price']) ? (float)fa_to_en_digits($_GET['max_price']) : 0;
    $inStockOnly = !empty($_GET['in_stock']) ? 1 : 0;
    $sort = $_GET['sort'] ?? 'newest';

    $where = "WHERE p.shop_id = ? AND p.active = 1 AND p.deleted_at IS NULL";
    $params = [$shopId];

    if ($search !== '') {
        $where .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($catFilter > 0) {
        $where .= " AND p.category_id = ?";
        $params[] = $catFilter;
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

    $prodStmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY $sortSql");
    $prodStmt->execute($params);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    $fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'befroosh.local') . "/s/" . urlencode($shop['slug']);

    // SEO & AEO Structured Data
    $seoTitle = e($shop['name']) . " | فروشگاه آنلاین و سفارش مستقیم";
    $seoDesc = mb_substr(strip_tags($shop['description'] ?: "خرید آنلاین انواع کالا با پرداخت امن کارت به کارت و ارسال به سراسر کشور از فروشگاه {$shop['name']}"), 0, 160);

    $jsonLd = [
        "@context" => "https://schema.org",
        "@graph" => [
            [
                "@type" => "Store",
                "@id" => $fullUrl . "#store",
                "name" => $shop['name'],
                "description" => $seoDesc,
                "url" => $fullUrl,
                "telephone" => $shop['phone'] ?: "",
                "address" => [
                    "@type" => "PostalAddress",
                    "streetAddress" => $shop['address'] ?: "ایران",
                    "addressCountry" => "IR"
                ],
                "currenciesAccepted" => "IRR",
                "paymentAccepted" => "Card to Card (کارت به کارت)",
            ],
            [
                "@type" => "FAQPage",
                "mainEntity" => [
                    [
                        "@type" => "Question",
                        "name" => "روش پرداخت در فروشگاه {$shop['name']} چگونه است؟",
                        "acceptedAnswer" => [
                            "@type" => "Answer",
                            "text" => "پرداخت به صورت مستقیم کارت‌به‌کارت انجام می‌شود. خریدار پس از واریز به یکی از شماره کارت‌های معتبر فروشگاه، تصویر فیش و شماره پیگیری را بارگذاری می‌نماید."
                        ]
                    ],
                    [
                        "@type" => "Question",
                        "name" => "مدت زمان رزرو کالا پس از ثبت سفارش چقدر است؟",
                        "acceptedAnswer" => [
                            "@type" => "Answer",
                            "text" => "پس از ثبت سفارش، موجودی اقلام به مدت " . en_to_fa_digits((string)($shop['reservation_days'] ?: 4)) . " روز کاری برای خریدار در انبار رزرو می‌گردد."
                        ]
                    ]
                ]
            ],
            [
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    ["@type" => "ListItem", "position" => 1, "name" => "سامانه سفارشات", "item" => "/shops"],
                    ["@type" => "ListItem", "position" => 2, "name" => $shop['name'], "item" => $fullUrl]
                ]
            ]
        ]
    ];

    layout_start($shop['name'], $user ?: ['role' => 'customer', 'nickname' => 'کاربر مهمان']);
    ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <!-- SHOP HERO -->
    <div class="card mb-3" style="background:linear-gradient(135deg, #1e3a8a, #0f172a); color:#fff; border:none; padding:24px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
            <div style="flex:1; min-width:280px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <div style="width:48px; height:48px; border-radius:10px; background:#3b82f6; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800;">
                        <?= e(mb_substr($shop['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h1 style="font-size:1.5rem; margin:0; color:#fff;"><?= e($shop['name']) ?></h1>
                        <span class="badge badge-emerald" style="margin-top:4px;">فروشگاه معتبر و فعال</span>
                    </div>
                </div>

                <p style="color:#cbd5e1; font-size:0.9rem; line-height:1.6; margin:10px 0;">
                    <?= nl2br(e($shop['description'] ?: 'ارائه‌دهنده باکیفیت‌ترین محصولات و ارسال مطمئن به سراسر ایران.')) ?>
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:16px; font-size:0.82rem; color:#94a3b8;">
                    <?php if ($shop['phone']): ?>
                        <div><?= icon('user', 14) ?> تلفن: <span dir="ltr"><?= format_phone($shop['phone']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($shop['address']): ?>
                        <div><?= icon('location', 14) ?> نشانی: <?= e($shop['address']) ?></div>
                    <?php endif; ?>
                    <div><?= icon('clock', 14) ?> مهلت رزرو کالا: <strong><?= en_to_fa_digits((string)($shop['reservation_days'] ?: 4)) ?> روز</strong></div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.3);" onclick="openQrModal('<?= e($fullUrl) ?>', 'QR کد ویترین فروشگاه')">
                    <?= icon('settings', 14) ?> بارکد QR فروشگاه
                </button>
                <?php if ($canManage): ?>
                    <a class="btn btn-success" href="/shop/settings">
                        <?= icon('edit', 14) ?> مدیریت فروشگاه
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- BANK CARDS NOTICE -->
        <?php if (!empty($activeCards)): ?>
            <div style="margin-top:18px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.15); display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <span style="font-size:0.8rem; font-weight:700; color:#cbd5e1;">روش پرداخت: فقط کارت‌به‌کارت</span>
                <?php foreach ($activeCards as $c): ?>
                    <div style="background:rgba(255,255,255,0.08); padding:5px 10px; border-radius:6px; font-size:0.8rem; font-family:monospace; direction:ltr;">
                        <?= format_card_number($c['card_number']) ?> (<?= e($c['card_holder']) ?> - <?= e($c['bank_name'] ?: 'بانک') ?>)
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="filter-bar">
                <input class="input" type="text" name="q" placeholder="جستجوی کالا، بارکد یا کد کالا..." value="<?= e($search) ?>" data-barcode-input style="min-width:220px;">

                <select class="select" name="category">
                    <option value="">همه دسته‌بندی‌ها</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
                    <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>بیشترین موجودی</option>
                </select>

                <label class="form-check" style="margin:0; font-size:0.85rem;">
                    <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?>>
                    <span>فقط کالاهای موجود</span>
                </label>

                <button class="btn btn-outline"><?= icon('filter', 14) ?> اعمال فیلتر</button>
                <button type="button" class="btn btn-outline" onclick="BefrooshScanner.openCamera(function(code){ document.querySelector('[data-barcode-input]').value = code; document.forms[0].submit(); })">
                    دوربین بارکدخوان
                </button>
                <?php if ($search || $catFilter || $minPrice || $maxPrice || $inStockOnly): ?>
                    <a class="btn btn-ghost" href="/s/<?= urlencode($shop['slug']) ?>">پاک‌سازی فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- PRODUCT CATALOG GRID -->
    <?php if (empty($products)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('کالایی یافت نشد', 'با شرایط فیلتر انتخاب شده، هیچ محصولی در این فروشگاه موجود نیست.', 'products') ?>
            </div>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px;">
            <?php foreach ($products as $p): ?>
                <?php
                $isAvailable = (float)$p['stock_quantity'] > 0;
                $prodUrl = "/s/" . urlencode($shop['slug']) . "/p/" . urlencode($p['slug'] ?: (string)$p['id']);
                ?>
                <article class="card" style="display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <div>
                        <!-- Product Image -->
                        <div style="height:180px; background:#f9fafb; border-bottom:1px solid #f3f4f6; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                <img src="/storage/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span style="color:#9ca3af; font-size:0.85rem;">تصویر کالا</span>
                            <?php endif; ?>

                            <?php if (!$isAvailable): ?>
                                <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1rem;">
                                    ناموجود
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Product Content -->
                        <div class="card-body" style="padding:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <span style="font-size:0.75rem; color:#6b7280;"><?= e($p['category_name'] ?: 'دسته‌بندی نشده') ?></span>
                                <?php if ($isAvailable): ?>
                                    <span class="badge badge-emerald">موجود (<?= en_to_fa_digits((string)$p['stock_quantity']) ?>)</span>
                                <?php endif; ?>
                            </div>

                            <h2 style="font-size:1rem; font-weight:700; margin-bottom:8px; line-height:1.4;">
                                <a href="<?= e($prodUrl) ?>" style="color:#111827; text-decoration:none;">
                                    <?= e($p['title']) ?>
                                </a>
                            </h2>

                            <div style="font-size:1.1rem; font-weight:800; color:#1d4ed8; margin:10px 0;">
                                <?= format_irr((float)$p['price']) ?>
                            </div>

                            <?php if (!empty($p['max_per_order']) && $p['max_per_order'] > 0): ?>
                                <div style="font-size:0.72rem; color:#6b7280;">سقف سفارش: <?= en_to_fa_digits((string)$p['max_per_order']) ?> <?= e($p['unit'] ?: 'عدد') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="card-footer" style="padding:10px 14px; background:#f9fafb; display:flex; gap:6px;">
                        <?php if ($isAvailable): ?>
                            <a class="btn btn-primary btn-sm" href="/buy/<?= (int)$p['id'] ?>" style="flex:1;">
                                خرید سریع
                            </a>
                            <form method="post" action="/cart/add" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn btn-outline btn-sm" title="افزودن به سبد"><?= icon('plus', 13) ?></button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" disabled style="flex:1; opacity:0.6;">ناموجود</button>
                        <?php endif; ?>

                        <form method="post" action="/bookmarks/toggle" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-outline btn-sm" title="نشان کردن"><?= icon('shield', 13) ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- QR CODE MODAL -->
    <div class="modal-backdrop" id="qrModal" style="display:none; align-items:center; justify-content:center;">
        <div class="modal" style="max-width:340px; text-align:center;">
            <div class="modal-header">
                <h3 id="qrModalTitle">کد QR</h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('qrModal').style.display='none'">✕</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div id="qrcode-container" style="display:flex; justify-content:center; margin-bottom:12px;"></div>
                <div style="font-size:0.8rem; color:#6b7280; word-break:break-all;" id="qrModalUrl"></div>
            </div>
        </div>
    </div>

    <script>
    function openQrModal(url, title) {
        document.getElementById('qrModalTitle').innerText = title || 'کد QR';
        document.getElementById('qrModalUrl').innerText = url;
        const container = document.getElementById('qrcode-container');
        container.innerHTML = '';
        if (typeof QRCode !== 'undefined') {
            new QRCode(container, {
                text: url,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
        document.getElementById('qrModal').style.display = 'flex';
    }
    </script>
    <?php
    layout_end();
});

// -------------------------------------------------------------
// 2. PUBLIC SINGLE PRODUCT PAGE: /s/{shop}/p/{product}
// -------------------------------------------------------------
route('GET', '/s/([^/]+)/p/([^/]+)', [], function ($shopSlug, $prodSlug) use ($pdo) {
    $user = current_user();
    $shop = resolve_shop($pdo, $shopSlug);

    if (!$shop) {
        error_page(404, 'فروشگاه یافت نشد', 'فروشگاه مورد نظر وجود ندارد.');
    }

    $shopId = (int)$shop['id'];
    $prod = resolve_product($pdo, $shopId, $prodSlug);

    if (!$prod) {
        error_page(404, 'کالا یافت نشد', 'کالای مورد نظر در این فروشگاه یافت نشد یا حذف شده است.');
    }

    $prodId = (int)$prod['id'];
    $canManage = can_manage_shop($user, $shopId);

    // Category name
    $catName = '';
    if (!empty($prod['category_id'])) {
        $cStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $cStmt->execute([$prod['category_id']]);
        $catName = (string)$cStmt->fetchColumn();
    }

    $isAvailable = (float)$prod['stock_quantity'] > 0;
    $activeCards = get_shop_active_cards($shopId);

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'befroosh.local');
    $productPageUrl = $baseUrl . "/s/" . urlencode($shop['slug']) . "/p/" . urlencode($prod['slug'] ?: (string)$prod['id']);
    $directBuyUrl = $baseUrl . "/buy/" . $prodId;

    // SEO / AEO Schema.org JSON-LD
    $seoTitle = e($prod['title']) . " | خرید از فروشگاه " . e($shop['name']);
    $seoDesc = mb_substr(strip_tags($prod['description'] ?: "خرید آنلاین {$prod['title']} با قیمت " . format_irr((float)$prod['price']) . " از فروشگاه {$shop['name']}"), 0, 160);

    $jsonLd = [
        "@context" => "https://schema.org",
        "@graph" => [
            [
                "@type" => "Product",
                "@id" => $productPageUrl . "#product",
                "name" => $prod['title'],
                "description" => $seoDesc,
                "sku" => $prod['sku'] ?: ("SKU-" . $prodId),
                "image" => !empty($prod['image_path']) ? ($baseUrl . "/storage/" . $prod['image_path']) : "",
                "offers" => [
                    "@type" => "Offer",
                    "price" => (float)$prod['price'],
                    "priceCurrency" => "IRR",
                    "availability" => $isAvailable ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                    "url" => $productPageUrl,
                    "seller" => [
                        "@type" => "Store",
                        "name" => $shop['name']
                    ]
                ]
            ],
            [
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    ["@type" => "ListItem", "position" => 1, "name" => "فروشگاه‌ها", "item" => "/shops"],
                    ["@type" => "ListItem", "position" => 2, "name" => $shop['name'], "item" => $baseUrl . "/s/" . urlencode($shop['slug'])],
                    ["@type" => "ListItem", "position" => 3, "name" => $prod['title'], "item" => $productPageUrl]
                ]
            ]
        ]
    ];

    layout_start($prod['title'], $user ?: ['role' => 'customer', 'nickname' => 'کاربر مهمان']);
    ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <!-- FLOATING QUICK EDIT BUTTON FOR SHOP OWNER / MANAGER -->
    <?php if ($canManage): ?>
        <div style="margin-bottom:14px; background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:0.85rem; color:#1e40af; font-weight:700;">
                شما به عنوان مدیر این فروشگاه وارد شده‌اید. می‌توانید این محصول را مستقیماً ویرایش نمایید.
            </div>
            <a class="btn btn-primary btn-sm" href="/products/<?= $prodId ?>/edit">
                <?= icon('edit', 14) ?> ویرایش سریع کالا
            </a>
        </div>
    <?php endif; ?>

    <!-- PRODUCT DETAIL CONTAINER -->
    <article class="card">
        <div class="card-body" style="padding:24px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:28px;">
                <!-- Product Gallery -->
                <div>
                    <div style="width:100%; height:320px; background:#f9fafb; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <?php if (!empty($prod['image_path']) && file_exists(STORAGE_PATH . '/' . $prod['image_path'])): ?>
                            <img src="/storage/<?= e($prod['image_path']) ?>" alt="<?= e($prod['title']) ?>" style="width:100%; height:100%; object-fit:contain;">
                        <?php else: ?>
                            <span style="color:#9ca3af; font-size:1rem;">تصویر کالا ثبت نشده است</span>
                        <?php endif; ?>
                    </div>

                    <!-- QR Buttons Cluster -->
                    <div style="display:flex; gap:8px; margin-top:12px;">
                        <button type="button" class="btn btn-outline btn-sm" style="flex:1;" onclick="openQrModal('<?= e($productPageUrl) ?>', 'QR کد صفحه محصول')">
                            QR کد این کالا
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" style="flex:1;" onclick="openQrModal('<?= e($directBuyUrl) ?>', 'QR خرید سریع این کالا')">
                            QR خرید مستقیم
                        </button>
                    </div>
                </div>

                <!-- Product Attributes & Purchase Box -->
                <div>
                    <div style="font-size:0.8rem; color:#6b7280; margin-bottom:6px;">
                        فروشگاه: <a href="/s/<?= urlencode($shop['slug']) ?>" style="color:#1d4ed8; text-decoration:none; font-weight:700;"><?= e($shop['name']) ?></a>
                        <?php if ($catName): ?> | دسته‌بندی: <strong><?= e($catName) ?></strong><?php endif; ?>
                    </div>

                    <h1 style="font-size:1.45rem; font-weight:800; line-height:1.4; margin-bottom:12px;">
                        <?= e($prod['title']) ?>
                    </h1>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:16px;">
                        <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;">قیمت فروش:</div>
                        <div style="font-size:1.6rem; font-weight:900; color:#1e3a8a;">
                            <?= format_irr((float)$prod['price']) ?>
                        </div>
                        <?php if (!empty($prod['tax_rate']) && (float)$prod['tax_rate'] > 0): ?>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">
                                شامل <?= en_to_fa_digits((string)($prod['tax_rate'] * 100)) ?>٪ مالیات بر ارزش افزوده
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Purchase Actions -->
                    <?php if ($isAvailable): ?>
                        <form method="post" action="/cart/add" style="margin-bottom:16px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= $prodId ?>">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                                <label style="font-weight:700; font-size:0.85rem;">تعداد:</label>
                                <input type="number" name="quantity" value="1" min="1" <?= (!empty($prod['max_per_order']) && $prod['max_per_order'] > 0) ? 'max="' . (int)$prod['max_per_order'] . '"' : '' ?> class="input" style="width:80px;" required>
                                <span style="font-size:0.8rem; color:#6b7280;"><?= e($prod['unit'] ?: 'عدد') ?></span>
                            </div>

                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a class="btn btn-primary" href="/buy/<?= $prodId ?>" style="flex:1;">
                                    <?= icon('check', 14) ?> خرید مستقیم
                                </a>
                                <button type="submit" class="btn btn-outline" style="flex:1;">
                                    <?= icon('plus', 14) ?> افزودن به سبد خرید
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error" style="margin-bottom:16px;">
                            این کالا در حال حاضر در انبار موجود نمی‌باشد.
                        </div>
                    <?php endif; ?>

                    <!-- Specs List (AEO Optimized) -->
                    <div style="border-top:1px solid #f1f5f9; padding-top:14px;">
                        <h3 style="font-size:0.9rem; font-weight:700; margin-bottom:8px;">مشخصات و محدودیت‌های خرید:</h3>
                        <dl style="display:grid; grid-template-columns:140px 1fr; gap:6px; font-size:0.82rem; margin:0;">
                            <dt style="color:#64748b;">وضعیت موجودی:</dt>
                            <dd><?= $isAvailable ? '<span class="badge badge-emerald">موجود در انبار</span>' : '<span class="badge badge-rose">ناموجود</span>' ?></dd>

                            <dt style="color:#64748b;">شناسه کالا (SKU):</dt>
                            <dd><code><?= e($prod['sku'] ?: '—') ?></code></dd>

                            <dt style="color:#64748b;">کد بارکد:</dt>
                            <dd><code><?= e($prod['barcode'] ?: '—') ?></code></dd>

                            <dt style="color:#64748b;">وزن تقریبی:</dt>
                            <dd><?= en_to_fa_digits((string)$prod['weight_grams']) ?> گرم</dd>

                            <?php if (!empty($prod['max_per_order']) && $prod['max_per_order'] > 0): ?>
                                <dt style="color:#64748b;">سقف در هر سفارش:</dt>
                                <dd><strong><?= en_to_fa_digits((string)$prod['max_per_order']) ?> <?= e($prod['unit'] ?: 'عدد') ?></strong></dd>
                            <?php endif; ?>

                            <?php if (!empty($prod['max_per_month']) && $prod['max_per_month'] > 0): ?>
                                <dt style="color:#64748b;">سقف خرید ماهانه:</dt>
                                <dd><strong><?= en_to_fa_digits((string)$prod['max_per_month']) ?> <?= e($prod['unit'] ?: 'عدد') ?> در ماه</strong></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Full Description (Semantic Section) -->
            <?php if (!empty($prod['description'])): ?>
                <section style="margin-top:28px; border-top:1px solid #e5e7eb; padding-top:20px;">
                    <h2 style="font-size:1.1rem; font-weight:800; margin-bottom:10px;">معرفی و توضیحات کالا</h2>
                    <div style="font-size:0.9rem; line-height:1.8; color:#374151;">
                        <?= nl2br(e($prod['description'])) ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </article>

    <!-- QR CODE MODAL -->
    <div class="modal-backdrop" id="qrModal" style="display:none; align-items:center; justify-content:center;">
        <div class="modal" style="max-width:340px; text-align:center;">
            <div class="modal-header">
                <h3 id="qrModalTitle">کد QR</h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('qrModal').style.display='none'">✕</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div id="qrcode-container" style="display:flex; justify-content:center; margin-bottom:12px;"></div>
                <div style="font-size:0.8rem; color:#6b7280; word-break:break-all;" id="qrModalUrl"></div>
            </div>
        </div>
    </div>

    <script>
    function openQrModal(url, title) {
        document.getElementById('qrModalTitle').innerText = title || 'کد QR';
        document.getElementById('qrModalUrl').innerText = url;
        const container = document.getElementById('qrcode-container');
        container.innerHTML = '';
        if (typeof QRCode !== 'undefined') {
            new QRCode(container, {
                text: url,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
        document.getElementById('qrModal').style.display = 'flex';
    }
    </script>
    <?php
    layout_end();
});
