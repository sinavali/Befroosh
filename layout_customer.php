<?php
declare(strict_types=1);

/**
 * layout_customer.php
 * Dedicated Customer Experience Layout (ZERO ADMIN SIDEBAR)
 * Clean Apple HIG Light Mode, Segmented Sub-Nav Tabs, Persian RTL
 */

require_once __DIR__ . '/layout_head.php';
require_once __DIR__ . '/layout_icons.php';
require_once __DIR__ . '/layout_components.php';

function layout_customer_start(string $title, string $activeTab = 'overview', ?array $user = null): void
{
    $user = $user ?? current_user();
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <?php render_layout_head($title . ' | حساب کاربری'); ?>
        <style>
            .customer-header {
                background: #ffffff;
                border-bottom: 1px solid var(--border);
                position: sticky;
                top: 0;
                z-index: 50;
            }
            .customer-nav-wrap {
                max-width: 1200px;
                margin: 0 auto;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
            }
            .customer-brand {
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 700;
                font-size: 1.15rem;
                color: var(--ink);
                text-decoration: none;
            }
            .customer-brand-icon {
                width: 36px;
                height: 36px;
                border-radius: 9px;
                background: var(--primary);
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.1rem;
                font-weight: 800;
            }
            .customer-header-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .customer-subnav-bar {
                background: var(--surface-muted);
                border-bottom: 1px solid var(--border);
            }
            .customer-subnav-list {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
                display: flex;
                gap: 6px;
                list-style: none;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .customer-subnav-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 12px 14px;
                font-size: 0.86rem;
                font-weight: 600;
                color: var(--muted);
                border-bottom: 2px solid transparent;
                white-space: nowrap;
                transition: color 0.15s ease, border-color 0.15s ease;
            }
            .customer-subnav-link:hover {
                color: var(--ink);
            }
            .customer-subnav-link.active {
                color: var(--primary);
                border-bottom-color: var(--primary);
                font-weight: 700;
            }
            .customer-main-wrap {
                max-width: 1200px;
                margin: 0 auto;
                padding: 24px 20px 48px;
                min-height: 75vh;
            }
            /* Mini-cart drawer */
            .mini-cart-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: all 0.25s ease-in-out; }
            .mini-cart-backdrop.open { opacity: 1; visibility: visible; }
            .mini-cart-drawer { position: fixed; top: 0; bottom: 0; left: 0; width: 100%; max-width: 380px; background: #fff; z-index: 9999; box-shadow: 4px 0 25px rgba(0, 0, 0, 0.1); transform: translateX(-100%); transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
            .mini-cart-drawer.open { transform: translateX(0); }
            .mini-cart-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
            .mini-cart-items { flex: 1; overflow-y: auto; padding: 16px 20px; }
            .mini-cart-item { display: flex; gap: 12px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--border); align-items: center; }
            .mini-cart-footer { padding: 16px 20px; border-top: 1px solid var(--border); background: var(--surface-muted); }
            .store-badge { position: absolute; top: -6px; right: -6px; background: var(--crimson); color: #fff; font-size: 0.7rem; font-weight: 900; border-radius: 99px; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }
            .customer-footer {
                background: #ffffff;
                border-top: 1px solid var(--border);
                padding: 24px 20px;
                text-align: center;
                font-size: 0.82rem;
                color: var(--muted);
            }
        </style>
    </head>
    <body>
        <header class="customer-header">
            <div class="customer-nav-wrap">
                <a href="/" class="customer-brand">
                    <div class="customer-brand-icon">ب</div>
                    <div>
                        <div style="line-height:1.2;">سامانه بفروش</div>
                        <div style="font-size:0.72rem; color:var(--muted); font-weight:normal;">پورتال اختصاصی خریداران</div>
                    </div>
                </a>

                <div class="customer-header-actions">
                    <a href="/shops" class="btn btn-outline btn-sm">
                        <?= icon('store', 14) ?> مرور فروشگاه‌ها
                    </a>

                    <a href="/favorites" class="btn btn-outline btn-sm" title="کالاهای نشان‌شده" style="position:relative;">
                        <?= icon('heart', 14) ?>
                        <span style="display:none;" id="store-fav-badge" class="store-badge">۰</span>
                    </a>

                    <button type="button" class="btn btn-outline btn-sm" id="miniCartBtn" onclick="window.openMiniCart()" title="سبد خرید" style="position:relative;">
                        <?= icon('cart', 14) ?>
                        <span style="display:none;" id="store-cart-badge" class="store-badge">۰</span>
                        <span>سبد خرید</span>
                    </button>

                    <div style="display:inline-flex; align-items:center; gap:8px;">
                        <span style="font-size:0.84rem; font-weight:600; color:var(--ink);">
                            <?= e($user['nickname'] ?? 'مشتری گرامی') ?>
                        </span>
                        <form method="post" action="/logout" class="inline-form" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm" title="خروج از حساب">خروج</button>
                        </form>
                    </div>
                </div>
            </div>

            <nav class="customer-subnav-bar">
                <ul class="customer-subnav-list">
                    <li>
                        <a href="/my" class="customer-subnav-link <?= $activeTab === 'overview' ? 'active' : '' ?>">
                            <?= icon('dashboard', 15) ?> خلاصه حساب
                        </a>
                    </li>
                    <li>
                        <a href="/orders" class="customer-subnav-link <?= $activeTab === 'orders' ? 'active' : '' ?>">
                            <?= icon('orders', 15) ?> سفارش‌های من
                        </a>
                    </li>
                    <li>
                        <a href="/orders/track" class="customer-subnav-link <?= $activeTab === 'track' ? 'active' : '' ?>">
                            <?= icon('search', 15) ?> پیگیری مرسوله
                        </a>
                    </li>
                    <li>
                        <a href="/tickets" class="customer-subnav-link <?= $activeTab === 'tickets' ? 'active' : '' ?>">
                            <?= icon('tickets', 15) ?> پشتیبانی و تیکت‌ها
                        </a>
                    </li>
                    <li>
                        <a href="/addresses" class="customer-subnav-link <?= $activeTab === 'addresses' ? 'active' : '' ?>">
                            <?= icon('location', 15) ?> آدرس‌های تحویل
                        </a>
                    </li>
                    <li>
                        <a href="/profile" class="customer-subnav-link <?= $activeTab === 'profile' ? 'active' : '' ?>">
                            <?= icon('user', 15) ?> مشخصات و امنیت
                        </a>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="customer-main-wrap">
            <?php if ($flash = get_flash()): ?>
                <?php
                $flashType = is_array($flash) ? ($flash['type'] ?? 'info') : 'info';
                $flashMsg = is_array($flash) ? ($flash['message'] ?? '') : (string)$flash;
                $flashClass = match($flashType) {
                    'success' => 'alert-success',
                    'error' => 'alert-error',
                    'warning' => 'alert-warning',
                    default => 'alert-info'
                };
                ?>
                <div class="alert <?= $flashClass ?> mb-3"><?= e($flashMsg) ?></div>
            <?php endif; ?>
    <?php
}

