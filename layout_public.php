<?php
declare(strict_types=1);

/**
 * layout_public.php
 * Public Storefront Website Layout (ZERO SIDEBAR) for Shops & Products
 */

require_once __DIR__ . '/layout_head.php';
require_once __DIR__ . '/layout_icons.php';
require_once __DIR__ . '/layout_components.php';

function layout_public_start(string $title, ?array $shop = null, ?array $user = null): void
{
    $user = $user ?? current_user();
    $shopName = $shop['name'] ?? 'سامانه بفروش';
    $shopSlug = $shop['slug'] ?? 'central';
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isPathActive = function (string $p) use ($currentPath): bool {
        return $currentPath === $p;
    };
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <?php render_layout_head($title . ' - ' . $shopName); ?>
        <style>
            .store-header { background: #fff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
            .store-top-announcement { background: #1e293b; color: #f8fafc; font-size: 0.8rem; padding: 6px 16px; text-align: center; }
            .store-nav-container { max-width: 1280px; margin: 0 auto; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
            .store-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.2rem; color: #0f172a; }
            .store-brand-logo { width: 40px; height: 40px; border-radius: 10px; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 900; }
            .store-search-form { flex: 1; max-width: 480px; min-width: 240px; position: relative; }
            .store-search-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 99px; padding: 8px 38px 8px 16px; font-size: 0.88rem; outline: none; background: #f8fafc; }
            .store-search-input:focus { background: #fff; border-color: #2563eb; }
            .store-search-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
            .store-actions { display: flex; align-items: center; gap: 10px; }
            .store-action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 0.86rem; font-weight: bold; color: #334155; position: relative; }
            .store-action-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
            .store-badge { position: absolute; -top: 6px; -right: 6px; background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 900; border-radius: 99px; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }
            .store-menubar { background: #f8fafc; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #e2e8f0; }
            .store-menu-list { max-width: 1280px; margin: 0 auto; padding: 0 20px; display: flex; gap: 8px; list-style: none; overflow-x: auto; }
            .store-menu-item a { display: block; padding: 10px 14px; font-size: 0.88rem; font-weight: bold; color: #475569; border-bottom: 2px solid transparent; white-space: nowrap; }
            .store-menu-item a:hover { color: #2563eb; }
            .store-menu-item a.active { color: #2563eb; border-bottom-color: #2563eb; }
            .store-main-content { max-width: 1280px; margin: 0 auto; padding: 24px 20px; min-height: 70vh; }
            .store-footer { background: #0f172a; color: #94a3b8; font-size: 0.88rem; padding: 48px 20px 24px; margin-top: 60px; border-top: 1px solid #1e293b; }
            .store-footer-grid { max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 32px; margin-bottom: 36px; }
            .store-footer-col h4 { color: #f8fafc; font-size: 1rem; margin-bottom: 16px; font-weight: bold; }
            .store-footer-col ul { list-style: none; }
            .store-footer-col ul li { margin-bottom: 8px; }
            .store-footer-col ul li a:hover { color: #fff; }
            .store-footer-bottom { max-width: 1280px; margin: 0 auto; border-top: 1px solid #1e293b; padding-top: 20px; text-align: center; font-size: 0.8rem; color: #64748b; }
        </style>
    </head>
    <body>
        <div class="store-top-announcement">
            <span>ارسال سراسری با پست پیشتاز | پرداخت امن کارت‌به‌کارت | ضمانت رزرو کالا تا ۴ روز</span>
        </div>

        <header class="store-header">
            <div class="store-nav-container">
                <a href="/shop/<?= e($shopSlug) ?>" class="store-brand">
                    <div class="store-brand-logo"><?= e(mb_substr($shopName, 0, 1)) ?></div>
                    <div>
                        <div style="line-height:1.2;"><?= e($shopName) ?></div>
                        <span style="font-size:0.72rem; color:#64748b; font-weight:normal;">فروشگاه آنلاین رسمی</span>
                    </div>
                </a>

                <form action="/shop/<?= e($shopSlug) ?>/products" method="get" class="store-search-form">
                    <span class="store-search-icon"><?= icon('search', 16) ?></span>
                    <input type="text" name="q" class="store-search-input" placeholder="جستجو در محصولات این فروشگاه..." value="<?= e($_GET['q'] ?? '') ?>">
                </form>

                <div class="store-actions">
                    <a href="/bookmarks" class="store-action-btn" title="علاقه‌مندی‌ها">
                        <?= icon('heart', 16) ?>
                        <span style="display:none;" id="store-fav-badge" class="store-badge">۰</span>
                    </a>

                    <a href="/cart" class="store-action-btn" title="سبد خرید">
                        <?= icon('cart', 16) ?>
                        <span style="display:none;" id="store-cart-badge" class="store-badge">۰</span>
                        <span>سبد خرید</span>
                    </a>

                    <?php if ($user && !empty($user['id'])): ?>
                        <a href="/dashboard" class="btn btn-primary btn-sm">
                            <?= icon('user', 14) ?> <?= e($user['nickname']) ?>
                        </a>
                    <?php else: ?>
                        <a href="/login" class="btn btn-primary btn-sm">ورود / ثبت‌نام</a>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="store-menubar">
                <ul class="store-menu-list">
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>" class="<?= $isPathActive('/shop/' . $shopSlug) ? 'active' : '' ?>">صفحه اصلی</a></li>
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/products" class="<?= $isPathActive('/shop/' . $shopSlug . '/products') ? 'active' : '' ?>">همه محصولات</a></li>
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/about" class="<?= $isPathActive('/shop/' . $shopSlug . '/about') ? 'active' : '' ?>">درباره فروشگاه</a></li>
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/policies" class="<?= $isPathActive('/shop/' . $shopSlug . '/policies') ? 'active' : '' ?>">قوانین و رویه ارسال</a></li>
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/faq" class="<?= $isPathActive('/shop/' . $shopSlug . '/faq') ? 'active' : '' ?>">سوالات متداول</a></li>
                    <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/contact" class="<?= $isPathActive('/shop/' . $shopSlug . '/contact') ? 'active' : '' ?>">تماس با ما</a></li>
                </ul>
            </nav>
        </header>

        <main class="store-main-content">
    <?php
}

function layout_public_end(?array $shop = null): void
{
    $shopName = $shop['name'] ?? 'فروشگاه';
    $shopSlug = $shop['slug'] ?? 'central';
    ?>
        </main>

        <footer class="store-footer">
            <div class="store-footer-grid">
                <div class="store-footer-col">
                    <h4>درباره <?= e($shopName) ?></h4>
                    <p style="line-height:1.8; color:#94a3b8; font-size:0.85rem;">
                        <?= e(mb_substr($shop['description'] ?? 'عرضه‌کننده مستقیم باکیفیت‌ترین محصولات با امکان ثبت سفارش آسان و ارسال به سراسر کشور.', 0, 180)) ?>
                    </p>
                    <div style="margin-top:12px; font-size:0.85rem; color:#cbd5e1;">
                        <?php if (!empty($shop['phone'])): ?><div>تلفن تماس: <span dir="ltr"><?= e($shop['phone']) ?></span></div><?php endif; ?>
                        <?php if (!empty($shop['address'])): ?><div>نشانی: <?= e($shop['address']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="store-footer-col">
                    <h4>دسترسی سریع</h4>
                    <ul>
                        <li><a href="/shop/<?= e($shopSlug) ?>">صفحه اصلی فروشگاه</a></li>
                        <li><a href="/shop/<?= e($shopSlug) ?>/products">کاتالوگ کالاها</a></li>
                        <li><a href="/shop/<?= e($shopSlug) ?>/policies">رویه‌های ارسال و مرجوعی</a></li>
                        <li><a href="/shop/<?= e($shopSlug) ?>/faq">راهنمای خرید و پرداخت</a></li>
                    </ul>
                </div>

                <div class="store-footer-col">
                    <h4>راهنمای پرداخت و پیگیری</h4>
                    <p style="font-size:0.84rem; line-height:1.7;">
                        پرداخت در این فروشگاه از طریق واریز مستقیم کارت‌به‌کارت انجام می‌پذیرد. پس از ثبت نهایی، فیش واریز را بارگذاری نموده و وضعیت سفارش را با کد رهگیری پستی پیگیری فرمایید.
                    </p>
                    <div style="margin-top:14px;">
                        <a href="/orders/track" class="btn btn-outline btn-sm" style="background:transparent; color:#fff; border-color:#475569;">
                            <?= icon('search', 13) ?> پیگیری سریع سفارش
                        </a>
                    </div>
                </div>
            </div>

            <div class="store-footer-bottom">
                کلیه حقوق این فروشگاه محفوظ است. قدرت گرفته از سامانه چندفروشگاهی بَفروش
            </div>
        </footer>
    </body>
    </html>
    <?php
}
