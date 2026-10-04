<?php
declare(strict_types=1);

/**
 * routes_users_edit.php
 * Staff User Creation, Modification, and Role Elevation
 */

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
