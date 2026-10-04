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

// Create Staff User (Superadmin only)
route('GET|POST', '/admins/create', ['superadmin'], function () use ($pdo) {
    $user = require_roles(['superadmin']);
    $error = '';
    $shops = all_active_shops();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $phone = normalize_phone(trim($_POST['phone'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($firstName . ' ' . $lastName) ?: trim($_POST['nickname'] ?? '');
        $username = trim($_POST['username'] ?? '') ?: ($phone ?: 'admin_' . uniqid());
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'shop_manager');
        $shopId = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;

        if (!$phone) {
            $error = 'شماره تلفن همراه معتبر ۱۰ رقمی شروع شونده با ۹ الزامی است.';
        } elseif (!$password || strlen($password) < 6) {
            $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        } elseif (!in_array($role, ['superadmin', 'admin', 'shop_owner', 'shop_manager'], true)) {
            $error = 'نقش کاربری نامعتبر است.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, nickname, first_name, last_name, role, shop_id, phone, active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
                ");
                $stmt->execute([$username, $hash, $nickname, $firstName, $lastName, $role, $shopId, $phone]);

                flash('success', 'حساب کاربری جدید با موفقیت ایجاد شد.');
                redirect('/admins');
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'نام کاربری یا شماره موبایل تکراری است.' : 'خطا در ثبت کاربر: ' . $e->getMessage();
            }
        }
    }

    layout_start('افزودن مدیر جدید', $user);
    ?>
    <div class="page-header">
        <h1>تعریف مدیر / عامل جدید</h1>
        <a class="btn btn-outline" href="/admins">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" style="max-width:640px;">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>شماره موبایل (۱۰ رقم) *</label>
                        <input class="input" name="phone" dir="ltr" placeholder="9123456789" required>
                    </div>

                    <div class="form-group">
                        <label>نام کاربری</label>
                        <input class="input" name="username" dir="ltr" placeholder="اختیاری">
                    </div>

                    <div class="form-group">
                        <label>نام</label>
                        <input class="input" name="first_name" required>
                    </div>

                    <div class="form-group">
                        <label>نام خانوادگی</label>
                        <input class="input" name="last_name" required>
                    </div>

                    <div class="form-group">
                        <label>رمز عبور *</label>
                        <input class="input" type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label>نقش سازمانی *</label>
                        <select class="select" name="role" required>
                            <option value="shop_manager">مدیر فروشگاه (shop_manager)</option>
                            <option value="shop_owner">مالک فروشگاه (shop_owner)</option>
                            <option value="admin">مدیر کل سامانه (admin)</option>
                            <option value="superadmin">مدیر ارشد (superadmin)</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label>فروشگاه منتسب</label>
                        <select class="select" name="shop_id">
                            <option value="">سراسری (بدون فروشگاه خاص)</option>
                            <?php foreach ($shops as $sh): ?>
                                <option value="<?= (int)$sh['id'] ?>"><?= e($sh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ایجاد حساب</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

// Edit Staff User & Role Elevation
route('GET|POST', '/admins/(\d+)/edit', ['admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) redirect('/admins');

    // Admin cannot edit other superadmins or admins
    if ($user['role'] === 'admin' && in_array($target['role'], ['superadmin', 'admin'], true)) {
        error_page(403, 'دسترسی غیرمجاز', 'مدیران سطح ۲ امکان تغییر دسترسی مدیران ارشد یا مدیران کل دیگر را ندارند.');
    }

    $shops = all_active_shops();
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $phone = normalize_phone(trim($_POST['phone'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($firstName . ' ' . $lastName) ?: trim($_POST['nickname'] ?? $target['nickname']);
        $newRole = trim($_POST['role'] ?? $target['role']);
        $shopId = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        // Restriction check for admin role
        if ($user['role'] === 'admin') {
            if (in_array($newRole, ['superadmin', 'admin'], true)) {
                $error = 'شما مجاز به انتصاب نقش مدیر ارشد یا مدیر کل نمی‌باشید.';
            }
        }

        if (!$error) {
            try {
                $pdo->prepare("
                    UPDATE users 
                    SET phone = ?, first_name = ?, last_name = ?, nickname = ?, role = ?, shop_id = ?, active = ?, updated_at = datetime('now')
                    WHERE id = ?
                ")->execute([$phone, $firstName, $lastName, $nickname, $newRole, $shopId, $active, $id]);

                if (!empty($_POST['new_password'])) {
                    $newPass = $_POST['new_password'];
                    if (strlen($newPass) >= 6) {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $id]);
                    }
                }

                flash('success', 'مشخصات و نقش کاربر با موفقیت ذخیره شد.');
                redirect('/admins');
            } catch (Throwable $e) {
                $error = 'خطا در ذخیره تغییرات: ' . $e->getMessage();
            }
        }
    }

    layout_start('ویرایش عامل سازمانی', $user);
    ?>
    <div class="page-header">
        <h1>ویرایش کاربر: <?= e($target['nickname']) ?></h1>
        <a class="btn btn-outline" href="/admins">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" style="max-width:640px;">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>شماره موبایل (۱۰ رقم) *</label>
                        <input class="input" name="phone" dir="ltr" value="<?= e($target['phone'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>نام</label>
                        <input class="input" name="first_name" value="<?= e($target['first_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>نام خانوادگی</label>
                        <input class="input" name="last_name" value="<?= e($target['last_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>نقش کاربری *</label>
                        <select class="select" name="role">
                            <option value="customer" <?= $target['role'] === 'customer' ? 'selected' : '' ?>>مشتری (customer)</option>
                            <option value="shop_manager" <?= $target['role'] === 'shop_manager' ? 'selected' : '' ?>>مدیر فروشگاه (shop_manager)</option>
                            <option value="shop_owner" <?= $target['role'] === 'shop_owner' ? 'selected' : '' ?>>مالک فروشگاه (shop_owner)</option>
                            <?php if ($user['role'] === 'superadmin'): ?>
                                <option value="admin" <?= $target['role'] === 'admin' ? 'selected' : '' ?>>مدیر کل سامانه (admin)</option>
                                <option value="superadmin" <?= $target['role'] === 'superadmin' ? 'selected' : '' ?>>مدیر ارشد (superadmin)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>فروشگاه منتسب</label>
                        <select class="select" name="shop_id">
                            <option value="">سراسری / نامشخص</option>
                            <?php foreach ($shops as $sh): ?>
                                <option value="<?= (int)$sh['id'] ?>" <?= (int)$target['shop_id'] === (int)$sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>رمز عبور جدید (اختیاری)</label>
                        <input class="input" type="password" name="new_password">
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-check">
                            <input type="checkbox" name="active" value="1" <?= $target['active'] ? 'checked' : '' ?>>
                            <span>حساب کاربری فعال باشد</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ذخیره تغییرات</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

// Role Elevation by Shop Owner (elevates an existing customer to shop_manager for their shop)
route('POST', '/shop/elevate-manager', ['shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    verify_csrf_or_die();

    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $phone = normalize_phone(trim($_POST['phone'] ?? ''));

    if (!$phone) {
        flash('error', 'شماره موبایل ۱۰ رقمی کاربر الزامی است.');
        redirect('/shop/settings');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND deleted_at IS NULL");
    $stmt->execute([$phone]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        flash('error', 'کاربری با این شماره موبایل در سامانه ثبت نام نکرده است. لطفاً ابتدا از کاربر بخواهید ثبت نام نماید.');
        redirect('/shop/settings');
    }

    if ($target['role'] === 'superadmin' || $target['role'] === 'admin') {
        flash('error', 'امکان تغییر دسترسی مدیران ارشد سیستم وجود ندارد.');
        redirect('/shop/settings');
    }

    $pdo->prepare("UPDATE users SET role = 'shop_manager', shop_id = ?, updated_at = datetime('now') WHERE id = ?")
        ->execute([$shopId, $target['id']]);

    flash('success', "کاربر «{$target['nickname']}» با موفقیت به عنوان مدیر داخلی این فروشگاه منصوب گردید.");
    redirect('/shop/settings');
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
