<?php
declare(strict_types=1);

route('GET|POST', '/login(?:\.php)?', [], function () use ($pdo) {
    if (current_user())
        redirect('/');

    $error = '';
    $next = $_GET['next'] ?? $_POST['next'] ?? '/';

    if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
        $next = '/';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $error = 'توکن امنیتی نامعتبر است.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $now = gmdate('Y-m-d H:i:s');

            $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE (username = ? OR ip_address = ?) AND blocked_until > ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$username, $ip, $now]);

            if ($stmt->fetch()) {
                $error = 'حساب کاربری به مدت ' . LOGIN_BLOCK_MINUTES . ' دقیقه قفل شده است.';
            } else {
                $normPhone = normalize_phone($username);
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (username = ? OR phone = ? OR (? IS NOT NULL AND phone = ?)) 
                      AND active = 1 AND deleted_at IS NULL
                ");
                $stmt->execute([$username, $username, $normPhone, $normPhone]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['last_activity'] = time();

                    $pdo->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?")->execute([$username, $ip]);
                    redirect($next);
                }

                $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE username = ? AND ip_address = ?");
                $stmt->execute([$username, $ip]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $newCount = (int) $row['attempt_count'] + 1;
                    $blockedUntil = $newCount >= MAX_LOGIN_ATTEMPTS
                        ? gmdate('Y-m-d H:i:s', strtotime('+' . LOGIN_BLOCK_MINUTES . ' minutes'))
                        : null;

                    $pdo->prepare("UPDATE login_attempts SET attempt_count = ?, last_attempt_at = ?, blocked_until = ? WHERE id = ?")
                        ->execute([$newCount, $now, $blockedUntil, $row['id']]);
                } else {
                    $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_count, last_attempt_at) VALUES (?, ?, 1, ?)")
                        ->execute([$username, $ip, $now]);
                }

                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }

    auth_layout_start('ورود');
    ?>
    <div class="logo"><?= icon('orders', 22) ?></div>
    <h1>ورود به سامانه</h1>
    <div class="sub">لطفاً اطلاعات حساب کاربری خود را وارد کنید</div>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="form-group">
            <label>نام کاربری <?= tip('نام کاربری یکتای شما برای ورود به سامانه است.') ?></label>
            <input class="input" type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label>رمز عبور <?= tip('در صورت فراموشی رمز عبور، با مدیر سیستم تماس بگیرید.') ?></label>
            <input class="input" type="password" name="password" required>
        </div>

        <button class="btn" type="submit">ورود</button>
    </form>
    <div style="margin-top:20px; text-align:center; font-size:0.88rem; color:#64748b;">
        حساب کاربری ندارید؟ <a href="/register" style="color:#2563eb; font-weight:bold; text-decoration:none;">ثبت‌نام در سامانه</a>
    </div>
    <?php
    auth_layout_end();
});

route('POST', '/logout', [], function () {
    if (verify_csrf()) {
        session_unset();
        session_destroy();
    }

    redirect('/login');
});

require_once __DIR__ . '/routes_dashboard.php';
require_once __DIR__ . '/routes_auth_profile.php';
require_once __DIR__ . '/routes_addresses.php';