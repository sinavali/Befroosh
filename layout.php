<?php
declare(strict_types=1);

/**
 * layout.php
 * Unified Layout Manager for Befroosh Platform
 */

require_once __DIR__ . '/layout_icons.php';
require_once __DIR__ . '/layout_components.php';
require_once __DIR__ . '/layout_head.php';
require_once __DIR__ . '/layout_sidebar.php';
require_once __DIR__ . '/layout_topbar.php';
require_once __DIR__ . '/layout_public.php';

function error_page(int $code, string $title, string $desc): void
{
    http_response_code($code);
    $user = current_user();
    if ($user) {
        layout_start($title, $user);
        echo '<div class="card"><div class="card-body">' . empty_state($title, $desc, 'clock') . '</div></div>';
        layout_end();
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <?php render_layout_head($title); ?>
    </head>
    <body style="display:flex; align-items:center; justify-content:center; min-height:100vh;">
        <div class="card" style="max-width:440px; width:90%; text-align:center; padding:32px;">
            <h1 style="font-size:1.2rem; color:#dc2626; margin-bottom:8px;"><?= e($title) ?></h1>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px;"><?= e($desc) ?></p>
            <a href="/" class="btn btn-primary">بازگشت به سامانه</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function auth_layout_start(string $title): void
{
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <?php render_layout_head($title); ?>
        <style>
            body { background: linear-gradient(135deg, #0f172a, #1e293b 60%, #334155); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .auth-card { width: 100%; max-width: 440px; background: #fff; border-radius: 12px; padding: 32px 28px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <div style="text-align:center; margin-bottom:20px;">
                <div style="width:48px; height:48px; border-radius:12px; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:1.3rem; margin:0 auto 10px;">بف</div>
                <h1 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;"><?= e($title) ?></h1>
            </div>
    <?php
}

function auth_layout_end(): void
{
    ?>
        </div>
    </body>
    </html>
    <?php
}

function layout_start(string $title, ?array $user = null): void
{
    $user = $user ?? current_user();
    if (!$user) {
        // If un-authenticated user reaches layout_start, use public layout!
        $currentShop = current_shop();
        layout_public_start($title, $currentShop, null);
        return;
    }

    $shopId = active_shop_id();
    $currentShop = current_shop();
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <?php render_layout_head($title); ?>
        <style>
            .app-container { display: flex; min-height: 100vh; }
            .sidebar { width: 256px; background: #111827; color: #fff; position: fixed; right: 0; top: 0; bottom: 0; z-index: 50; display: flex; flex-direction: column; }
            .nav-link { display: flex; align-items: center; gap: 10px; color: #94a3b8; padding: 9px 12px; border-radius: 8px; margin-bottom: 3px; font-size: 0.88rem; font-weight: 500; transition: 0.15s; position: relative; }
            .nav-link:hover { background: rgba(255,255,255,0.06); color: #fff; }
            .nav-link.active { background: #2563eb; color: #fff; font-weight: 700; }
            .nav-badge { margin-right: auto; background: #ef4444; color: #fff; border-radius: 99px; font-size: 0.68rem; padding: 2px 7px; font-weight: 800; }
            .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
            .user-box { display: flex; align-items: center; gap: 10px; }
            .user-name { font-size: 0.85rem; font-weight: bold; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .user-role { font-size: 0.7rem; color: #94a3b8; }
            .main-content { flex: 1; margin-right: 256px; display: flex; flex-direction: column; min-width: 0; }
            .topbar { background: #fff; border-bottom: 1px solid var(--border); min-height: 56px; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 40; }
            .mobile-toggle { display: none; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #334155; }
            .content-area { padding: 20px; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }
            .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 45; }
            .sidebar-backdrop.open { display: block; }
            @media (max-width: 1024px) {
                .sidebar { transform: translateX(100%); transition: transform 0.25s ease-in-out; }
                .sidebar.open { transform: translateX(0); }
                .main-content { margin-right: 0; }
                .mobile-toggle { display: inline-flex; }
            }
        </style>
    </head>
    <body>
        <div class="app-container">
            <div id="sidebarBackdrop" class="sidebar-backdrop"></div>
            <?php render_dashboard_sidebar($user, $shopId, $currentShop); ?>
            <div class="main-content">
                <?php render_dashboard_topbar($title, $user, $shopId, $currentShop); ?>
                <div class="content-area">
                    <?php if ($flash = get_flash()): ?>
                        <?php
                        $flashType = is_array($flash) ? ($flash['type'] ?? 'info') : 'info';
                        $flashMsg = is_array($flash) ? ($flash['message'] ?? '') : (string)$flash;
                        $flashStyle = match($flashType) {
                            'success' => 'background:#ecfdf5; border-color:#a7f3d0; color:#065f46;',
                            'error' => 'background:#fef2f2; border-color:#fecaca; color:#991b1b;',
                            'warning' => 'background:#fffbeb; border-color:#fde68a; color:#92400e;',
                            default => 'background:#eff6ff; border-color:#bfdbfe; color:#1e40af;'
                        };
                        ?>
                        <div class="card mb-3" style="<?= $flashStyle ?> padding:12px 16px; font-weight:bold; border-radius:8px;">
                            <?= e($flashMsg) ?>
                        </div>
                    <?php endif; ?>
    <?php
}

function layout_end(): void
{
    $user = current_user();
    if (!$user) {
        layout_public_end(current_shop());
        return;
    }
    ?>
                </div>
            </div>
        </div>
        <script>
            function closeSidebar() {
                var s = document.querySelector('.sidebar');
                var b = document.getElementById('sidebarBackdrop');
                if (s) s.classList.remove('open');
                if (b) b.classList.remove('open');
            }
            function openSidebar() {
                var s = document.querySelector('.sidebar');
                var b = document.getElementById('sidebarBackdrop');
                if (s) s.classList.add('open');
                if (b) b.classList.add('open');
            }
            window.closeSidebar = closeSidebar;
            window.openSidebar = openSidebar;
            var toggle = document.getElementById('sidebarToggle');
            if (toggle) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var s = document.querySelector('.sidebar');
                    if (s && s.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }
            var backdrop = document.getElementById('sidebarBackdrop');
            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }
            document.addEventListener('click', function(e) {
                var s = document.querySelector('.sidebar');
                var t = document.getElementById('sidebarToggle');
                if (s && s.classList.contains('open')) {
                    if (!s.contains(e.target) && (!t || !t.contains(e.target))) {
                        closeSidebar();
                    }
                }
            });

            // Universal 3-digit comma formatting for price inputs
            function formatPriceInput(el) {
                var raw = el.value.replace(/[^0-9۰-۹]/g, '');
                if (!raw) return;
                var en = raw.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
                var num = parseInt(en, 10);
                if (isNaN(num)) return;
                el.value = num.toLocaleString('en-US');
            }
            document.querySelectorAll('input.price-input, input[data-type="price"], input[name*="price"], input[name*="cost"], input[name*="threshold"]').forEach(function(el) {
                if (el.type === 'number') { el.type = 'text'; el.inputMode = 'numeric'; }
                if (el.value) formatPriceInput(el);
                el.addEventListener('input', function() { formatPriceInput(this); });
            });
        </script>
    </body>
    </html>
    <?php
}