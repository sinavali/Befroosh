<?php
declare(strict_types=1);

/**
 * routes_auth_profile.php
 * User Profile Editing (Name, Nickname, National Code, Email) & Password Management
 * Username and Phone remain immutable.
 */

$profilePageHandler = function () use ($pdo) {
    $user = require_login();

    // Fresh user data from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;
    $_SESSION['user'] = $user;

    $isCustomer = ($user['role'] === 'customer');
    if ($isCustomer) {
        layout_public_start('پروفایل کاربری', null, $user);
    } else {
        layout_start('حساب کاربری', $user);
    }
    ?>
    <div class="page-header" style="margin-bottom:24px;">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('user', 18) ?></div>
            <div>
                <h1 style="font-size:1.35rem; font-weight:800; color:#0f172a;">حساب و پروفایل کاربری</h1>
                <div class="page-sub">ویرایش مشخصات هویتی و تغییر رمز عبور حساب</div>
            </div>
        </div>
    </div>

    <!-- PROFILE EDIT FORM -->
    <form method="post" action="/account/profile" style="margin-bottom:24px;">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-header">
                <h2>مشخصات هویتی و اطلاعات تماس</h2>
                <span style="font-size:0.8rem; color:#64748b;">نقش: <strong><?= e(role_fa($user['role'])) ?></strong></span>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                    <div class="form-group">
                        <label>نام کاربری <?= tip('نام کاربری حساب غیرقابل تغییر است.') ?></label>
                        <input class="input" value="<?= e($user['username']) ?>" readonly disabled style="background:#f1f5f9; color:#64748b; cursor:not-allowed;">
                    </div>

                    <div class="form-group">
                        <label>شماره تلفن همراه <?= tip('شماره همراه جهت احراز هویت پیامکی ثبت شده و غیرقابل تغییر است.') ?></label>
                        <input class="input" dir="ltr" value="<?= e($user['phone'] ?: '—') ?>" readonly disabled style="background:#f1f5f9; color:#64748b; cursor:not-allowed;">
                    </div>

                    <div class="form-group">
                        <label>نام</label>
                        <input class="input" name="first_name" value="<?= e($user['first_name'] ?? '') ?>" placeholder="مثال: محمد">
                    </div>

                    <div class="form-group">
                        <label>نام خانوادگی</label>
                        <input class="input" name="last_name" value="<?= e($user['last_name'] ?? '') ?>" placeholder="مثال: رضایی">
                    </div>

                    <div class="form-group">
                        <label>نام نمایشی (لقب) *</label>
                        <input class="input" name="nickname" value="<?= e($user['nickname'] ?? '') ?>" required placeholder="نامی که در سیستم نمایش داده می‌شود">
                    </div>

                    <div class="form-group">
                        <label>کد ملی</label>
                        <input class="input" name="national_code" dir="ltr" maxlength="10" value="<?= e($user['national_code'] ?? '') ?>" placeholder="۱۰ رقم کد ملی">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>آدرس ایمیل</label>
                        <input class="input" type="email" name="email" dir="ltr" value="<?= e($user['email'] ?? '') ?>" placeholder="user@example.com">
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">
                    <?= icon('check', 14) ?> ذخیره تغییرات پروفایل
                </button>
            </div>
        </div>
    </form>

    <!-- PASSWORD CHANGE FORM -->
    <form method="post" action="/account/password">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-header">
                <h2>تغییر رمز عبور</h2>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
                    <div class="form-group">
                        <label>رمز عبور فعلی *</label>
                        <input class="input" type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور جدید (حداقل ۶ نویسه) *</label>
                        <input class="input" type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>تکرار رمز عبور جدید *</label>
                        <input class="input" type="password" name="confirm_password" required>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-outline" style="border-color:#cbd5e1;">
                    <?= icon('lock', 14) ?> تغییر رمز عبور
                </button>
            </div>
        </div>
    </form>
    <?php
    if ($isCustomer) {
        layout_public_end(null);
    } else {
        layout_end();
    }
};

route('GET', '/account/profile(?:\.php)?', [], $profilePageHandler);
route('GET', '/profile(?:\.php)?', [], $profilePageHandler);

// Save Profile Updates
route('POST', '/account/profile', [], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $nationalCode = trim(fa_to_en_digits($_POST['national_code'] ?? ''));
    $email = trim($_POST['email'] ?? '');

    if ($nickname === '') {
        $nickname = trim($firstName . ' ' . $lastName) ?: $user['username'];
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'فرمت آدرس ایمیل وارد شده نامعتبر است.');
        safe_redirect_back('/account/profile');
    }

    if ($nationalCode !== '' && (!ctype_digit($nationalCode) || strlen($nationalCode) !== 10)) {
        flash('error', 'کد ملی باید دقیقاً ۱۰ رقم عددی باشد.');
        safe_redirect_back('/account/profile');
    }

    $stmt = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, nickname = ?, national_code = ?, email = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([
        $firstName ?: null,
        $lastName ?: null,
        $nickname,
        $nationalCode ?: null,
        $email ?: null,
        $user['id']
    ]);

    // Update active session
    $refresh = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $refresh->execute([$user['id']]);
    $_SESSION['user'] = $refresh->fetch(PDO::FETCH_ASSOC);

    flash('success', 'اطلاعات پروفایل شما با موفقیت به‌روزرسانی شد.');
    redirect('/account/profile');
});

// Change Password Handler
route('POST', '/account/password', [], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        flash('error', 'رمز عبور فعلی وارد شده نادرست است.');
    } elseif (strlen($new) < 6) {
        flash('error', 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.');
    } elseif ($new !== $confirm) {
        flash('error', 'تکرار رمز عبور جدید مطابقت ندارد.');
    } else {
        $pdo->prepare("UPDATE users SET password = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

        flash('success', 'رمز عبور شما با موفقیت تغییر کرد.');
    }

    redirect('/account/profile');
});
