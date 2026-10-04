<?php
declare(strict_types=1);

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
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?></title>
        <style>
            body {
                font-family: Tahoma, sans-serif;
                background: #f5f6f8;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                color: #111827;
            }

            .box {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 34px;
                text-align: center;
                max-width: 430px;
                width: calc(100% - 32px);
            }

            a {
                display: inline-block;
                margin-top: 14px;
                background: #2563eb;
                color: #fff;
                padding: 9px 16px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 700;
            }
        </style>
    </head>

    <body>
        <div class="box">
            <h1 style="margin:0 0 8px; font-size:1.15rem;"><?= e($title) ?></h1>
            <p style="color:#6b7280; margin:0;"><?= e($desc) ?></p>
            <a href="/">بازگشت به سامانه</a>
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
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | سامانه سفارشات</title>
        <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: 'Vazirmatn', Tahoma, sans-serif;
                background: linear-gradient(135deg, #0f172a, #1e293b 55%, #334155);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 18px;
                color: #111827;
            }

            .auth-card {
                width: 100%;
                max-width: 430px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 28px;
                box-shadow: 0 24px 60px rgba(0, 0, 0, .24);
            }

            .logo {
                width: 48px;
                height: 48px;
                border-radius: 10px;
                background: #1d4ed8;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 14px;
            }

            h1 {
                text-align: center;
                font-size: 1.2rem;
                margin-bottom: 6px;
            }

            .sub {
                text-align: center;
                color: #6b7280;
                font-size: .86rem;
                margin-bottom: 18px;
            }

            label {
                display: flex;
                gap: 5px;
                align-items: center;
                font-size: .83rem;
                font-weight: 700;
                color: #374151;
                margin-bottom: 6px;
            }

            .input {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 10px 12px;
                outline: none;
                background: #fff;
            }

            .input:focus {
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
            }

            .form-group {
                margin-bottom: 14px;
            }

            .btn {
                width: 100%;
                border: none;
                background: #1d4ed8;
                color: #fff;
                border-radius: 8px;
                padding: 11px 14px;
                font-weight: 800;
            }

            .error {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
                padding: 10px 12px;
                border-radius: 8px;
                font-size: .85rem;
                font-weight: 700;
                margin-bottom: 14px;
            }
        </style>
    </head>

    <body>
        <div class="auth-card">
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

