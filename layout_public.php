<?php
declare(strict_types=1);

/**
 * layout_public.php
 * Public Storefront Website Layout (ZERO SIDEBAR) for Shops & Products
 * Includes Slide-over Mini-Cart Drawer and Customer Navigation
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
            .store-action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 0.86rem; font-weight: bold; color: #334155; position: relative; cursor: pointer; }
            .store-action-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
            .store-badge { position: absolute; top: -6px; right: -6px; background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 900; border-radius: 99px; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }
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
            /* Mini-cart drawer */
            .mini-cart-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; opacity: 0; visibility: hidden; transition: all 0.25s ease-in-out; }
            .mini-cart-backdrop.open { opacity: 1; visibility: visible; }
            .mini-cart-drawer { position: fixed; top: 0; bottom: 0; left: 0; width: 100%; max-width: 380px; background: #fff; z-index: 9999; box-shadow: -4px 0 25px rgba(0,0,0,0.15); transform: translateX(-100%); transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
            .mini-cart-drawer.open { transform: translateX(0); }
            .mini-cart-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
            .mini-cart-items { flex: 1; overflow-y: auto; padding: 16px 20px; }
            .mini-cart-item { display: flex; gap: 12px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; align-items: center; }
            .mini-cart-footer { padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        </style>
    </head>
    <body>
        <div class="store-top-announcement">
            <span>ارسال سراسری با پست پیشتاز | پرداخت امن کارت‌به‌کارت | ضمانت رزرو کالا تا ۴ روز</span>
        </div>

        <header class="store-header">
            <div class="store-nav-container">
                <a href="<?= $shop ? '/shop/' . e($shopSlug) : '/' ?>" class="store-brand">
                    <div class="store-brand-logo"><?= e(mb_substr($shopName, 0, 1)) ?></div>
                    <div>
                        <div style="line-height:1.2;"><?= e($shopName) ?></div>
                        <span style="font-size:0.72rem; color:#64748b; font-weight:normal;"><?= $shop ? 'فروشگاه آنلاین رسمی' : 'پلتفرم هوشمند فروش آنلاین و شعب' ?></span>
                    </div>
                </a>

                <form action="<?= $shop ? '/shop/' . e($shopSlug) . '/products' : '/shops' ?>" method="get" class="store-search-form">
                    <span class="store-search-icon"><?= icon('search', 16) ?></span>
                    <input type="text" name="q" class="store-search-input" placeholder="<?= $shop ? 'جستجو در محصولات این فروشگاه...' : 'جستجو در میان فروشگاه‌ها...' ?>" value="<?= e($_GET['q'] ?? '') ?>">
                </form>

                <div class="store-actions">
                    <a href="/favorites" class="store-action-btn" title="علاقه‌مندی‌ها">
                        <?= icon('heart', 16) ?>
                        <span style="display:none;" id="store-fav-badge" class="store-badge">۰</span>
                    </a>

                    <button type="button" class="store-action-btn" id="miniCartBtn" onclick="window.openMiniCart()" title="سبد خرید">
                        <?= icon('cart', 16) ?>
                        <span style="display:none;" id="store-cart-badge" class="store-badge">۰</span>
                        <span>سبد خرید</span>
                    </button>

                    <?php if ($user && !empty($user['id'])): ?>
                        <?php if ($user['role'] === 'customer'): ?>
                            <a href="/profile/orders" class="btn btn-outline btn-sm"><?= icon('orders', 14) ?> سفارش‌های من</a>
                            <a href="/profile" class="btn btn-primary btn-sm"><?= icon('user', 14) ?> <?= e($user['nickname']) ?></a>
                        <?php else: ?>
                            <a href="/dashboard" class="btn btn-primary btn-sm"><?= icon('dashboard', 14) ?> پنل مدیریت</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/login" class="btn btn-primary btn-sm">ورود / ثبت‌نام</a>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="store-menubar">
                <ul class="store-menu-list">
                    <?php if ($shop): ?>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>" class="<?= $isPathActive('/shop/' . $shopSlug) ? 'active' : '' ?>">صفحه اصلی</a></li>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/products" class="<?= $isPathActive('/shop/' . $shopSlug . '/products') ? 'active' : '' ?>">همه محصولات</a></li>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/about" class="<?= $isPathActive('/shop/' . $shopSlug . '/about') ? 'active' : '' ?>">درباره فروشگاه</a></li>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/policies" class="<?= $isPathActive('/shop/' . $shopSlug . '/policies') ? 'active' : '' ?>">قوانین و رویه ارسال</a></li>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/faq" class="<?= $isPathActive('/shop/' . $shopSlug . '/faq') ? 'active' : '' ?>">سوالات متداول</a></li>
                        <li class="store-menu-item"><a href="/shop/<?= e($shopSlug) ?>/contact" class="<?= $isPathActive('/shop/' . $shopSlug . '/contact') ? 'active' : '' ?>">تماس با ما</a></li>
                    <?php else: ?>
                        <li class="store-menu-item"><a href="/" class="<?= $isPathActive('/') ? 'active' : '' ?>">صفحه اصلی</a></li>
                        <li class="store-menu-item"><a href="/shops" class="<?= $isPathActive('/shops') ? 'active' : '' ?>">ویترین فروشگاه‌ها</a></li>
                        <li class="store-menu-item"><a href="/policies" class="<?= $isPathActive('/policies') ? 'active' : '' ?>">قوانین و رویه‌ها</a></li>
                        <li class="store-menu-item"><a href="/register" class="<?= $isPathActive('/register') ? 'active' : '' ?>">ثبت‌نام فروشندگان</a></li>
                    <?php endif; ?>
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

        <!-- MINI-CART DRAWER -->
        <div id="miniCartBackdrop" class="mini-cart-backdrop" onclick="window.closeMiniCart()"></div>
        <div id="miniCartDrawer" class="mini-cart-drawer">
            <div class="mini-cart-header">
                <h3 style="margin:0; font-size:1rem; font-weight:800; display:flex; align-items:center; gap:8px;">
                    <?= icon('cart', 18) ?> سبد خرید شما
                </h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="window.closeMiniCart()" style="font-size:1.1rem; padding:4px 8px;">✕</button>
            </div>
            <div id="miniCartItems" class="mini-cart-items">
                <!-- Injected via JavaScript -->
            </div>
            <div class="mini-cart-footer">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; font-weight:bold;">
                    <span>مبلغ کل کالاها:</span>
                    <span id="miniCartTotal" style="color:#059669; font-size:1.05rem;">۰ ریال</span>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="/cart" class="btn btn-primary" style="width:100%; justify-content:center;">تکمیل سفارش و پرداخت</a>
                </div>
            </div>
        </div>

        <script>
        window.openMiniCart = function() {
            window.renderMiniCart();
            document.getElementById('miniCartBackdrop').classList.add('open');
            document.getElementById('miniCartDrawer').classList.add('open');
        };
        window.closeMiniCart = function() {
            document.getElementById('miniCartBackdrop').classList.remove('open');
            document.getElementById('miniCartDrawer').classList.remove('open');
        };
        window.renderMiniCart = function() {
            var items = window.BefrooshStore ? window.BefrooshStore.getCart() : [];
            var box = document.getElementById('miniCartItems');
            var tot = document.getElementById('miniCartTotal');
            if (!box) return;
            if (items.length === 0) {
                box.innerHTML = '<div style="padding:30px 10px; text-align:center; color:#94a3b8;"><p>سبد خرید شما خالی است.</p></div>';
                if (tot) tot.textContent = '۰ ریال';
                return;
            }
            var sum = 0;
            var html = '';
            items.forEach(function(it, idx) {
                var line = (it.price || 0) * (it.qty || 1);
                sum += line;
                html += '<div class="mini-cart-item">' +
                    '<div style="flex:1; min-width:0;">' +
                        '<div style="font-size:0.86rem; font-weight:bold; color:#1e293b; margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + (it.title || 'کالا') + '</div>' +
                        '<div style="font-size:0.8rem; color:#059669; font-weight:700;">' + Number(it.price || 0).toLocaleString('fa-IR') + ' ریال</div>' +
                        '<div style="display:flex; align-items:center; gap:8px; margin-top:6px;">' +
                            '<button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="window.updateMiniCartQty(' + idx + ', -1)">-</button>' +
                            '<span style="font-size:0.85rem; font-weight:bold;">' + Number(it.qty || 1).toLocaleString('fa-IR') + '</span>' +
                            '<button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="window.updateMiniCartQty(' + idx + ', 1)">+</button>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444; padding:4px;" onclick="window.removeMiniCartItem(' + idx + ')">✕</button>' +
                '</div>';
            });
            box.innerHTML = html;
            if (tot) tot.textContent = Number(sum).toLocaleString('fa-IR') + ' ریال';
        };
        window.updateMiniCartQty = function(idx, delta) {
            var items = window.BefrooshStore.getCart();
            if (!items[idx]) return;
            items[idx].qty = (items[idx].qty || 1) + delta;
            if (items[idx].qty <= 0) { items.splice(idx, 1); }
            window.BefrooshStore.setCart(items);
        };
        window.removeMiniCartItem = function(idx) {
            var items = window.BefrooshStore.getCart();
            items.splice(idx, 1);
            window.BefrooshStore.setCart(items);
        };
        </script>

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
