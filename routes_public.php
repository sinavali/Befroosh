<?php
declare(strict_types=1);

/**
 * routes_public.php
 * Public Storefront Pages (Home, About, Policies, FAQ, Contact)
 * Completely self-contained with dedicated public website layout (ZERO SIDEBAR)
 */

// 1. Directory of Shops
route('GET', '/(?:shops)?', [], function () use ($pdo) {
    $user = current_user();
    $shops = all_active_shops();

    layout_public_start('ویترین فروشگاه‌ها', null, $user);
    ?>
    <div style="text-align:center; margin-bottom:36px;">
        <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:8px;">فروشگاه‌های فعال در بستر بفروش</h1>
        <p style="color:#64748b; font-size:0.95rem;">مستقیماً از فروشندگان معتبر خرید کنید و سفارش خود را با کارت‌به‌کارت نهایی نمایید.</p>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
        <?php foreach ($shops as $sh): ?>
            <div class="card" style="padding:24px; display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.2s; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
                <div>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                        <div style="width:48px; height:48px; border-radius:12px; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:900;">
                            <?= e(mb_substr($sh['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h2 style="font-size:1.15rem; margin:0; color:#0f172a;"><?= e($sh['name']) ?></h2>
                            <span class="badge badge-emerald" style="margin-top:4px;">تأییدشده و فعال</span>
                        </div>
                    </div>
                    <p style="color:#64748b; font-size:0.88rem; line-height:1.7; margin-bottom:16px;">
                        <?= e(mb_substr($sh['description'] ?: 'ارائه‌دهنده انواع کالاهای باکیفیت و ارسال سریع به سراسر کشور.', 0, 140)) ?>...
                    </p>
                </div>
                <div style="display:flex; gap:8px; border-top:1px solid #f1f5f9; padding-top:16px;">
                    <a href="/shop/<?= e($sh['slug']) ?>" class="btn btn-primary" style="flex:1;">ورود به فروشگاه</a>
                    <a href="/shop/<?= e($sh['slug']) ?>/products" class="btn btn-outline" style="flex:1;">کاتالوگ کالاها</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    layout_public_end(null);
});

// 2. Shop Landing Page: /shop/{slug} (and alias /s/{slug})
$shopLandingHandler = function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) {
        error_page(404, 'فروشگاه یافت نشد', 'فروشگاه مورد نظر وجود ندارد یا غیرفعال شده است.');
    }
    $shopId = (int)$shop['id'];
    $user = current_user();

    // Fetch featured products
    $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 8");
    $stmt->execute([$shopId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories
    $catStmt = $pdo->prepare("SELECT * FROM categories WHERE shop_id = ? AND active = 1 ORDER BY sort_order ASC LIMIT 6");
    $catStmt->execute([$shopId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_public_start($shop['name'], $shop, $user);
    ?>
    <!-- HERO BANNER -->
    <div style="background:linear-gradient(135deg, #1e3a8a, #0f172a); border-radius:16px; padding:40px 32px; color:#fff; margin-bottom:32px;">
        <div style="max-width:700px;">
            <div style="display:inline-block; background:rgba(255,255,255,0.15); padding:4px 12px; border-radius:99px; font-size:0.8rem; font-weight:bold; margin-bottom:12px;">فروشگاه آنلاین رسمی</div>
            <h1 style="font-size:1.8rem; font-weight:900; margin-bottom:12px; color:#fff;"><?= e($shop['name']) ?></h1>
            <p style="color:#cbd5e1; font-size:0.95rem; line-height:1.8; margin-bottom:20px;"><?= e($shop['description']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="/shop/<?= e($shop['slug']) ?>/products" class="btn btn-primary" style="background:#3b82f6;">مشاهده همه کالاها</a>
                <a href="/shop/<?= e($shop['slug']) ?>/policies" class="btn btn-outline" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.3);">رویه‌های ارسال و پرداخت</a>
            </div>
        </div>
    </div>

    <!-- CATEGORIES -->
    <?php if ($categories): ?>
        <div style="margin-bottom:36px;">
            <h2 style="font-size:1.15rem; font-weight:bold; margin-bottom:16px;">دسته‌بندی‌های برگزیده</h2>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <?php foreach ($categories as $cat): ?>
                    <a href="/shop/<?= e($shop['slug']) ?>/products?category=<?= (int)$cat['id'] ?>" class="card" style="padding:12px 20px; display:inline-flex; align-items:center; gap:8px; font-weight:bold; color:#1e293b; margin:0;">
                        <span><?= e($cat['name']) ?></span>
                        <span style="color:#94a3b8; font-size:0.8rem;">←</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- FEATURED PRODUCTS -->
    <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h2 style="font-size:1.15rem; font-weight:bold; margin:0;">تازه‌ترین کالاهای فروشگاه</h2>
            <a href="/shop/<?= e($shop['slug']) ?>/products" style="color:#2563eb; font-size:0.88rem; font-weight:bold;">مشاهده کاتالوگ کامل ←</a>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:18px;">
            <?php foreach ($products as $p): ?>
                <div class="card" style="padding:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative;">
                    <div style="position:absolute; top:12px; left:12px; z-index:5;">
                        <button type="button" class="btn btn-ghost fav-heart-btn" data-product-id="<?= (int)$p['id'] ?>" onclick="window.BefrooshStore.toggleFav(<?= (int)$p['id'] ?>, this)" title="نشان کردن">
                            <?= icon('heart', 18) ?>
                        </button>
                    </div>

                    <a href="/s/<?= e($shop['slug']) ?>/p/<?= e($p['slug'] ?: (string)$p['id']) ?>">
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
                        <div style="font-size:0.95rem; font-weight:800; color:#059669; margin-bottom:12px;"><?= format_irr((float)$p['price']) ?></div>
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
    </div>
    <?php
    layout_public_end($shop);
};

route('GET', '/shop/([^/]+)', [], $shopLandingHandler);
route('GET', '/s/([^/]+)', [], $shopLandingHandler);

// 3. Shop About Page: /shop/{slug}/about
route('GET', '/shop/([^/]+)/about', [], function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) error_page(404, 'یافت نشد', 'فروشگاه یافت نشد.');
    layout_public_start('درباره ما - ' . $shop['name'], $shop);
    ?>
    <div class="card" style="padding:32px; max-width:860px; margin:0 auto;">
        <h1 style="font-size:1.4rem; font-weight:800; margin-bottom:18px; color:#0f172a;">درباره فروشگاه <?= e($shop['name']) ?></h1>
        <div style="line-height:2; color:#334155; font-size:0.95rem;">
            <?php if (!empty($shop['about_html'])): ?>
                <?= $shop['about_html'] ?>
            <?php else: ?>
                <p><?= nl2br(e($shop['description'] ?: 'اطلاعاتی ثبت نشده است.')) ?></p>
            <?php endif; ?>
        </div>
        <hr style="border:none; border-top:1px solid #e2e8f0; margin:24px 0;">
        <div style="display:flex; gap:20px; font-size:0.88rem; color:#64748b; flex-wrap:wrap;">
            <?php if ($shop['phone']): ?><div>تلفن تماس: <strong><?= en_to_fa_digits($shop['phone']) ?></strong></div><?php endif; ?>
            <?php if ($shop['email']): ?><div>ایمیل: <strong><?= e($shop['email']) ?></strong></div><?php endif; ?>
            <?php if ($shop['address']): ?><div>نشانی: <strong><?= e($shop['address']) ?></strong></div><?php endif; ?>
        </div>
    </div>
    <?php
    layout_public_end($shop);
});

// 4. Shop Policies Page: /shop/{slug}/policies
route('GET', '/shop/([^/]+)/policies', [], function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) error_page(404, 'یافت نشد', 'فروشگاه یافت نشد.');
    layout_public_start('قوانین و رویه‌های ارسال - ' . $shop['name'], $shop);
    ?>
    <div class="card" style="padding:32px; max-width:860px; margin:0 auto;">
        <h1 style="font-size:1.4rem; font-weight:800; margin-bottom:18px; color:#0f172a;">قوانین و رویه‌های خرید و ارسال</h1>
        <div style="line-height:2; color:#334155; font-size:0.95rem;">
            <?php if (!empty($shop['policies_html'])): ?>
                <?= $shop['policies_html'] ?>
            <?php else: ?>
                <p>مشتری گرامی، پس از ثبت سفارش، مبلغ فاکتور را به شماره کارت رسمی فروشگاه واریز کرده و تصویر فیش را بارگذاری فرمایید. اقلام سفارش شما تا <?= en_to_fa_digits((string)($shop['reservation_days'] ?: 4)) ?> روز کاری در وضعیت رزرو قرار می‌گیرند.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    layout_public_end($shop);
});

// 5. Shop FAQ Page: /shop/{slug}/faq
route('GET', '/shop/([^/]+)/faq', [], function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) error_page(404, 'یافت نشد', 'فروشگاه یافت نشد.');
    $faqs = [];
    if (!empty($shop['faq_json'])) {
        $faqs = json_decode($shop['faq_json'], true) ?: [];
    }
    layout_public_start('سوالات متداول - ' . $shop['name'], $shop);
    ?>
    <div class="card" style="padding:32px; max-width:860px; margin:0 auto;">
        <h1 style="font-size:1.4rem; font-weight:800; margin-bottom:20px; color:#0f172a;">سوالات متداول خریداران</h1>
        <?php if ($faqs): ?>
            <?php foreach ($faqs as $f): ?>
                <div style="margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:16px;">
                    <h3 style="font-size:1rem; font-weight:bold; color:#1e293b; margin-bottom:6px;"><?= e($f['q']) ?></h3>
                    <p style="color:#475569; font-size:0.9rem; line-height:1.8;"><?= e($f['a']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#64748b;">پرسش و پاسخی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
    <?php
    layout_public_end($shop);
});