function layout_start(string $title, array $user): void
{
    global $pdo;

    $role = $user['role'] ?? '';
    $shopId = active_shop_id();
    $currentShop = current_shop();
    $navItems = [];

    if ($role === 'customer') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/shops', 'label' => 'ویترین فروشگاه‌ها', 'icon' => 'orders'];
        $navItems[] = ['url' => '/cart', 'label' => 'سبد خرید', 'icon' => 'orders'];
        $navItems[] = ['url' => '/bookmarks', 'label' => 'کالاهای نشان‌شده', 'icon' => 'products'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات من', 'icon' => 'orders'];
        $navItems[] = ['url' => '/orders/track', 'label' => 'پیگیری مرسوله', 'icon' => 'orders'];
        $navItems[] = ['url' => '/tickets', 'label' => 'پشتیبانی و تیکت‌ها', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/account/addresses', 'label' => 'آدرس‌های تحویل', 'icon' => 'location'];
        $navItems[] = ['url' => '/account/profile', 'label' => 'پروفایل کاربری', 'icon' => 'user'];
    }

    if ($role === 'shop_owner') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد فروشگاه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'موجودی و انبارداری', 'icon' => 'products'];
        $navItems[] = ['url' => '/accounting', 'label' => 'دفتر کل و مالی', 'icon' => 'report'];
        $navItems[] = ['url' => '/orders/report', 'label' => 'گزارش‌های فروش', 'icon' => 'report'];
        $navItems[] = ['url' => '/shop/settings', 'label' => 'کارت‌ها و تنظیمات', 'icon' => 'settings'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/shops', 'label' => 'خرید از سایر شعب', 'icon' => 'orders'];
        $navItems[] = ['url' => '/cart', 'label' => 'سبد خرید من', 'icon' => 'orders'];
    }

    if ($role === 'shop_manager') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد فروشگاه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کالاها و بارکدها', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'انبارداری و شمارش', 'icon' => 'products'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان', 'icon' => 'customers'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های مشتریان', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/shops', 'label' => 'خرید از سایر شعب', 'icon' => 'orders'];
        $navItems[] = ['url' => '/cart', 'label' => 'سبد خرید من', 'icon' => 'orders'];
    }

    if ($role === 'admin') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد سامانه', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/shops/manage', 'label' => 'مدیریت فروشگاه‌ها', 'icon' => 'settings'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات سراسری', 'icon' => 'orders'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان سامانه', 'icon' => 'customers'];
        $navItems[] = ['url' => '/admins', 'label' => 'مدیران و عوامل شعب', 'icon' => 'shield'];
        $navItems[] = ['url' => '/reports/system', 'label' => 'گزارشات و بازرسی', 'icon' => 'report'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌های پشتیبانی', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/orders/report', 'label' => 'گزارش‌های تجمیعی', 'icon' => 'report'];
    }

    if ($role === 'superadmin') {
        $navItems[] = ['url' => '/dashboard', 'label' => 'داشبورد پلتفرم', 'icon' => 'dashboard'];
        $navItems[] = ['url' => '/shops/manage', 'label' => 'مدیریت فروشگاه‌ها', 'icon' => 'settings'];
        $navItems[] = ['url' => '/orders', 'label' => 'سفارشات (سراسری)', 'icon' => 'orders'];
        $navItems[] = ['url' => '/products', 'label' => 'کاتالوگ محصولات', 'icon' => 'products'];
        $navItems[] = ['url' => '/inventory', 'label' => 'انبارداری کل', 'icon' => 'products'];
        $navItems[] = ['url' => '/accounting', 'label' => 'دفاتر حسابداری کل', 'icon' => 'report'];
        $navItems[] = ['url' => '/orders/report', 'label' => 'گزارش‌های تجمیعی', 'icon' => 'report'];
        $navItems[] = ['url' => '/admins', 'label' => 'مدیران و دسترسی‌ها', 'icon' => 'shield'];
        $navItems[] = ['url' => '/customers', 'label' => 'مشتریان سامانه', 'icon' => 'customers'];
        $navItems[] = ['url' => '/reports/system', 'label' => 'گزارشات و بازرسی', 'icon' => 'report'];
        $navItems[] = ['url' => '/tickets', 'label' => 'تیکت‌ها', 'icon' => 'tickets'];
        $navItems[] = ['url' => '/settings/retention', 'label' => 'نگهداری داده‌ها', 'icon' => 'settings'];
    }

    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    $isActive = function (string $url) use ($currentPath): bool {
        return $currentPath === $url || str_starts_with($currentPath, rtrim($url, '/') . '/');
    };

    $unseenOrders = 0;
    $unseenTickets = 0;
    $lowStockAlerts = 0;
    $cartCount = 0;

    try {
        if (!empty($user['id'])) {
            $cartCount = get_cart_count((int)$user['id']);
        }

        if ($role === 'customer') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND seen_by_customer = 0");
            $stmt->execute([$user['id']]);
            $unseenOrders = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND seen_by_customer = 0");
            $stmt->execute([$user['id']]);
            $unseenTickets = (int) $stmt->fetchColumn();
        } elseif (in_array($role, ['shop_owner', 'shop_manager'], true)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND seen_by_admin = 0");
            $stmt->execute([$shopId]);
            $unseenOrders = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE shop_id = ? AND seen_by_admin = 0");
            $stmt->execute([$shopId]);
            $unseenTickets = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE shop_id = ? AND stock_quantity <= min_stock_alert AND deleted_at IS NULL");
            $stmt->execute([$shopId]);
            $lowStockAlerts = (int) $stmt->fetchColumn();
        } elseif (in_array($role, ['admin', 'superadmin'], true)) {
            $unseenOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE seen_by_admin = 0")->fetchColumn();
            $unseenTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE seen_by_admin = 0")->fetchColumn();
            $lowStockAlerts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_alert AND deleted_at IS NULL")->fetchColumn();
        }
    } catch (Throwable $ex) {
    }

    $getBadge = function (string $url) use ($unseenOrders, $unseenTickets, $lowStockAlerts, $cartCount): int {
        if ($url === '/orders')
            return $unseenOrders;
        if ($url === '/tickets')
            return $unseenTickets;
        if ($url === '/inventory' && $lowStockAlerts > 0)
            return $lowStockAlerts;
        if ($url === '/cart' && $cartCount > 0)
            return $cartCount;
        return 0;
    };

    $GLOBALS['navItems'] = $navItems;
    $GLOBALS['isActive'] = $isActive;
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | سامانه سفارشات</title>

        <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
        <link href="/assets/vendor/select2.min.css" rel="stylesheet">

        <script src="/assets/vendor/jquery.min.js"></script>
        <script src="/assets/vendor/select2.min.js"></script>

        <style>
            :root {
                --font: 'Vazirmatn', Tahoma, sans-serif;
                --bg: #f5f6f8;
                --card: #ffffff;
                --border: #e5e7eb;
                --text: #111827;
                --muted: #6b7280;
                --primary: #1d4ed8;
                --primary-dark: #1e40af;
                --radius: 8px;
                --shadow: 0 8px 24px rgba(15, 23, 42, .05);
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            html {
                font-size: 15px;
            }

            body {
                font-family: var(--font);
                background: var(--bg);
                color: var(--text);
                line-height: 1.8;
                min-height: 100vh;
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            img {
                max-width: 100%;
                display: block;
            }

            button,
            input,
            select,
            textarea {
                font-family: inherit;
                font-size: .92rem;
            }

            button {
                cursor: pointer;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            h1 {
                font-size: 1.28rem;
                font-weight: 800;
            }

            h2 {
                font-size: 1rem;
                font-weight: 800;
            }

            h3 {
                font-size: .93rem;
                font-weight: 800;
            }

            .app {
                display: flex;
                min-height: 100vh;
            }

            .sidebar {
                width: 256px;
                background: #111827;
                color: #fff;
                position: fixed;
                top: 0;
                bottom: 0;
                right: 0;
                z-index: 60;
                display: flex;
                flex-direction: column;
                transition: transform .22s ease;
            }

            .brand {
                padding: 16px;
                border-bottom: 1px solid rgba(255, 255, 255, .08);
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .brand-icon {
                width: 34px;
                height: 34px;
                border-radius: 8px;
                background: #1d4ed8;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .brand-title {
                font-weight: 800;
                font-size: .95rem;
            }

            .brand-sub {
                color: #9ca3af;
                font-size: .72rem;
            }

            .sidebar-nav {
                padding: 12px;
                overflow-y: auto;
                flex: 1;
            }

            .nav-label {
                color: #6b7280;
                font-size: .68rem;
                font-weight: 800;
                padding: 0 8px 8px;
            }

            .nav-link {
                display: flex;
                align-items: center;
                gap: 9px;
                color: #d1d5db;
                padding: 9px 10px;
                border-radius: 8px;
                margin-bottom: 3px;
                transition: .14s;
                font-size: .89rem;
                position: relative;
            }

            .nav-link:hover {
                background: rgba(255, 255, 255, .06);
                color: #fff;
            }

            .nav-link.active {
                background: rgba(29, 78, 216, .24);
                color: #fff;
                font-weight: 700;
            }

            .nav-link.active:before {
                content: '';
                position: absolute;
                right: 0;
                top: 22%;
                bottom: 22%;
                width: 3px;
                border-radius: 99px;
                background: #60a5fa;
            }

            .nav-badge {
                margin-right: auto;
                background: #dc2626;
                color: #fff;
                min-width: 18px;
                height: 18px;
                border-radius: 99px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: .66rem;
                padding: 0 5px;
                font-weight: 800;
            }

            .sidebar-footer {
                padding: 12px;
                border-top: 1px solid rgba(255, 255, 255, .08);
            }

            .user-box {
                display: flex;
                align-items: center;
                gap: 9px;
            }

            .avatar {
                width: 33px;
                height: 33px;
                border-radius: 99px;
                background: #374151;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                flex-shrink: 0;
            }

            .user-name {
                font-size: .84rem;
                font-weight: 700;
                color: #fff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 115px;
            }

            .user-role {
                font-size: .71rem;
                color: #9ca3af;
            }

            .main {
                flex: 1;
                margin-right: 256px;
                display: flex;
                flex-direction: column;
                min-width: 0;
            }

            .topbar {
                position: sticky;
                top: 0;
                z-index: 40;
                background: rgba(255, 255, 255, .95);
                backdrop-filter: blur(8px);
                border-bottom: 1px solid var(--border);
                min-height: 58px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 10px 16px;
            }

            .topbar-title {
                font-weight: 800;
                color: #334155;
            }

            .topbar-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .mobile-toggle {
                display: none;
                width: 36px;
                height: 36px;
                border-radius: 8px;
                border: 1px solid var(--border);
                background: #fff;
                align-items: center;
                justify-content: center;
                color: #334155;
            }

            .sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .45);
                z-index: 55;
            }

            .content {
                padding: 18px;
                width: 100%;
                max-width: 1300px;
                margin: 0 auto;
            }

            .card {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 10px;
                box-shadow: var(--shadow);
            }

            .card-header {
                padding: 13px 14px;
                border-bottom: 1px solid #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                flex-wrap: wrap;
            }

            .card-body {
                padding: 14px;
            }

            .card-footer {
                padding: 12px 14px;
                border-top: 1px solid #f3f4f6;
                background: #fafafa;
                border-radius: 0 0 10px 10px;
            }

            .page-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 16px;
                flex-wrap: wrap;
            }

            .page-title-wrap {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .page-icon {
                width: 38px;
                height: 38px;
                border-radius: 9px;
                background: #eff6ff;
                color: var(--primary);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .page-sub {
                color: var(--muted);
                font-size: .8rem;
                margin-top: 1px;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                border: none;
                border-radius: var(--radius);
                padding: 8px 12px;
                font-size: .87rem;
                font-weight: 700;
                transition: .14s;
                line-height: 1.5;
                white-space: nowrap;
            }

            .btn:hover {
                transform: translateY(-1px);
            }

            .btn:active {
                transform: scale(.99);
            }

            .btn-primary {
                background: var(--primary);
                color: #fff;
            }

            .btn-primary:hover {
                background: var(--primary-dark);
            }

            .btn-success {
                background: #047857;
                color: #fff;
            }

            .btn-danger {
                background: #be123c;
                color: #fff;
            }

            .btn-warning {
                background: #b45309;
                color: #fff;
            }

            .btn-outline {
                background: #fff;
                border: 1px solid var(--border);
                color: #374151;
            }

            .btn-outline:hover {
                background: #f9fafb;
            }

            .btn-ghost {
                background: transparent;
                color: #4b5563;
            }

            .btn-ghost:hover {
                background: #f3f4f6;
            }

            .btn-sm {
                padding: 5px 9px;
                font-size: .79rem;
                border-radius: 7px;
            }

            .btn-block {
                width: 100%;
            }

            .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .form-grid.one {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 12px;
            }

            label {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: .82rem;
                font-weight: 700;
                color: #374151;
                margin-bottom: 5px;
            }

            .input,
            .select,
            .textarea,
            .select2-container .select2-selection--single,
            .select2-container .select2-selection--multiple {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: var(--radius);
                background: #fff;
                transition: .14s;
                outline: none;
                color: #111827;
            }

            .input,
            .select,
            .textarea {
                padding: 9px 11px;
            }

            .select,
            .input[type=date] {
                min-height: 39px;
            }

            .input:focus,
            .select:focus,
            .textarea:focus,
            .select2-container--default .select2-selection--single:focus,
            .select2-container--default .select2-selection--multiple:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(29, 78, 216, .09);
            }

            .textarea {
                min-height: 98px;
                resize: vertical;
            }

            .form-hint {
                font-size: .73rem;
                color: var(--muted);
                margin-top: 4px;
            }

            .form-check {
                display: flex;
                align-items: center;
                gap: 7px;
                font-size: .87rem;
                font-weight: 600;
                color: #374151;
                cursor: pointer;
            }

            .form-check input {
                width: 15px;
                height: 15px;
                accent-color: var(--primary);
            }

            .inline-form {
                display: inline-flex;
                align-items: center;
                gap: 7px;
            }

            .select2-container {
                width: 100% !important;
            }

            .select2-container--default .select2-selection--single {
                height: 39px;
                display: flex;
                align-items: center;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: #111827;
                padding-right: 11px;
                padding-left: 24px;
                width: 100%;
                text-align: right;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                left: 8px;
                right: auto;
            }

            .select2-container--default .select2-selection--multiple {
                min-height: 39px;
                padding: 4px 7px;
            }

            .select2-container--default .select2-search--inline .select2-search__field {
                margin-top: 4px;
            }

            .select2-dropdown {
                border: 1px solid #d1d5db;
                border-radius: 8px;
                box-shadow: var(--shadow);
            }

            .select2-results__option {
                font-size: .86rem;
            }

            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background: #1d4ed8;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                color: #1e40af;
                border-radius: 6px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .table th {
                padding: 10px 11px;
                background: #f9fafb;
                color: #6b7280;
                font-size: .73rem;
                font-weight: 800;
                text-align: right;
                border-bottom: 1px solid var(--border);
                white-space: nowrap;
            }

            .table td {
                padding: 10px 11px;
                border-bottom: 1px solid #f3f4f6;
                font-size: .87rem;
                vertical-align: middle;
            }

            .table tbody tr {
                transition: .12s;
            }

            .table tbody tr:hover {
                background: #f9fafb;
            }

            .table tbody tr:last-child td {
                border-bottom: none;
            }

            .date-cell {
                direction: ltr;
                text-align: right;
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }

            .sort-link {
                color: #374151;
                font-weight: 800;
            }

            .sort-link.active {
                color: var(--primary);
            }

            .badge {
                display: inline-flex;
                align-items: center;
                padding: 3px 8px;
                border-radius: 6px;
                font-size: .72rem;
                font-weight: 800;
                white-space: nowrap;
            }

            .badge-blue {
                background: #eff6ff;
                color: #1d4ed8;
            }

            .badge-emerald {
                background: #ecfdf5;
                color: #047857;
            }

            .badge-amber {
                background: #fffbeb;
                color: #b45309;
            }

            .badge-rose {
                background: #fff1f2;
                color: #be123c;
            }

            .badge-purple {
                background: #faf5ff;
                color: #7e22ce;
            }

            .badge-gray {
                background: #f3f4f6;
                color: #4b5563;
            }

            .alert {
                padding: 11px 13px;
                border-radius: 9px;
                display: flex;
                align-items: flex-start;
                gap: 9px;
                margin-bottom: 14px;
                font-size: .87rem;
                font-weight: 600;
            }

            .alert-success {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .alert-error {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            .alert-info {
                background: #eff6ff;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }

            .alert-warning {
                background: #fffbeb;
                color: #92400e;
                border: 1px solid #fde68a;
            }

            .alert .close {
                margin-right: auto;
                background: none;
                border: none;
                font-size: 16px;
                line-height: 1;
                opacity: .65;
            }

            .alert .close:hover {
                opacity: 1;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                gap: 12px;
                margin-bottom: 16px;
            }

            .stat-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 14px;
                display: flex;
                gap: 10px;
                align-items: center;
                box-shadow: var(--shadow);
            }

            .stat-icon {
                width: 42px;
                height: 42px;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .stat-icon.blue {
                background: #eff6ff;
                color: #2563eb;
            }

            .stat-icon.emerald {
                background: #ecfdf5;
                color: #059669;
            }

            .stat-icon.amber {
                background: #fffbeb;
                color: #d97706;
            }

            .stat-icon.purple {
                background: #faf5ff;
                color: #9333ea;
            }

            .stat-icon.rose {
                background: #fff1f2;
                color: #e11d48;
            }

            .stat-value {
                font-size: 1.13rem;
                font-weight: 900;
                line-height: 1.4;
            }

            .stat-label {
                color: var(--muted);
                font-size: .76rem;
            }

            .empty-state {
                text-align: center;
                padding: 36px 16px;
                color: var(--muted);
            }

            .empty-state-icon {
                width: 58px;
                height: 58px;
                margin: 0 auto 10px;
                border-radius: 10px;
                background: #f3f4f6;
                color: #9ca3af;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .empty-state h3 {
                color: #374151;
                margin-bottom: 4px;
            }

            .empty-state p {
                font-size: .85rem;
            }

            .filter-bar {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                align-items: center;
            }

            .filter-bar .input,
            .filter-bar .select,
            .filter-bar .select2-container {
                width: auto;
                min-width: 150px;
            }

            .filter-bar .input {
                padding: 7px 10px;
            }

            .detail-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                gap: 10px;
            }

            .detail-item {
                background: #f9fafb;
                border: 1px solid #f3f4f6;
                border-radius: 8px;
                padding: 9px 10px;
            }

            .detail-label {
                font-size: .72rem;
                color: var(--muted);
                font-weight: 700;
                display: flex;
                gap: 4px;
                align-items: center;
            }

            .detail-value {
                font-size: .89rem;
                font-weight: 700;
                color: #111827;
                word-break: break-word;
            }

            .chat {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .message {
                max-width: 84%;
                padding: 11px 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #fff;
            }

            .message.mine {
                align-self: flex-start;
                background: #eff6ff;
                border-color: #bfdbfe;
            }

            .message.other {
                align-self: flex-end;
                background: #fff;
            }

            .message-meta {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                font-size: .71rem;
                color: var(--muted);
                margin-bottom: 5px;
                flex-wrap: wrap;
            }

            .message-body {
                white-space: pre-wrap;
                font-size: .9rem;
            }

            .attachments {
                display: flex;
                gap: 7px;
                flex-wrap: wrap;
                margin-top: 9px;
            }

            .attachments img {
                width: 62px;
                height: 62px;
                object-fit: cover;
                border-radius: 7px;
                border: 1px solid var(--border);
            }

            .modal-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .5);
                z-index: 100;
                align-items: center;
                justify-content: center;
                padding: 16px;
            }

            .modal-backdrop.show {
                display: flex;
            }

            .modal {
                width: 100%;
                max-width: 480px;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 25px 60px rgba(0, 0, 0, .22);
            }

            .modal-header {
                padding: 13px 14px;
                border-bottom: 1px solid #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }

            .modal-body {
                padding: 14px;
            }

            .modal-footer {
                padding: 12px 14px;
                border-top: 1px solid #f3f4f6;
                display: flex;
                gap: 8px;
                justify-content: flex-start;
            }

            .pagination {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
                margin-top: 16px;
                flex-wrap: wrap;
            }

            .page-link {
                min-width: 32px;
                height: 32px;
                padding: 0 7px;
                border-radius: 7px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fff;
                border: 1px solid var(--border);
                color: #374151;
                font-size: .8rem;
                font-weight: 700;
            }

            .page-link.active {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
            }

            .page-link.disabled {
                opacity: .45;
                pointer-events: none;
            }

            .thumb {
                width: 40px;
                height: 40px;
                object-fit: cover;
                border-radius: 7px;
                border: 1px solid var(--border);
                background: #f9fafb;
            }

            .thumb-placeholder {
                width: 40px;
                height: 40px;
                border-radius: 7px;
                background: #f3f4f6;
                color: #9ca3af;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .action-cluster {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                align-items: center;
            }

            .divider {
                height: 1px;
                background: #f3f4f6;
                margin: 14px 0;
            }

            .text-muted {
                color: var(--muted);
            }

            .text-sm {
                font-size: .82rem;
            }

            .mt-1 {
                margin-top: 6px;
            }

            .mt-2 {
                margin-top: 10px;
            }

            .mt-3 {
                margin-top: 16px;
            }

            .mb-1 {
                margin-bottom: 6px;
            }

            .mb-2 {
                margin-bottom: 10px;
            }

            .mb-3 {
                margin-bottom: 16px;
            }

            .flex {
                display: flex;
            }

            .items-center {
                align-items: center;
            }

            .justify-between {
                justify-content: space-between;
            }

            .flex-wrap {
                flex-wrap: wrap;
            }

            .gap-1 {
                gap: 6px;
            }

            .gap-2 {
                gap: 10px;
            }

            .mini-card {
                background: #f9fafb;
                border: 1px solid #f3f4f6;
                border-radius: 8px;
                padding: 10px;
            }

            .impersonation-banner {
                background: #fffbeb;
                border-bottom: 1px solid #fde68a;
                color: #92400e;
                padding: 8px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                font-size: .83rem;
                font-weight: 700;
                flex-wrap: wrap;
            }

            .order-line {
                display: grid;
                grid-template-columns: 1fr 100px 40px;
                gap: 7px;
                align-items: center;
                margin-bottom: 8px;
            }

            .order-total-box {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                color: #1e40af;
                padding: 9px 11px;
                border-radius: 8px;
                font-weight: 800;
                display: flex;
                justify-content: space-between;
                gap: 10px;
                flex-wrap: wrap;
            }

            .bottom-nav {
                display: none;
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                background: #fff;
                border-top: 1px solid var(--border);
                z-index: 45;
            }

            .bottom-nav .inner {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(58px, 1fr));
            }

            .bottom-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                padding: 7px 4px;
                color: #6b7280;
                font-size: .64rem;
                font-weight: 700;
            }

            .bottom-link.active {
                color: var(--primary);
            }

            .tip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 15px;
                height: 15px;
                border-radius: 99px;
                background: #e5e7eb;
                color: #4b5563;
                font-size: .62rem;
                font-weight: 900;
                cursor: help;
                position: relative;
                flex-shrink: 0;
            }

            .tip:hover::after,
            .tip:focus::after {
                content: attr(data-tip);
                position: absolute;
                bottom: calc(100% + 6px);
                right: 50%;
                transform: translateX(50%);
                background: #111827;
                color: #fff;
                font-size: .68rem;
                font-weight: 600;
                line-height: 1.6;
                padding: 6px 8px;
                border-radius: 6px;
                width: max-content;
                max-width: 230px;
                z-index: 1000;
                box-shadow: 0 10px 24px rgba(0, 0, 0, .18);
            }

            .datepicker {
                position: absolute;
                z-index: 2000;
                width: 270px;
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
                padding: 12px;
            }

            .dp-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                margin-bottom: 10px;
            }

            .dp-head select {
                border: 1px solid #e5e7eb;
                border-radius: 7px;
                padding: 4px 6px;
                font-size: .78rem;
                background: #fff;
            }

            .dp-nav {
                width: 28px;
                height: 28px;
                border: 1px solid #e5e7eb;
                background: #fff;
                border-radius: 7px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .dp-nav:hover {
                background: #f9fafb;
            }

            .dp-weekdays,
            .dp-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 3px;
                text-align: center;
            }

            .dp-weekdays div {
                font-size: .67rem;
                font-weight: 800;
                color: #6b7280;
                padding: 5px 0;
            }

            .dp-day {
                border: none;
                background: transparent;
                border-radius: 7px;
                height: 30px;
                font-size: .8rem;
                color: #111827;
            }

            .dp-day:hover {
                background: #f3f4f6;
            }

            .dp-day.today {
                border: 1px solid #93c5fd;
            }

            .dp-day.selected {
                background: #1d4ed8;
                color: #fff;
            }

            .dp-day.blank {
                visibility: hidden;
            }

            .dp-actions {
                display: flex;
                gap: 6px;
                margin-top: 10px;
            }

            .dp-actions button {
                flex: 1;
                border: 1px solid #e5e7eb;
                background: #fff;
                border-radius: 7px;
                padding: 5px 6px;
                font-size: .74rem;
                font-weight: 700;
            }

            .dp-actions button:hover {
                background: #f9fafb;
            }

            .file-upload {
                border: 1.5px dashed #d1d5db;
                border-radius: 10px;
                background: #f9fafb;
                padding: 12px;
            }

            .file-upload.drag {
                border-color: #2563eb;
                background: #eff6ff;
            }

            .file-input-hidden {
                position: absolute;
                width: 1px;
                height: 1px;
                opacity: 0;
                overflow: hidden;
            }

            .file-upload-button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #fff;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 7px 11px;
                font-size: .83rem;
                font-weight: 700;
                color: #374151;
            }

            .file-upload-button:hover {
                background: #f9fafb;
            }

            .file-list {
                margin-top: 10px;
                display: grid;
                gap: 8px;
            }

            .file-item {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 7px 9px;
                font-size: .8rem;
            }

            .file-item img {
                width: 40px;
                height: 40px;
                object-fit: cover;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
            }

            .file-hint {
                color: #6b7280;
                font-size: .78rem;
            }

            @media (max-width: 960px) {
                .sidebar {
                    transform: translateX(100%);
                }

                .sidebar.open {
                    transform: translateX(0);
                }

                .sidebar-backdrop.show {
                    display: block;
                }

                .main {
                    margin-right: 0;
                }

                .mobile-toggle {
                    display: inline-flex;
                }

                .bottom-nav {
                    display: block;
                }

                body {
                    padding-bottom: 68px;
                }

                .form-grid {
                    grid-template-columns: 1fr;
                }

                .order-line {
                    grid-template-columns: 1fr 86px 38px;
                }
            }

            @media (max-width: 960px) and (min-width: 521px) {
                .content {
                    max-width: 720px;
                }
            }

            @media (max-width: 520px) {
                .content {
                    max-width: 520px;
                    padding: 12px;
                }
                .card-body {
                    padding: 14px;
                }
                .topbar {
                    padding: 8px 12px;
                }
            }

            @media print {

                .sidebar,
                .topbar,
                .bottom-nav,
                .no-print,
                .sidebar-backdrop {
                    display: none !important;
                }

                .main {
                    margin: 0 !important;
                }

                .content {
                    max-width: none;
                    padding: 0;
                }

                body {
                    background: #fff;
                }

                .card {
                    box-shadow: none;
                    border: none;
                }

                a {
                    color: inherit;
                }
            }
        </style>
    </head>

    <body>
        <div class="app">
            <aside class="sidebar" id="sidebar">
                <div class="brand">
                    <div class="brand-icon"><?= icon('orders', 17) ?></div>
                    <div>
                        <div class="brand-title">سامانه سفارشات</div>
                        <div class="brand-sub">نسخه سازمانی</div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="nav-label">منوی اصلی</div>
                    <?php foreach ($navItems as $item): ?>
                        <?php $badge = $getBadge($item['url']); ?>
                        <a class="nav-link <?= $isActive($item['url']) ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
                            <?= icon($item['icon'], 16) ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if ($badge > 0): ?><span
                                    class="nav-badge"><?= en_to_fa_digits((string) $badge) ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-footer">
                    <div class="user-box">
                        <div class="avatar"><?= e(mb_substr($user['nickname'] ?? 'ک', 0, 1)) ?></div>
                        <div>
                            <div class="user-name"><?= e($user['nickname']) ?></div>
                            <div class="user-role"><?= e(role_fa($role)) ?></div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

            <div class="main">
                <header class="topbar">
                    <div class="flex items-center gap-2">
                        <button class="mobile-toggle" id="sidebarToggle" type="button"
                            aria-label="باز کردن منو"><?= icon('menu', 16) ?></button>
                        <div class="topbar-title"><?= e($title) ?></div>
                    </div>

                    <div class="topbar-actions">
                        <?php if ($role === 'superadmin'): ?>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span style="font-size:0.75rem; color:var(--muted); font-weight:bold;">فروشگاه فعال:</span>
                                <select class="select" onchange="if(this.value) location.href='/shops/' + this.value + '/switch'" style="padding:3px 8px; font-size:0.8rem; height:32px; width:auto; min-width:140px;">
                                    <?php foreach (all_active_shops() as $sh): ?>
                                        <option value="<?= (int)$sh['id'] ?>" <?= (int)$shopId === (int)$sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($role === 'admin' && $currentShop): ?>
                            <span style="background:#eff6ff; color:#1d4ed8; padding:4px 10px; border-radius:6px; font-size:0.8rem; font-weight:bold; border:1px solid #bfdbfe;">
                                <?= e($currentShop['name']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($role === 'customer'): ?>
                            <a class="btn btn-primary btn-sm" href="/orders/create"><?= icon('plus', 13) ?> سفارش جدید</a>
                        <?php elseif (in_array($role, ['admin', 'superadmin'], true)): ?>
                            <a class="btn btn-primary btn-sm" href="/orders/create"><?= icon('plus', 13) ?> ثبت سفارش</a>
                        <?php endif; ?>

                        <form method="post" action="/logout" class="inline-form">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm"><?= icon('logout', 13) ?> خروج</button>
                        </form>
                    </div>
                </header>

                <?php if (!empty($user['is_impersonated'])): ?>
                    <div class="impersonation-banner">
                        <span>شما به عنوان «<?= e($user['nickname']) ?>» وارد شده‌اید.</span>
                        <form method="post" action="/stop-impersonation" class="inline-form">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm">بازگشت به حساب اصلی</button>
                        </form>
                    </div>
                <?php endif; ?>

                <main class="content">
                    <?php $flash = get_flash();
                    if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
                            <span><?= e($flash['message']) ?></span>
                            <button type="button" class="close" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endif; ?>
                    <?php
}