function layout_customer_end(): void
{
    ?>
        </main>

        <footer class="customer-footer">
            <div>سامانه هوشمند فروش و شعب بفروش | تجربه اختصاصی مشتریان</div>
            <div style="font-size:0.75rem; color:var(--placeholder); margin-top:4px;">کلیه حقوق این سامانه محفوظ است.</div>
        </footer>

        <!-- Slide-over Mini-Cart Drawer -->
        <div id="miniCartBackdrop" class="mini-cart-backdrop" onclick="window.closeMiniCart()"></div>
        <div id="miniCartDrawer" class="mini-cart-drawer">
            <div class="mini-cart-header">
                <h3 style="font-size:1.05rem; font-weight:700; margin:0;">سبد خرید</h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="window.closeMiniCart()">&times;</button>
            </div>
            <div id="miniCartItems" class="mini-cart-items">
                <!-- Dynamically filled by BefrooshStore -->
            </div>
            <div class="mini-cart-footer">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-weight:700;">
                    <span>جمع اقلام:</span>
                    <span id="miniCartTotal" class="tabular-nums">۰ تومان</span>
                </div>
                <a href="/cart" class="btn btn-primary" style="width:100%; justify-content:center;">مشاهده سبد و تسویه حساب</a>
            </div>
        </div>

        <script>
        window.openMiniCart = function() {
            var d = document.getElementById('miniCartDrawer');
            var b = document.getElementById('miniCartBackdrop');
            if (d && b) { d.classList.add('open'); b.classList.add('open'); renderDrawerItems(); }
        };
        window.closeMiniCart = function() {
            var d = document.getElementById('miniCartDrawer');
            var b = document.getElementById('miniCartBackdrop');
            if (d && b) { d.classList.remove('open'); b.classList.remove('open'); }
        };
        function renderDrawerItems() {
            var items = window.BefrooshStore ? window.BefrooshStore.getCart() : [];
            var box = document.getElementById('miniCartItems');
            var totalEl = document.getElementById('miniCartTotal');
            if (!box || !totalEl) return;
            if (!items.length) {
                box.innerHTML = '<div style="text-align:center; padding:40px 10px; color:#6e6e73;">سبد خرید شما خالی است.</div>';
                totalEl.textContent = '۰ تومان';
                return;
            }
            var subtotal = 0;
            var html = '';
            items.forEach(function(it, idx) {
                var total = (it.price || 0) * (it.qty || 1);
                subtotal += total;
                html += '<div class="mini-cart-item">' +
                    '<div style="flex:1;">' +
                        '<div style="font-weight:600; font-size:0.88rem; color:#1d1d1f;">' + (it.title || 'کالا') + '</div>' +
                        '<div style="font-size:0.78rem; color:#6e6e73;" class="tabular-nums">' + Number(it.price||0).toLocaleString('fa-IR') + ' تومان × ' + it.qty + '</div>' +
                    '</div>' +
                    '<div style="text-align:left;">' +
                        '<div style="font-weight:700; font-size:0.88rem;" class="tabular-nums">' + Number(total).toLocaleString('fa-IR') + ' تومان</div>' +
                        '<button type="button" style="background:none; border:none; color:#ff3b30; font-size:0.75rem; cursor:pointer;" onclick="window.removeMiniCartItem(' + idx + '); renderDrawerItems();">حذف</button>' +
                    '</div>' +
                '</div>';
            });
            box.innerHTML = html;
            totalEl.textContent = Number(subtotal).toLocaleString('fa-IR') + ' تومان';
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (window.BefrooshStore) {
                var count = window.BefrooshStore.getCartCount();
                var b = document.getElementById('store-cart-badge');
                if (b && count > 0) { b.textContent = count; b.style.display = 'inline-flex'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
}
