<?php
declare(strict_types=1);

/**
 * routes_users.php
 * Platform Administrative Staff, Role Elevation, Impersonation, and Platform Retention Settings
 */

// Staff / Users Management
route('GET', '/admins(?:\.php)?', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);

    $search = trim($_GET['search'] ?? '');
    $roleFilter = $_GET['role'] ?? '';
    $shopFilter = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    $where = "WHERE role IN ('superadmin', 'admin', 'shop_owner', 'shop_manager') AND deleted_at IS NULL";
    $params = [];

    if ($search !== '') {
        $where .= " AND (username LIKE ? OR nickname LIKE ? OR phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($roleFilter !== '') {
        $where .= " AND role = ?";
        $params[] = $roleFilter;
    }

    if ($shopFilter > 0) {
        $where .= " AND shop_id = ?";
        $params[] = $shopFilter;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT u.*, s.name AS shop_name 
        FROM users u 
        LEFT JOIN shops s ON s.id = u.shop_id 
        $where 
        ORDER BY u.id DESC 
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $shops = all_active_shops();

    layout_start('مدیران و عوامل سامانه', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('shield', 18) ?></div>
            <div>
                <h1>مدیریت عوامل و مدیران سامانه</h1>
                <div class="page-sub">فهرست مدیران کل، مالکان فروشگاه‌ها و مدیران داخلی شعب</div>
            </div>
        </div>
        <?php if ($user['role'] === 'superadmin'): ?>
            <a class="btn btn-primary" href="/admins/create"><?= icon('plus', 14) ?> افزودن مدیر جدید</a>
        <?php endif; ?>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجوی نام، نام کاربری، موبایل..." value="<?= e($search) ?>" style="min-width:240px;">

                <select class="select" name="role">
                    <option value="">همه نقش‌ها</option>
                    <?php if ($user['role'] === 'superadmin'): ?>
                        <option value="superadmin" <?= $roleFilter === 'superadmin' ? 'selected' : '' ?>>مدیر ارشد سامانه</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>مدیر کل سامانه (سطح ۲)</option>
                    <?php endif; ?>
                    <option value="shop_owner" <?= $roleFilter === 'shop_owner' ? 'selected' : '' ?>>مالک فروشگاه</option>
                    <option value="shop_manager" <?= $roleFilter === 'shop_manager' ? 'selected' : '' ?>>مدیر فروشگاه</option>
                </select>

                <select class="select" name="shop_id">
                    <option value="">همه فروشگاه‌ها</option>
                    <?php foreach ($shops as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $shopFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <?php if ($search || $roleFilter || $shopFilter): ?>
                    <a class="btn btn-ghost" href="/admins">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- STAFF TABLE -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کاربر</th>
                        <th>شماره موبایل</th>
                        <th>نام کاربری</th>
                        <th>نقش کاربری</th>
                        <th>فروشگاه منتسب</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="8"><?= empty_state('کاربری یافت نشد') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($staff as $s): ?>
                        <?php
                        $isSuper = ($s['role'] === 'superadmin');
                        $isAdmin = ($s['role'] === 'admin');
                        $canEdit = ($user['role'] === 'superadmin') || (!$isSuper && !$isAdmin);
                        ?>
                        <tr>
                            <td>#<?= en_to_fa_digits((string)$s['id']) ?></td>
                            <td><strong><?= e($s['nickname']) ?></strong></td>
                            <td dir="ltr"><?= format_phone($s['phone']) ?></td>
                            <td><code><?= e($s['username']) ?></code></td>
                            <td><span class="badge badge-blue"><?= e(role_fa($s['role'])) ?></span></td>
                            <td><?= e($s['shop_name'] ?: 'سراسری') ?></td>
                            <td>
                                <?= $s['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-rose">غیرفعال</span>' ?>
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <?php if ($canEdit): ?>
                                        <a class="btn btn-outline btn-sm" href="/admins/<?= (int)$s['id'] ?>/edit"><?= icon('edit', 13) ?></a>
                                    <?php endif; ?>

                                    <!-- Impersonate Button (for superadmin and admin) -->
                                    <?php if ((int)$s['id'] !== (int)$user['id']): ?>
                                        <form method="post" action="/impersonate/<?= (int)$s['id'] ?>" class="inline-form" data-confirm="آیا مایل به ورود به حساب «<?= e($s['nickname']) ?>» هستید؟">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm" title="ورود به عنوان این کاربر"><?= icon('user', 13) ?> ورود با نام</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/admins', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

// Impersonation for superadmin and admin
route('POST', '/impersonate/(\d+)', [], function ($targetId) use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    if (!can_impersonate($user)) {
        error_page(403, 'دسترسی غیرمجاز', 'تنها مدیران ارشد و مدیران کل سامانه امکان ورود با نام کاربران دیگر را دارند.');
    }

    $targetId = (int)$targetId;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        flash('error', 'کاربر مورد نظر یافت نشد.');
        redirect('/');
    }

    // Save original user id if not already impersonating
    if (empty($_SESSION['impersonator_id'])) {
        $_SESSION['impersonator_id'] = (int)$user['id'];
    }

    $_SESSION['user_id'] = (int)$target['id'];
    $_SESSION['is_impersonated'] = true;

    flash('info', "شما اکنون به عنوان «{$target['nickname']}» وارد سامانه شده‌اید.");
    redirect('/');
});

// Stop impersonation
route('POST', '/stop-impersonation', [], function () {
    verify_csrf_or_die();

    if (!empty($_SESSION['impersonator_id'])) {
        $_SESSION['user_id'] = (int)$_SESSION['impersonator_id'];
        unset($_SESSION['impersonator_id'], $_SESSION['is_impersonated']);
        flash('success', 'به حساب کاربری اصلی خود بازگشتید.');
    }

    redirect('/');
});

// Retention Settings (Superadmin only)
route('GET|POST', '/settings/retention', ['superadmin'], function () use ($pdo) {
    $user = require_roles(['superadmin']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $days = max(1, (int)($_POST['cart_retention_days'] ?? 7));
        $resDays = max(1, (int)($_POST['reservation_days'] ?? 4));

        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('cart_retention_days', ?)")->execute([(string)$days]);
        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('default_reservation_days', ?)")->execute([(string)$resDays]);

        flash('success', 'تنظیمات نگهداری و انقضای داده‌ها ذخیره گردید.');
        redirect('/settings/retention');
    }

    $cartDays = (int)($pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'cart_retention_days'")->fetchColumn() ?: 7);
    $resDays = (int)($pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'default_reservation_days'")->fetchColumn() ?: 4);

    layout_start('تنظیمات نگهداری داده‌ها', $user);
    ?>
    <div class="page-header">
        <h1>تنظیمات دوره‌های زمانی و انقضای داده‌ها</h1>
    </div>

    <form method="post" style="max-width:540px;">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-group mb-3">
                    <label>مدت زمان نگهداری سبد خرید مشتریان (روز)</label>
                    <input class="input" type="number" name="cart_retention_days" value="<?= $cartDays ?>" min="1" required>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">سبد خریدهای بیش از این مدت به صورت خودکار منقضی و پاک می‌شوند (پیش‌فرض: ۷ روز).</div>
                </div>

                <div class="form-group">
                    <label>مدت زمان پیش‌فرض رزرو انبار برای سفارشات پرداخت‌نشده (روز)</label>
                    <input class="input" type="number" name="reservation_days" value="<?= $resDays ?>" min="1" required>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">پس از این مدت، در صورت عدم تایید پرداخت، سفارش لغو و کالا به انبار بازمی‌گردد (پیش‌فرض: ۴ روز).</div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ذخیره تنظیمات</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

require_once __DIR__ . '/routes_users_edit.php';