function layout_end(): void
{
    $navItems = $GLOBALS['navItems'] ?? [];
    $isActive = $GLOBALS['isActive'] ?? fn($x) => false;
    ?>
                </main>
            </div>
        </div>

        <nav class="bottom-nav">
            <div class="inner">
                <?php foreach (array_slice($navItems, 0, 5) as $item): ?>
                    <a class="bottom-link <?= $isActive($item['url']) ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
                        <?= icon($item['icon'], 16) ?>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

        <script src="/assets/vendor/qrcode.min.js"></script>
        <script src="/assets/vendor/barcode-scanner.js"></script>
        <script>
            const APP_TODAY_JALALI = '<?= e(jalali_today(false)) ?>';
            const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
            const JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.getElementById('sidebar');
                const backdrop = document.getElementById('sidebarBackdrop');
                const toggle = document.getElementById('sidebarToggle');

                if (toggle) {
                    toggle.addEventListener('click', function () {
                        sidebar.classList.toggle('open');
                        backdrop.classList.toggle('show');
                    });
                }

                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        sidebar.classList.remove('open');
                        backdrop.classList.remove('show');
                    });
                }

                const flash = document.getElementById('flashAlert');
                if (flash) setTimeout(() => flash.remove(), 5000);

                document.addEventListener('click', function (e) {
                    const opener = e.target.closest('[data-modal]');
                    if (opener) {
                        e.preventDefault();
                        const modal = document.querySelector(opener.getAttribute('data-modal'));
                        if (modal) modal.classList.add('show');
                    }

                    const closer = e.target.closest('[data-modal-close]');
                    if (closer) {
                        const modal = closer.closest('.modal-backdrop');
                        if (modal) modal.classList.remove('show');
                    }

                    if (e.target.classList.contains('modal-backdrop')) {
                        e.target.classList.remove('show');
                    }
                });

                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (form.hasAttribute('data-confirm') && !confirm(form.getAttribute('data-confirm'))) {
                        e.preventDefault();
                    }
                });

                initSelect2();
                enhanceFileInputs();
                initDatePicker();
                initOrderForm();
            });

            function initSelect2(scope) {
                if (!window.jQuery || !jQuery.fn.select2) return;

                jQuery(scope || document).find('select.select').each(function () {
                    const $el = jQuery(this);

                    if (!$el.data('select2')) {
                        $el.select2({
                            dir: 'rtl',
                            width: '100%',
                            allowClear: true,
                            placeholder: $el.attr('placeholder') || ''
                        });
                    }
                });
            }

            function faToEn(s) {
                return String(s).replace(/[۰-۹]/g, d => FA_DIGITS.indexOf(d).toString());
            }

            function enToFa(s) {
                return String(s).replace(/\d/g, d => FA_DIGITS[parseInt(d)]);
            }

            function pad2(n) {
                return n < 10 ? '0' + n : String(n);
            }

            function jalaliIsLeap(jy) {
                return [1, 5, 9, 13, 17, 22, 26, 30].indexOf(jy % 33) !== -1;
            }

            function jalaliMonthDays(jy, jm) {
                if (jm <= 6) return 31;
                if (jm <= 11) return 30;
                return jalaliIsLeap(jy) ? 30 : 29;
            }

            function jalaliToGregorian(jy, jm, jd) {
                jy = parseInt(jy); jm = parseInt(jm); jd = parseInt(jd);
                jy += 1595;

                let days = -355668 + (365 * jy) + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4)
                    + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);

                let gy = 400 * Math.floor(days / 146097);
                days %= 146097;

                if (days > 36524) {
                    gy += 100 * Math.floor(--days / 36524);
                    days %= 36524;
                    if (days >= 365) days++;
                }

                gy += 4 * Math.floor(days / 1461);
                days %= 1461;

                if (days > 365) {
                    gy += Math.floor((days - 1) / 365);
                    days = (days - 1) % 365;
                }

                let gd = days + 1;
                const sal = [0, 31, ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

                let gm = 1;
                while (gm <= 12 && gd > sal[gm]) {
                    gd -= sal[gm];
                    gm++;
                }

                return [gy, gm, gd];
            }

            function gregorianToJalali(gy, gm, gd) {
                const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                const gy2 = (gm > 2) ? (gy + 1) : gy;

                let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
                    + Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];

                let jy = -1595 + (33 * Math.floor(days / 12053));
                days %= 12053;

                jy += 4 * Math.floor(days / 1461);
                days %= 1461;

                if (days > 365) {
                    jy += Math.floor((days - 1) / 365);
                    days = (days - 1) % 365;
                }

                const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
                const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));

                return [jy, jm, jd];
            }

            function parseJalaliValue(value) {
                const parts = faToEn(value || '').split('/');
                if (parts.length !== 3) return null;

                const jy = parseInt(parts[0], 10);
                const jm = parseInt(parts[1], 10);
                const jd = parseInt(parts[2], 10);

                if (!jy || !jm || !jd) return null;
                if (jm < 1 || jm > 12) return null;
                if (jd < 1 || jd > jalaliMonthDays(jy, jm)) return null;

                return [jy, jm, jd];
            }

            let activeDatePicker = null;

            function closeDatePicker() {
                if (activeDatePicker && activeDatePicker.dp && activeDatePicker.dp.parentNode) {
                    activeDatePicker.dp.parentNode.removeChild(activeDatePicker.dp);
                }

                activeDatePicker = null;
            }

            function initDatePicker() {
                document.querySelectorAll('.jalali-date').forEach(function (input) {
                    input.setAttribute('inputmode', 'numeric');
                    if (!input.placeholder) input.placeholder = '۱۴۰۴/۰۱/۰۱';

                    input.addEventListener('focus', function () {
                        openDatePicker(input);
                    });

                    input.addEventListener('click', function () {
                        openDatePicker(input);
                    });
                });

                document.addEventListener('mousedown', function (e) {
                    if (!activeDatePicker) return;
                    if (e.target.closest('.datepicker') || e.target === activeDatePicker.input) return;
                    closeDatePicker();
                });

                window.addEventListener('resize', closeDatePicker);
                window.addEventListener('scroll', closeDatePicker, true);
            }

            function openDatePicker(input) {
                closeDatePicker();

                const parsed = parseJalaliValue(input.value);
                const today = APP_TODAY_JALALI.split('/').map(Number);

                const jy = parsed ? parsed[0] : today[0];
                const jm = parsed ? parsed[1] : today[1];

                const dp = document.createElement('div');
                dp.className = 'datepicker';
                document.body.appendChild(dp);

                dp.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                });

                activeDatePicker = { input, dp, jy, jm, selected: parsed };

                renderDatePicker();
                positionDatePicker();
            }

            function positionDatePicker() {
                if (!activeDatePicker) return;

                const rect = activeDatePicker.input.getBoundingClientRect();

                activeDatePicker.dp.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                activeDatePicker.dp.style.right = (document.documentElement.clientWidth - rect.right) + 'px';
            }

            function renderDatePicker() {
                if (!activeDatePicker) return;

                const { dp, jy, jm, input, selected } = activeDatePicker;
                const today = APP_TODAY_JALALI.split('/').map(Number);

                let monthOptions = '';
                for (let i = 1; i <= 12; i++) {
                    monthOptions += `<option value="${i}" ${i == jm ? 'selected' : ''}>${JALALI_MONTHS[i - 1]}</option>`;
                }

                let yearOptions = '';
                const minYear = today[0] - 30;
                const maxYear = today[0] + 5;

                for (let i = minYear; i <= maxYear; i++) {
                    yearOptions += `<option value="${i}" ${i == jy ? 'selected' : ''}>${enToFa(i)}</option>`;
                }

                const firstGreg = jalaliToGregorian(jy, jm, 1);
                const firstDate = new Date(firstGreg[0], firstGreg[1] - 1, firstGreg[2]);
                const offset = (firstDate.getDay() + 1) % 7;
                const daysInMonth = jalaliMonthDays(jy, jm);

                let daysHtml = '';

                for (let i = 0; i < offset; i++) {
                    daysHtml += '<button type="button" class="dp-day blank"></button>';
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    let cls = 'dp-day';

                    if (jy == today[0] && jm == today[1] && day == today[2]) cls += ' today';
                    if (selected && selected[0] == jy && selected[1] == jm && selected[2] == day) cls += ' selected';

                    daysHtml += `<button type="button" class="${cls}" data-day="${day}">${enToFa(day)}</button>`;
                }

                dp.innerHTML = `
        <div class="dp-head">
            <button type="button" class="dp-nav" data-dir="prev">‹</button>
            <div class="flex gap-1">
                <select data-dp="month">${monthOptions}</select>
                <select data-dp="year">${yearOptions}</select>
            </div>
            <button type="button" class="dp-nav" data-dir="next">›</button>
        </div>
        <div class="dp-weekdays">
            <div>ش</div><div>ی</div><div>د</div><div>س</div><div>چ</div><div>پ</div><div>ج</div>
        </div>
        <div class="dp-grid">${daysHtml}</div>
        <div class="dp-actions">
            <button type="button" data-action="today">امروز</button>
            <button type="button" data-action="clear">خالی</button>
        </div>
    `;

                dp.querySelectorAll('[data-dir]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        let { jy, jm } = activeDatePicker;

                        if (this.dataset.dir === 'prev') {
                            jm--;
                            if (jm < 1) { jm = 12; jy--; }
                        } else {
                            jm++;
                            if (jm > 12) { jm = 1; jy++; }
                        }

                        activeDatePicker.jy = jy;
                        activeDatePicker.jm = jm;
                        renderDatePicker();
                    });
                });

                const monthSelect = dp.querySelector('[data-dp="month"]');
                const yearSelect = dp.querySelector('[data-dp="year"]');

                monthSelect.addEventListener('change', function () {
                    activeDatePicker.jm = parseInt(this.value, 10);
                    renderDatePicker();
                });

                yearSelect.addEventListener('change', function () {
                    activeDatePicker.jy = parseInt(this.value, 10);
                    renderDatePicker();
                });

                dp.querySelectorAll('.dp-day:not(.blank)').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const day = parseInt(this.dataset.day, 10);
                        const value = activeDatePicker.jy + '/' + pad2(activeDatePicker.jm) + '/' + pad2(day);
                        activeDatePicker.input.value = enToFa(value);
                        closeDatePicker();
                    });
                });

                dp.querySelector('[data-action="today"]').addEventListener('click', function () {
                    activeDatePicker.input.value = enToFa(APP_TODAY_JALALI);
                    closeDatePicker();
                });

                dp.querySelector('[data-action="clear"]').addEventListener('click', function () {
                    activeDatePicker.input.value = '';
                    closeDatePicker();
                });
            }

            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' بایت';
                if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' کیلوبایت';
                return (bytes / 1048576).toFixed(1) + ' مگابایت';
            }

            function enhanceFileInputs() {
                document.querySelectorAll('input[type=file]').forEach(function (input) {
                    if (input.dataset.enhanced) return;

                    input.dataset.enhanced = '1';
                    input.classList.add('file-input-hidden');

                    const wrapper = document.createElement('div');
                    wrapper.className = 'file-upload';

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'file-upload-button';
                    button.innerHTML = 'انتخاب فایل';

                    const list = document.createElement('div');
                    list.className = 'file-list';

                    input.parentNode.insertBefore(wrapper, input);
                    wrapper.appendChild(input);
                    wrapper.appendChild(button);
                    wrapper.appendChild(list);

                    button.addEventListener('click', function () {
                        input.click();
                    });

                    wrapper.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        wrapper.classList.add('drag');
                    });

                    wrapper.addEventListener('dragleave', function () {
                        wrapper.classList.remove('drag');
                    });

                    wrapper.addEventListener('drop', function (e) {
                        e.preventDefault();
                        wrapper.classList.remove('drag');

                        if (e.dataTransfer.files.length) {
                            input.files = e.dataTransfer.files;
                            renderFiles();
                        }
                    });

                    input.addEventListener('change', renderFiles);

                    function renderFiles() {
                        list.innerHTML = '';

                        if (!input.files.length) {
                            list.innerHTML = '<div class="file-hint">هیچ فایلی انتخاب نشده است.</div>';
                            return;
                        }

                        Array.from(input.files).forEach(function (file) {
                            const item = document.createElement('div');
                            item.className = 'file-item';

                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                item.appendChild(img);
                            }

                            const info = document.createElement('span');
                            info.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                            item.appendChild(info);

                            list.appendChild(item);
                        });
                    }

                    renderFiles();
                });
            }

            function formatMoney(amount) {
                return new Intl.NumberFormat('fa-IR').format(amount || 0) + ' ریال';
            }

            function escHtml(s) {
                return String(s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function initOrderForm() {
                const linesEl = document.getElementById('order-lines');
                const productsEl = document.getElementById('products-data');
                const addBtn = document.getElementById('add-line');

                if (!linesEl || !productsEl) return;

                const products = JSON.parse(productsEl.textContent);
                const initialItemsEl = document.getElementById('items-data');
                const initialItems = initialItemsEl ? JSON.parse(initialItemsEl.textContent) : [];

                function productOptions(selectedId) {
                    let html = '<option value="">انتخاب محصول</option>';

                    products.forEach(function (p) {
                        html += '<option value="' + p.id + '" data-price="' + p.price + '"' + (selectedId == p.id ? ' selected' : '') + '>' +
                            escHtml(p.title) + ' — ' + formatMoney(p.price) +
                            '</option>';
                    });

                    return html;
                }

                function updateTotal() {
                    let total = 0;

                    linesEl.querySelectorAll('.order-line').forEach(function (line) {
                        const select = line.querySelector('.product-select');
                        const qty = parseFloat(line.querySelector('.qty').value || 0);
                        const price = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
                        total += price * qty;
                    });

                    const totalEl = document.getElementById('order-total');
                    if (totalEl) totalEl.textContent = formatMoney(total);
                }

                function addLine(item) {
                    item = item || { product_id: '', quantity: 1 };

                    const line = document.createElement('div');
                    line.className = 'order-line';
                    line.innerHTML =
                        '<select name="product_id[]" class="select product-select" required>' + productOptions(item.product_id) + '</select>' +
                        '<input type="number" name="quantity[]" class="input qty" min="0.1" step="0.1" value="' + item.quantity + '" required>' +
                        '<button type="button" class="btn btn-danger btn-sm remove-line" title="حذف">×</button>';

                    linesEl.appendChild(line);
                    initSelect2(line);
                    updateTotal();
                }

                linesEl.addEventListener('click', function (e) {
                    const btn = e.target.closest('.remove-line');
                    if (!btn) return;

                    const line = btn.closest('.order-line');

                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery(line).find('.product-select').select2('destroy');
                    }

                    line.remove();
                    updateTotal();
                });

                linesEl.addEventListener('input', updateTotal);
                linesEl.addEventListener('change', updateTotal);

                if (window.jQuery && jQuery.fn.select2) {
                    jQuery(document).on('select2:select', '.product-select', function () {
                        updateTotal();
                    });
                }

                if (addBtn) addBtn.addEventListener('click', function () { addLine(); });

                if (initialItems.length) {
                    initialItems.forEach(addLine);
                } else {
                    addLine();
                }
            }
        </script>
    </body>

    </html>
    <?php
}