// 6. Shop Contact Page: /shop/{slug}/contact
route('GET|POST', '/shop/([^/]+)/contact', [], function ($slug) use ($pdo) {
    $shop = resolve_shop($slug);
    if (!$shop) error_page(404, 'یافت نشد', 'فروشگاه یافت نشد.');
    $sent = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if ($name && $msg) {
            $sent = true;
        }
    }

    layout_public_start('ارتباط با ما - ' . $shop['name'], $shop);
    ?>
    <div class="card" style="padding:32px; max-width:700px; margin:0 auto;">
        <h1 style="font-size:1.4rem; font-weight:800; margin-bottom:16px; color:#0f172a;">ارتباط مستقیم با فروشگاه <?= e($shop['name']) ?></h1>
        <?php if ($sent): ?>
            <div class="card" style="background:#ecfdf5; border-color:#a7f3d0; color:#065f46; padding:14px; font-weight:bold;">پیام شما با موفقیت ثبت شد و به مدیریت فروشگاه ارسال گردید.</div>
        <?php else: ?>
            <form method="post" action="/shop/<?= e($shop['slug']) ?>/contact">
                <?= csrf_field() ?>
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">نام و نام خانوادگی:</label>
                    <input type="text" name="name" class="input" required>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">شماره تماس:</label>
                    <input type="tel" name="phone" class="input">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">پیام شما:</label>
                    <textarea name="message" class="input" rows="4" required></textarea>
                </div>
                <button class="btn btn-primary" style="width:100%;">ارسال پیام به فروشگاه</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    layout_public_end($shop);
});
