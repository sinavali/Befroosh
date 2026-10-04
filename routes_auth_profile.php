<?php
declare(strict_types=1);

/**
 * routes_auth_profile.php
 * User Profile & Password Management
 */

route('GET', '/account/profile(?:\.php)?', [], function () use ($pdo) {
    $user = require_login();

    layout_start('حساب کاربری', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('user', 18) ?></div>
            <div>
                <h1>حساب کاربری</h1>
                <div class="page-sub">مشاهده اطلاعات و تغییر رمز عبور</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">نام کاربری <?= tip('نام کاربری قابل تغییر نیست.') ?></div>
                    <div class="detail-value"><?= e($user['username']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">نام نمایشی</div>
                    <div class="detail-value"><?= e($user['nickname']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">نقش</div>
                    <div class="detail-value"><?= e(role_fa($user['role'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">موبایل</div>
                    <div class="detail-value"><?= e($user['phone'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="/account/password">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-header">
                <h2>تغییر رمز عبور</h2>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>رمز عبور فعلی <?= tip('برای تأیید هویت، رمز فعلی را وارد کنید.') ?></label>
                        <input class="input" type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور جدید <?= tip('حداقل ۶ کاراکتر باشد.') ?></label>
                        <input class="input" type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>تکرار رمز عبور جدید</label>
                        <input class="input" type="password" name="confirm_password" required>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('lock', 14) ?> تغییر رمز عبور</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

route('POST', '/account/password', [], function () use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        flash('error', 'رمز عبور فعلی اشتباه است.');
    } elseif (strlen($new) < 6) {
        flash('error', 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.');
    } elseif ($new !== $confirm) {
        flash('error', 'تکرار رمز عبور یکسان نیست.');
    } else {
        $pdo->prepare("UPDATE users SET password = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

        flash('success', 'رمز عبور شما تغییر کرد.');
    }

    redirect('/account/profile');
});
