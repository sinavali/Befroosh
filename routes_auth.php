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

route('GET', '/', [], function () {
    $user = require_login();
    redirect('/dashboard');
});

route('GET', '/dashboard(?:\.php)?', [], function () use ($pdo) {
    $user = require_login();

    if ($user['role'] === 'customer') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $stmt->execute([$user['id']]);
        $totalOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'placed'");
        $stmt->execute([$user['id']]);
        $placedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'finalised'");
        $stmt->execute([$user['id']]);
        $finalisedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'completed'");
        $stmt->execute([$user['id']]);
        $completedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE customer_id = ? AND status IN ('finalised','completed')");
        $stmt->execute([$user['id']]);
        $totalSpend = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND status = 'open'");
        $stmt->execute([$user['id']]);
        $openTickets = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        layout_start('داشبورد مشتری', $user);
        ?>
        <div class="page-header">
            <div class="page-title-wrap">
                <div class="page-icon"><?= icon('dashboard', 18) ?></div>
                <div>
                    <h1>داشبورد</h1>
                    <div class="page-sub">خلاصه سفارشات و درخواست‌های شما</div>
                </div>
            </div>

            <div class="action-cluster">
                <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش جدید</a>
                <a class="btn btn-outline" href="/tickets/create"><?= icon('tickets', 14) ?> تیکت جدید</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $totalOrders) ?></div>
                    <div class="stat-label">کل سفارشات</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber"><?= icon('clock', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $placedOrders) ?></div>
                    <div class="stat-label">در انتظار نهایی‌سازی</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon emerald"><?= icon('check', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $completedOrders) ?></div>
                    <div class="stat-label">تکمیل شده</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('report', 18) ?></div>
                <div>
                    <div class="stat-value"><?= format_irr($totalSpend) ?></div>
                    <div class="stat-label">مجموع خرید نهایی</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon rose"><?= icon('tickets', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
                    <div class="stat-label">تیکت باز</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h2>آخرین سفارش شما</h2>
                <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه سفارشات</a>
            </div>
            <div class="card-body">
                <?php if (!$lastOrder): ?>
                    <?= empty_state('هنوز سفارشی ثبت نکرده‌اید', 'اولین سفارش خود را از دکمه «ثبت سفارش جدید» ایجاد کنید.') ?>
                <?php else: ?>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">شماره سفارش</div>
                            <div class="detail-value"><?= e($lastOrder['uuid']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">وضعیت</div>
                            <div class="detail-value"><?= order_badge($lastOrder['status']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">تاریخ ثبت</div>
                            <div class="detail-value date-cell"><?= format_jalali($lastOrder['created_at']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">مبلغ</div>
                            <div class="detail-value">
                                <?= format_irr((float) ($lastOrder['final_total'] ?? $lastOrder['estimated_total'])) ?></div>
                        </div>
                    </div>

                    <div class="mt-2">
                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int) $lastOrder['id'] ?>">مشاهده جزئیات</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>سفارشات اخیر</h2>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>وضعیت</th>
                            <th>مبلغ</th>
                            <th>تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentOrders): ?>
                            <tr>
                                <td colspan="5"><?= empty_state('سفارشی یافت نشد') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><strong><?= e($o['uuid']) ?></strong></td>
                                    <td><?= order_badge($o['status']) ?></td>
                                    <td><?= format_irr((float) ($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                    <td class="date-cell"><?= format_jalali($o['created_at']) ?></td>
                                    <td><a class="btn btn-outline btn-sm" href="/orders/<?= (int) $o['id'] ?>">مشاهده</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        layout_end();
        return;
    }

    $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
    $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
    $activeCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND active = 1 AND deleted_at IS NULL")->fetchColumn();
    $revenue = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE status IN ('finalised','completed')")->fetchColumn();

    $recentOrders = $pdo->query("
        SELECT o.*, u.nickname AS customer_nickname
        FROM orders o
        JOIN users u ON u.id = o.customer_id
        ORDER BY o.id DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    layout_start('داشبورد', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('dashboard', 18) ?></div>
            <div>
                <h1>داشبورد</h1>
                <div class="page-sub">خلاصه وضعیت سفارشات، مشتریان و تیکت‌ها</div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string) $ordersToday) ?></div>
                <div class="stat-label">سفارش امروز</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($revenue) ?></div>
                <div class="stat-label">درآمد نهایی/تکمیل‌شده</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('customers', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string) $activeCustomers) ?></div>
                <div class="stat-label">مشتری فعال</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><?= icon('tickets', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
                <div class="stat-label">تیکت باز</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>آخرین سفارشات</h2>
            <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>مشتری</th>
                        <th>وضعیت</th>
                        <th>مبلغ</th>
                        <th>تاریخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr>
                            <td colspan="6">
                                <?= empty_state('سفارشی ثبت نشده است', 'اولین سفارش را از بخش ثبت سفارش ایجاد کنید.') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['uuid']) ?></strong></td>
                                <td><?= e($o['customer_nickname']) ?></td>
                                <td><?= order_badge($o['status']) ?></td>
                                <td><?= format_irr((float) ($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                <td class="date-cell"><?= format_jalali($o['created_at']) ?></td>
                                <td><a class="btn btn-outline btn-sm" href="/orders/<?= (int) $o['id'] ?>">مشاهده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    layout_end();
});

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

route('GET', '/account/addresses(?:\.php)?', ['customer'], function () use ($pdo) {
    $user = require_roles(['customer']);

    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt->execute([$user['id']]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('آدرس‌های من', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>آدرس‌های من</h1>
                <div class="page-sub">مدیریت آدرس‌های تحویل سفارش</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/account/addresses/create"><?= icon('plus', 14) ?> افزودن آدرس</a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!$addresses): ?>
                <?= empty_state('آدرسی ثبت نشده است', 'برای ثبت سفارش ابتدا یک آدرس اضافه کنید.', 'location') ?>
            <?php else: ?>
                <div class="detail-grid">
                    <?php foreach ($addresses as $a): ?>
                        <div class="mini-card">
                            <div class="flex justify-between items-center gap-1 flex-wrap mb-1">
                                <strong><?= e($a['state']) ?>، <?= e($a['city']) ?></strong>
                                <?php if ($a['is_default']): ?><span class="badge badge-emerald">پیش‌فرض</span><?php endif; ?>
                            </div>

                            <div class="text-sm"><?= e($a['address']) ?></div>

                            <?php if ($a['postal_code']): ?>
                                <div class="text-sm text-muted mt-1">کد پستی: <?= e($a['postal_code']) ?></div>
                            <?php endif; ?>

                            <?php if ($a['description']): ?>
                                <div class="text-sm text-muted mt-1"><?= e($a['description']) ?></div>
                            <?php endif; ?>

                            <div class="action-cluster mt-2">
                                <a class="btn btn-outline btn-sm" href="/account/addresses/<?= (int) $a['id'] ?>/edit">ویرایش</a>

                                <?php if (!$a['is_default']): ?>
                                    <form method="post" action="/account/addresses/<?= (int) $a['id'] ?>/default" class="inline-form">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline btn-sm">پیش‌فرض</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="/account/addresses/<?= (int) $a['id'] ?>/delete" class="inline-form"
                                    data-confirm="این آدرس حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm">حذف</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    layout_end();
});

route('GET|POST', '/account/addresses/create', ['customer'], function () use ($pdo) {
    $user = require_roles(['customer']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $default = isset($_POST['is_default']) ? 1 : 0;

        if (!$state || !$city || !$address) {
            $error = 'فیلدهای ضروری آدرس را کامل کنید.';
        } else {
            if ($default) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }

            $pdo->prepare("INSERT INTO addresses (user_id, state, city, address, postal_code, description, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))")
                ->execute([$user['id'], $state, $city, $address, $postal, $desc, $default]);

            flash('success', 'آدرس اضافه شد.');
            redirect('/account/addresses');
        }
    }

    layout_start('آدرس جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>آدرس جدید</h1>
                <div class="page-sub">ثبت آدرس جدید برای دریافت سفارش</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/account/addresses">بازگشت</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>استان <?= tip('مثال: تهران') ?></label>
                        <input class="input" name="state" required>
                    </div>
                    <div class="form-group">
                        <label>شهر <?= tip('مثال: تهران') ?></label>
                        <input class="input" name="city" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>آدرس کامل <?= tip('خیابان، کوچه، پلاک، واحد و نکات ضروری') ?></label>
                        <textarea class="textarea" name="address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>کد پستی <?= tip('اختیاری است ولی برای ارسال دقیق توصیه می‌شود.') ?></label>
                        <input class="input" name="postal_code">
                    </div>
                    <div class="form-group">
                        <label>توضیحات <?= tip('مثال: زنگ واحد خراب است.') ?></label>
                        <input class="input" name="description">
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="is_default">
                            به عنوان آدرس پیش‌فرض <?= tip('آدرس پیش‌فرض در ثبت سفارش سریع‌تر انتخاب می‌شود.') ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('check', 14) ?> ذخیره آدرس</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

route('GET|POST', '/account/addresses/(\d+)/edit', ['customer'], function ($id) use ($pdo) {
    $user = require_roles(['customer']);

    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        error_page(404, 'یافت نشد', 'آدرس مورد نظر وجود ندارد.');
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $default = isset($_POST['is_default']) ? 1 : 0;

        if (!$state || !$city || !$addr) {
            $error = 'فیلدهای ضروری آدرس را کامل کنید.';
        } else {
            if ($default) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }

            $pdo->prepare("UPDATE addresses SET state = ?, city = ?, address = ?, postal_code = ?, description = ?, is_default = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?")
                ->execute([$state, $city, $addr, $postal, $desc, $default, $id, $user['id']]);

            flash('success', 'آدرس به‌روزرسانی شد.');
            redirect('/account/addresses');
        }
    }

    layout_start('ویرایش آدرس', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>ویرایش آدرس</h1>
                <div class="page-sub">اصلاح اطلاعات آدرس تحویل</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/account/addresses">بازگشت</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>استان</label>
                        <input class="input" name="state" value="<?= e($address['state']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>شهر</label>
                        <input class="input" name="city" value="<?= e($address['city']) ?>" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>آدرس کامل</label>
                        <textarea class="textarea" name="address" required><?= e($address['address']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>کد پستی</label>
                        <input class="input" name="postal_code" value="<?= e($address['postal_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>توضیحات</label>
                        <input class="input" name="description" value="<?= e($address['description'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="is_default" <?= $address['is_default'] ? 'checked' : '' ?>>
                            به عنوان آدرس پیش‌فرض
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تغییرات</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

route('POST', '/account/addresses/(\d+)/delete', ['customer'], function ($id) use ($pdo) {
    $user = require_roles(['customer']);
    verify_csrf_or_die();

    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $addr = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($addr) {
        $pdo->prepare("DELETE FROM addresses WHERE id = ?")->execute([$id]);

        if ($addr['is_default']) {
            $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$user['id']]);
            $first = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($first) {
                $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ?")->execute([$first['id']]);
            }
        }
    }

    flash('success', 'آدرس حذف شد.');
    redirect('/account/addresses');
});

route('POST', '/account/addresses/(\d+)/default', ['customer'], function ($id) use ($pdo) {
    $user = require_roles(['customer']);
    verify_csrf_or_die();

    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
    $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);

    flash('success', 'آدرس پیش‌فرض تغییر کرد.');
    redirect('/account/addresses');
});