<?php
declare(strict_types=1);

/**
 * routes_customers_form.php
 * Customer Create and Edit forms and actions
 */

// Customer Create
route('GET|POST', '/customers/create(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $phone = normalize_phone(trim($_POST['phone'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($firstName . ' ' . $lastName) ?: trim($_POST['nickname'] ?? '');
        $username = trim($_POST['username'] ?? '') ?: ($phone ?: 'user_' . uniqid());
        $password = $_POST['password'] ?? '';
        $nationalCode = trim(fa_to_en_digits($_POST['national_code'] ?? '')) ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $active = isset($_POST['active']) ? 1 : 0;

        // Address fields
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;

        if (!$phone) {
            $error = 'شماره تلفن همراه الزامی است و باید ۱۰ رقم شروع شونده با ۹ باشد (مثال: ۹۱۲۳۴۵۶۷۸۹).';
        } elseif (!$password || strlen($password) < 6) {
            $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        } elseif (!$state || !$city || !$addr) {
            $error = 'ثبت حداقل یک آدرس پیش‌فرض تحویل برای مشتری الزامی است.';
        } else {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $pdo->prepare("
                    INSERT INTO users (username, password, nickname, first_name, last_name, role, phone, national_code, notes, active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'customer', ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                $insUser->execute([$username, $hash, $nickname, $firstName, $lastName, $phone, $nationalCode, $notes, $active]);
                $newUserId = (int)$pdo->lastInsertId();

                $insAddr = $pdo->prepare("
                    INSERT INTO addresses (user_id, state, city, address, postal_code, is_default, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
                ");
                $insAddr->execute([$newUserId, $state, $city, $addr, $postal]);

                $pdo->commit();
                flash('success', 'حساب مشتری با موفقیت ایجاد شد.');
                redirect('/customers');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'شماره موبایل یا نام کاربری تکراری است.' : 'خطا در ثبت مشتری: ' . $e->getMessage();
            }
        }
    }

    layout_start('افزودن مشتری جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>افزودن مشتری جدید</h1>
                <div class="page-sub">تعریف حساب مشتری به همراه شماره موبایل ۱۰ رقمی و نشانی اولیه</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/customers">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات هویتی و تماس</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>شماره موبایل (۱۰ رقم، شروع با ۹) *</label>
                        <input class="input" name="phone" dir="ltr" placeholder="9123456789" value="<?= e($_POST['phone'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>نام</label>
                        <input class="input" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" placeholder="مثلاً: علی">
                    </div>

                    <div class="form-group">
                        <label>نام خانوادگی</label>
                        <input class="input" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" placeholder="مثلاً: محمدی">
                    </div>

                    <div class="form-group">
                        <label>کد ملی</label>
                        <input class="input" name="national_code" dir="ltr" placeholder="۱۰ رقم کد ملی" value="<?= e($_POST['national_code'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>رمز عبور *</label>
                        <input class="input" type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label>نام کاربری (اختیاری؛ پیش‌فرض موبایل)</label>
                        <input class="input" name="username" dir="ltr" value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>یادداشت داخلی</label>
                    <textarea class="textarea" name="notes"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>آدرس پیش‌فرض تحویل سفارش</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>استان *</label>
                        <input class="input" name="state" value="<?= e($_POST['state'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>شهر *</label>
                        <input class="input" name="city" value="<?= e($_POST['city'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>کد پستی ۱۰ رقمی</label>
                        <input class="input" name="postal_code" dir="ltr" value="<?= e($_POST['postal_code'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label>نشانی کامل پستی *</label>
                        <textarea class="textarea" name="address" required><?= e($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ایجاد حساب مشتری</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

// Customer Edit
route('GET|POST', '/customers/(\d+)/edit(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        error_page(404, 'مشتری یافت نشد', 'حساب مشتری مورد نظر وجود ندارد.');
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $phone = normalize_phone(trim($_POST['phone'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($firstName . ' ' . $lastName) ?: trim($_POST['nickname'] ?? $customer['nickname']);
        $nationalCode = trim(fa_to_en_digits($_POST['national_code'] ?? '')) ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$phone) {
            $error = 'شماره تلفن همراه معتبر ۱۰ رقمی شروع شونده با ۹ الزامی است.';
        } else {
            try {
                $upd = $pdo->prepare("
                    UPDATE users 
                    SET phone = ?, first_name = ?, last_name = ?, nickname = ?, national_code = ?, notes = ?, active = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $upd->execute([$phone, $firstName, $lastName, $nickname, $nationalCode, $notes, $active, $id]);

                // Reset password if provided
                if (!empty($_POST['new_password'])) {
                    $newPass = $_POST['new_password'];
                    if (strlen($newPass) >= 6) {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $id]);
                    }
                }

                flash('success', 'مشخصات مشتری به‌روزرسانی شد.');
                redirect('/customers');
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'شماره موبایل تکراری است.' : 'خطا در ویرایش: ' . $e->getMessage();
            }
        }
    }

    layout_start('ویرایش مشتری', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>ویرایش مشتری: <?= e($customer['nickname']) ?></h1>
                <div class="page-sub">شناسه: #<?= en_to_fa_digits((string)$id) ?></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/customers/<?= $id ?>/addresses">مدیریت آدرس‌ها</a>
            <a class="btn btn-outline" href="/customers">بازگشت</a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>مشخصات فردی</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>شماره موبایل (۱۰ رقم) *</label>
                        <input class="input" name="phone" dir="ltr" value="<?= e($customer['phone'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>نام</label>
                        <input class="input" name="first_name" value="<?= e($customer['first_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>نام خانوادگی</label>
                        <input class="input" name="last_name" value="<?= e($customer['last_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>کد ملی</label>
                        <input class="input" name="national_code" dir="ltr" value="<?= e($customer['national_code'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>رمز عبور جدید (اختیاری)</label>
                        <input class="input" type="password" name="new_password" placeholder="تنها در صورت تغییر وارد کنید">
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; margin-top:20px;">
                        <label class="form-check">
                            <input type="checkbox" name="active" value="1" <?= $customer['active'] ? 'checked' : '' ?>>
                            <span>حساب کاربری فعال باشد</span>
                        </label>
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>یادداشت داخلی</label>
                    <textarea class="textarea" name="notes"><?= e($customer['notes'] ?? '') ?></textarea>
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
