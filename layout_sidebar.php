<?php
declare(strict_types=1);

/**
 * layout_sidebar.php
 * Authenticated Dashboard Sidebar Navigation & Role-based Menus
 */

require_once __DIR__ . '/layout_icons.php';
require_once __DIR__ . '/helpers_shop.php';

function render_dashboard_sidebar(array $user, int $shopId, ?array $currentShop): void
{
    global $pdo;

    $role = $user['role'] ?? 'customer';
    $navItems = [];

    if ($role === 'customer') {
        $navItems = [
            'خرید و سفارشات' => [
                ['url' => '/dashboard', 'label' => 'داشبورد من', 'icon' => 'dashboard'],
                ['url' => '/shops', 'label' => 'ویترین فروشگاه‌ها', 'icon' => 'store'],
                ['url' => '/cart', 'label' => 'سبد خرید', 'icon' => 'cart'],
                ['url' => '/orders', 'label' => 'سفارشات من', 'icon' => 'orders'],
                ['url' => '/orders/track', 'label' => 'پیگیری مرسوله', 'icon' => 'search']
            ],
            'حساب کاربری' => [
                ['url' => '/favorites', 'label' => 'کالاهای نشان‌شده', 'icon' => 'heart'],
                ['url' => '/tickets', 'label' => 'پشتیبانی و تیکت‌ها', 'icon' => 'tickets'],
                ['url' => '/profile/addresses', 'label' => 'آدرس‌های تحویل', 'icon' => 'location'],
                ['url' => '/profile', 'label' => 'پروفایل کاربری', 'icon' => 'user']
            ]
        ];
    } elseif (in_array($role, ['business_owner', 'shop_owner'], true)) {
        $shopSlug = $currentShop['slug'] ?? 'central';
        $navItems = [
            'مدیریت و شعب' => [
                ['url' => '/dashboard', 'label' => 'داشبورد کسب‌وکار', 'icon' => 'dashboard'],
                ['url' => '/app/branches', 'label' => 'مدیریت شعب', 'icon' => 'store'],
                ['url' => '/app/shipping-groups', 'label' => 'گروه‌های ارسال متمرکز', 'icon' => 'orders']
            ],
            'عملیات و فروش' => [
                ['url' => '/orders', 'label' => 'سفارشات', 'icon' => 'orders'],
                ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'],
                ['url' => '/inventory', 'label' => 'موجودی و انبارداری', 'icon' => 'products']
            ],
            'مالی و گزارشات' => [
                ['url' => '/accounting', 'label' => 'دفتر کل و مالی', 'icon' => 'report'],
                ['url' => '/reports', 'label' => 'گزارش‌های جامع و مالیاتی', 'icon' => 'report']
            ],
            'مشتریان و پشتیبانی' => [
                ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'],
                ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'],
                ['url' => '/shop/messages', 'label' => 'پیام‌های تماس', 'icon' => 'send']
            ],
            'تنظیمات و حساب' => [
                ['url' => '/shop/settings', 'label' => 'تنظیمات کسب‌وکار', 'icon' => 'settings'],
                ['url' => '/profile/addresses', 'label' => 'آدرس‌های تحویل', 'icon' => 'location'],
                ['url' => '/profile', 'label' => 'پروفایل من', 'icon' => 'user'],
                ['url' => '/b/' . $shopSlug, 'label' => 'مشاهده ویترین آنلاین', 'icon' => 'store']
            ]
        ];
    } elseif (in_array($role, ['branch_manager', 'shop_manager', 'manager'], true)) {
        $shopSlug = $currentShop['slug'] ?? 'central';
        $navItems = [
            'عملیات روزمره' => [
                ['url' => '/dashboard', 'label' => 'داشبورد شعبه', 'icon' => 'dashboard'],
                ['url' => '/orders', 'label' => 'سفارشات شعبه', 'icon' => 'orders'],
                ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'],
                ['url' => '/inventory', 'label' => 'انبارداری و شمارش', 'icon' => 'products'],
                ['url' => '/reports', 'label' => 'گزارش‌های فروش و کالا', 'icon' => 'report']
            ],
            'ارتباطات' => [
                ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'],
                ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'],
                ['url' => '/shop/messages', 'label' => 'پیام‌های تماس', 'icon' => 'send']
            ],
            'حساب کاربری' => [
                ['url' => '/app/branches', 'label' => 'شعب من', 'icon' => 'store'],
                ['url' => '/profile/addresses', 'label' => 'آدرس‌های تحویل', 'icon' => 'location'],
                ['url' => '/profile', 'label' => 'پروفایل من', 'icon' => 'user'],
                ['url' => '/b/' . $shopSlug, 'label' => 'مشاهده ویترین آنلاین', 'icon' => 'store']
            ]
        ];
    } else { // admin or superadmin
        $navItems = [
            'سامانه کلان' => [
                ['url' => '/dashboard', 'label' => 'داشبورد سامانه', 'icon' => 'dashboard'],
                ['url' => '/shops/manage', 'label' => 'مدیریت کسب‌وکارها', 'icon' => 'store'],
                ['url' => '/app/branches', 'label' => 'مدیریت شعب سراسری', 'icon' => 'store'],
                ['url' => '/app/plans', 'label' => 'پلن‌های اشتراک', 'icon' => 'shield']
            ],
            'عملیات سراسری' => [
                ['url' => '/app/shipping-groups', 'label' => 'گروه‌های ارسال متمرکز', 'icon' => 'orders'],
                ['url' => '/orders', 'label' => 'سفارشات سراسری', 'icon' => 'orders'],
                ['url' => '/products', 'label' => 'کاتالوگ محصولات', 'icon' => 'products'],
                ['url' => '/inventory', 'label' => 'انبارداری کل', 'icon' => 'products']
            ],
            'حسابداری و بازرسی' => [
                ['url' => '/accounting', 'label' => 'دفاتر حسابداری کل', 'icon' => 'report'],
                ['url' => '/reports', 'label' => 'مرکز گزارشات و مالیات', 'icon' => 'report'],
                ['url' => '/reports/system', 'label' => 'گزارشات و بازرسی', 'icon' => 'report']
            ],
            'کاربران و پشتیبانی' => [
                ['url' => '/admins', 'label' => 'مدیران و دسترسی‌ها', 'icon' => 'shield'],
                ['url' => '/customers', 'label' => 'مشتریان سامانه', 'icon' => 'customers'],
                ['url' => '/tickets', 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets'],
                ['url' => '/profile/addresses', 'label' => 'آدرس‌های تحویل', 'icon' => 'location'],
                ['url' => '/profile', 'label' => 'پروفایل من', 'icon' => 'user']
            ]
        ];
    }

    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $bestMatchUrl = '';
    $maxLen = 0;
    foreach ($navItems as $groupName => $items) {
        foreach ($items as $item) {
            $u = $item['url'];
            if ($currentPath === $u) {
                $bestMatchUrl = $u;
                break 2;
            }
            if ($currentPath === '/orders/track' && $u === '/orders') {
                continue;
            }
            if ($u !== '/' && str_starts_with($currentPath, rtrim($u, '/') . '/') && strlen($u) > $maxLen) {
                $maxLen = strlen($u);
                $bestMatchUrl = $u;
            }
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
        } elseif (in_array($role, ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager'], true)) {
            $unseenOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE shop_id = {$shopId} AND seen_by_admin = 0")->fetchColumn();
            $unseenTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE shop_id = {$shopId} AND seen_by_admin = 0")->fetchColumn();
            $unseenMessages = (int)$pdo->query("SELECT COUNT(*) FROM shop_contact_messages WHERE shop_id = {$shopId} AND is_read = 0")->fetchColumn();
        } else {
            $unseenOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE seen_by_admin = 0")->fetchColumn();
            $unseenTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE seen_by_admin = 0")->fetchColumn();
        }
    } catch (Throwable $e) {}

    // Multi-Business and Multi-Branch Resolution
    $userBusinesses = [];
    $assignedBranches = [];
    $activeBranchId = active_branch_id();

    if (in_array($role, ['superadmin', 'admin'], true)) {
        $userBusinesses = all_active_shops();
        $assignedBranches = get_business_branches($shopId);
    } elseif (in_array($role, ['business_owner', 'shop_owner'], true)) {
        $userBusinesses = get_user_businesses((int)$user['id']);
        $assignedBranches = get_business_branches($shopId);
    } elseif (in_array($role, ['branch_manager', 'shop_manager', 'manager'], true)) {
        $assignedBranches = get_user_assigned_branches($user, $shopId);
    }
    ?>
    <aside class="sidebar">
        <div class="sidebar-header" style="padding:16px 14px; border-bottom:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:<?= (!empty($userBusinesses) && count($userBusinesses) > 1) || !empty($assignedBranches) ? '12px' : '0' ?>;">
                <div style="width:36px; height:36px; background:#2563eb; color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:900;">بف</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:800; font-size:1rem; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">سامانه بفروش</div>
                    <div style="font-size:0.75rem; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($currentShop['name'] ?? 'پنل مدیریت') ?></div>
                </div>
                <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" style="background:none; border:none; color:#94a3b8; font-size:1.2rem; cursor:pointer; padding:4px;" title="بستن منو">✕</button>
            </div>

            <?php if (!empty($userBusinesses) && count($userBusinesses) > 1): ?>
                <form method="post" action="/app/switch-business" style="margin-bottom:8px;">
                    <?= csrf_field() ?>
                    <label style="display:block; font-size:0.68rem; color:#94a3b8; margin-bottom:2px;">🏢 کسب‌وکار انتخابی:</label>
                    <select name="business_id" onchange="this.form.submit()" style="width:100%; font-size:0.78rem; padding:5px 7px; background:#1e293b; color:#e2e8f0; border:1px solid #334155; border-radius:6px; cursor:pointer;">
                        <?php foreach ($userBusinesses as $biz): ?>
                            <option value="<?= (int)$biz['id'] ?>" <?= (int)$biz['id'] === $shopId ? 'selected' : '' ?>>
                                <?= e($biz['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <?php if (!empty($assignedBranches)): ?>
                <form method="post" action="/app/switch-branch" style="margin:0;">
                    <?= csrf_field() ?>
                    <label style="display:block; font-size:0.68rem; color:#94a3b8; margin-bottom:2px;">📍 فیلتر شعبه:</label>
                    <select name="branch_id" onchange="this.form.submit()" style="width:100%; font-size:0.78rem; padding:5px 7px; background:#1e293b; color:#e2e8f0; border:1px solid #334155; border-radius:6px; cursor:pointer;">
                        <?php if (in_array($role, ['superadmin', 'admin', 'business_owner', 'shop_owner'], true)): ?>
                            <option value="0" <?= $activeBranchId === null ? 'selected' : '' ?>>🏢 کل کسب‌وکار (سراسری)</option>
                        <?php endif; ?>
                        <?php foreach ($assignedBranches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>" <?= $activeBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                📍 <?= e($br['name']) ?><?= !empty($br['is_main']) ? ' (اصلی)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

        <nav class="sidebar-nav" style="padding:14px 10px; flex:1; overflow-y:auto;">
            <?php 
            $groupIndex = 0;
            foreach ($navItems as $groupName => $items): 
                $groupIndex++;
                $hasActive = false;
                foreach ($items as $it) {
                    if ($it['url'] === $bestMatchUrl) {
                        $hasActive = true;
                        break;
                    }
                }
            ?>
                <div class="nav-group" data-active="<?= $hasActive ? 'true' : 'false' ?>">
                    <div class="nav-group-header" onclick="toggleNavGroup(this)" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-size:0.75rem; color:#64748b; font-weight:bold; margin:10px 12px 6px 0; padding:6px 0; text-transform:uppercase; letter-spacing:0.5px; transition:color 0.2s;">
                        <span><?= e($groupName) ?></span>
                        <span class="nav-group-icon" style="transition:transform 0.2s; transform: <?= $hasActive ? 'rotate(180deg)' : 'rotate(0)' ?>;">▼</span>
                    </div>
                    <div class="nav-group-items" style="display: <?= $hasActive ? 'block' : 'none' ?>; overflow:hidden;">
                        <?php foreach ($items as $item): 
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
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        
        <script>
            function toggleNavGroup(headerEl) {
                const groupEl = headerEl.closest('.nav-group');
                const itemsEl = groupEl.querySelector('.nav-group-items');
                const iconEl = groupEl.querySelector('.nav-group-icon');
                const isOpen = itemsEl.style.display === 'block';
                
                // If opening, close others that are NOT the active group
                if (!isOpen) {
                    document.querySelectorAll('.nav-group').forEach(otherGroup => {
                        if (otherGroup !== groupEl && otherGroup.getAttribute('data-active') !== 'true') {
                            otherGroup.querySelector('.nav-group-items').style.display = 'none';
                            otherGroup.querySelector('.nav-group-icon').style.transform = 'rotate(0)';
                        }
                    });
                }
                
                // Toggle current group
                if (isOpen) {
                    // Only allow closing if it's NOT the active group
                    if (groupEl.getAttribute('data-active') !== 'true') {
                        itemsEl.style.display = 'none';
                        iconEl.style.transform = 'rotate(0)';
                    }
                } else {
                    itemsEl.style.display = 'block';
                    iconEl.style.transform = 'rotate(180deg)';
                }
            }
        </script>

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
