<?php
declare(strict_types=1);

/**
 * layout_sidebar.php
 * Authenticated Dashboard Sidebar Navigation & Role-based Menus
 */

require_once __DIR__ . '/layout_icons.php';

function render_dashboard_sidebar(array $user, int $shopId, ?array $currentShop): void
{
    global $pdo;

    $role = $user['role'] ?? 'customer';
    $navItems = [];

    if ($role === 'customer') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد من', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/shops', 'label' => 'ویترین فروشگاه‌ها', 'icon' => 'store'];
        $navItems[] = ['url' => '/cart', 'label' => 'سبد خرید', 'icon' => 'cart'];
        $navItems[] = ['url' => '/favorites', 'label' => 'کالاهای نشان‌شده', 'icon' => 'heart'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات من', 'icon' => 'orders'];
        $navItems[] = ['url' => '/orders/track', 'label' => 'پیگیری مرسوله', 'icon' => 'search'];
        $navItems[] = ['url' => '/tickets', 'label' => 'پشتیبانی و تیکت‌ها', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/account/addresses', 'label' => 'آدرس‌های من', 'icon' => 'location'];
        $navItems[] = ['url' => '/account/profile', 'label' => 'پروفایل کاربری', 'icon' => 'user'];
    } elseif ($role === 'shop_owner') {
        $shopSlug = $currentShop['slug'] ?? 'central';
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد فروشگاه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'موجودی و انبارداری', 'icon' => 'products'];
        $navItems[] = ['url' => '/accounting', 'label' => 'دفتر کل و مالی', 'icon' => 'report'];
        $navItems[] = ['url' => '/reports', 'label' => 'گزارش‌های جامع و مالیاتی', 'icon' => 'report'];
        $navItems[] = ['url' => '/shop/messages', 'label' => 'پیام‌های تماس', 'icon' => 'send'];
        $navItems[] = ['url' => '/shop/settings', 'label' => 'تنظیمات و سیاست‌ها', 'icon' => 'settings'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/shop/' . $shopSlug, 'label' => 'مشاهده ویترین آنلاین', 'icon' => 'store'];
    } elseif ($role === 'shop_manager') {
        $shopSlug = $currentShop['slug'] ?? 'central';
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد فروشگاه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'انبارداری و شمارش', 'icon' => 'products'];
        $navItems[] = ['url' => '/reports', 'label' => 'گزارش‌های فروش و کالا', 'icon' => 'report'];
        $navItems[] = ['url' => '/shop/messages', 'label' => 'پیام‌های تماس', 'icon' => 'send'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/shop/' . $shopSlug, 'label' => 'مشاهده ویترین آنلاین', 'icon' => 'store'];
    } else { // admin or superadmin
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد سامانه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/shops/manage', 'label' => 'مدیریت فروشگاه‌ها', 'icon' => 'store'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات سراسری', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کاتالوگ محصولات', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'انبارداری کل', 'icon' => 'products'];
        $navItems[] = ['url' => '/accounting', 'label' => 'دفاتر حسابداری کل', 'icon' => 'report'];
        $navItems[] = ['url' => '/reports', 'label' => 'مرکز گزارشات و مالیات', 'icon' => 'report'];
        $navItems[] = ['url' => '/admins', 'label' => 'مدیران و شعب', 'icon' => 'shield'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان سامانه', 'icon' => 'customers'];
        $navItems[] = ['url' => '/reports/system', 'label' => 'گزارشات و بازرسی', 'icon' => 'report'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets'];
    }

    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $bestMatchUrl = '';
    $maxLen = 0;
    foreach ($navItems as $item) {
        $u = $item['url'];
        if ($currentPath === $u) {
            $bestMatchUrl = $u;
            break;
        }
        if ($currentPath === '/orders/track' && $u === '/orders') {
            continue;
        }
        if ($u !== '/' && str_starts_with($currentPath, rtrim($u, '/') . '/') && strlen($u) > $maxLen) {
            $maxLen = strlen($u);
            $bestMatchUrl = $u;
        }
    }

    // Calculate unread notification counts
    $unseenOrders = 0;
    $unseenTickets = 0;
    $unseenMessages = 0;
    $cartCount = 0;
    try {
        if ($role === 'customer') {
            $unseenOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE customer_id = " . (int)$user['id'] . " AND seen_by_customer = 0")->fetchColumn();
            $unseenTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE customer_id = " . (int)$user['id'] . " AND seen_by_customer = 0")->fetchColumn();
            $cartCount = (int)$pdo->query("SELECT COUNT(*) FROM cart_items WHERE user_id = " . (int)$user['id'])->fetchColumn();
        } elseif (in_array($role, ['shop_owner', 'shop_manager'], true)) {
            $unseenOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE shop_id = {$shopId} AND seen_by_admin = 0")->fetchColumn();
            $unseenTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE shop_id = {$shopId} AND seen_by_admin = 0")->fetchColumn();
            $unseenMessages = (int)$pdo->query("SELECT COUNT(*) FROM shop_contact_messages WHERE shop_id = {$shopId} AND is_read = 0")->fetchColumn();
        } else {
            $unseenOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE seen_by_admin = 0")->fetchColumn();
            $unseenTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE seen_by_admin = 0")->fetchColumn();
        }
    } catch (Throwable $e) {}
    ?>
    <aside class="sidebar">
        <div class="sidebar-header" style="padding:18px 16px; display:flex; align-items:center; gap:10px; border-bottom:1px solid rgba(255,255,255,0.08);">
            <div style="width:36px; height:36px; background:#2563eb; color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:900;">بف</div>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:800; font-size:1rem; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">سامانه بفروش</div>
                <div style="font-size:0.75rem; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($currentShop['name'] ?? 'پنل مدیریت') ?></div>
            </div>
            <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" style="background:none; border:none; color:#94a3b8; font-size:1.2rem; cursor:pointer; padding:4px;" title="بستن منو">✕</button>
        </div>

        <nav class="sidebar-nav" style="padding:14px 10px; flex:1; overflow-y:auto;">
            <?php foreach ($navItems as $item): 
                $badgeVal = 0;
                if ($item['url'] === '/orders') $badgeVal = $unseenOrders;
                if ($item['url'] === '/tickets') $badgeVal = $unseenTickets;
                if ($item['url'] === '/cart') $badgeVal = $cartCount;
                if ($item['url'] === '/shop/messages') $badgeVal = $unseenMessages;
                $active = ($item['url'] === $bestMatchUrl);
            ?>
                <a href="<?= e($item['url']) ?>" class="nav-link <?= $active ? 'active' : '' ?>">
                    <?= icon($item['icon'], 16) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if ($badgeVal > 0): ?>
                        <span class="nav-badge"><?= $badgeVal ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-box">
                <div style="width:34px; height:34px; border-radius:50%; background:#334155; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?= e(mb_substr($user['nickname'] ?? 'ک', 0, 1)) ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="user-name"><?= e($user['nickname'] ?? 'کاربر') ?></div>
                    <div class="user-role"><?= e($user['role'] ?? '') ?></div>
                </div>
                <form method="post" action="/logout" style="margin:0;">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" style="padding:4px; color:#ef4444;" title="خروج"><?= icon('logout', 16) ?></button>
                </form>
            </div>
        </div>
    </aside>
    <?php
}
