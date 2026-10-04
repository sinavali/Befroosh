<?php
declare(strict_types=1);

/**
 * routes_customers.php
 * Customer Accounts & Customer Delivery Addresses Management
 * Supports mandatory 10-digit mobile phone number, first/last name, and national code
 */

// Customers listing
route('GET', '/customers(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);

    $search = trim($_GET['search'] ?? '');
    $active = $_GET['active'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    [$sort, $dir] = get_sort(['id', 'username', 'nickname', 'phone', 'created_at'], 'id');

    $where = "WHERE role = 'customer' AND deleted_at IS NULL";
    $params = [];

    if ($search !== '') {
        $where .= " AND (username LIKE ? OR nickname LIKE ? OR phone LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($active !== '') {
        $where .= " AND active = ?";
        $params[] = (int)$active;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY $sort $dir LIMIT {$pag['perPage']} OFFSET {$pag['offset']}");
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت مشتریان', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('customers', 18) ?></div>
            <div>
                <h1>مدیریت مشتریان</h1>
                <div class="page-sub">مدیریت حساب‌های کاربری مشتریان، تلفن همراه و آدرس‌های تحویل</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/customers/create"><?= icon('plus', 14) ?> افزودن مشتری جدید</a>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجوی نام، موبایل، نام کاربری..." value="<?= e($search) ?>" style="min-width:260px;">

                <select class="select" name="active">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>غیرفعال</option>
                </select>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <?php if ($search !== '' || $active !== ''): ?>
                    <a class="btn btn-ghost" href="/customers">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- CUSTOMERS TABLE -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام و نام خانوادگی</th>
                        <th>شماره موبایل</th>
                        <th>نام کاربری</th>
                        <th>کد ملی</th>
                        <th>وضعیت</th>
                        <th>تاریخ عضویت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="8"><?= empty_state('مشتری یافت نشد') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>#<?= en_to_fa_digits((string)$c['id']) ?></td>
                            <td><strong><?= e($c['nickname']) ?></strong></td>
                            <td dir="ltr" style="font-family:monospace;"><?= format_phone($c['phone']) ?></td>
                            <td><code><?= e($c['username']) ?></code></td>
                            <td><?= e($c['national_code'] ?: '—') ?></td>
                            <td>
                                <?= $c['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-rose">غیرفعال</span>' ?>
                            </td>
                            <td class="date-cell"><?= format_jalali($c['created_at']) ?></td>
                            <td>
                                <div class="flex gap-1">
                                    <a class="btn btn-outline btn-sm" href="/customers/<?= (int)$c['id'] ?>/addresses"><?= icon('location', 13) ?> آدرس‌ها</a>
                                    <a class="btn btn-outline btn-sm" href="/customers/<?= (int)$c['id'] ?>/edit"><?= icon('edit', 13) ?> ویرایش</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/customers', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

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

// Customer Addresses Listing & CRUD
route('GET', '/customers/(\d+)/addresses', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) redirect('/customers');

    $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $addrStmt->execute([$id]);
    $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('آدرس‌های مشتری', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>آدرس‌های تحویل: <?= e($customer['nickname']) ?></h1>
                <div class="page-sub">تلفن همراه: <?= format_phone($customer['phone']) ?></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/customers">بازگشت به مشتریان</a>
            <a class="btn btn-primary" href="/customers/<?= $id ?>/addresses/create"><?= icon('plus', 14) ?> افزودن آدرس جدید</a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>استان و شهر</th>
                        <th>نشانی کامل</th>
                        <th>کد پستی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($addresses)): ?>
                    <tr><td colspan="5"><?= empty_state('آدرسی برای این مشتری ثبت نشده است') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($addresses as $a): ?>
                        <tr>
                            <td><strong><?= e($a['state']) ?></strong>، <?= e($a['city']) ?></td>
                            <td><?= e($a['address']) ?></td>
                            <td><code><?= e($a['postal_code'] ?? '—') ?></code></td>
                            <td><?= $a['is_default'] ? '<span class="badge badge-emerald">پیش‌فرض</span>' : '—' ?></td>
                            <td>
                                <form method="post" action="/customers/<?= $id ?>/addresses/<?= (int)$a['id'] ?>/delete" class="inline-form" data-confirm="آدرس حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?></button>
                                </form>
                            </td>
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

// Customer Address Create
route('GET|POST', '/customers/(\d+)/addresses/create', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $id = (int)$id;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($state && $city && $addr) {
            if ($isDefault) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$id]);
            }
            $pdo->prepare("
                INSERT INTO addresses (user_id, state, city, address, postal_code, is_default, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ")->execute([$id, $state, $city, $addr, $postal, $isDefault]);

            flash('success', 'آدرس جدید برای مشتری ثبت شد.');
            redirect("/customers/$id/addresses");
        }
    }

    layout_start('افزودن آدرس مشتری', $user);
    ?>
    <div class="page-header">
        <h1>افزودن آدرس جدید</h1>
        <a class="btn btn-outline" href="/customers/<?= $id ?>/addresses">بازگشت</a>
    </div>

    <form method="post" style="max-width:600px;">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-group mb-2">
                    <label>استان *</label>
                    <input class="input" name="state" required>
                </div>
                <div class="form-group mb-2">
                    <label>شهر *</label>
                    <input class="input" name="city" required>
                </div>
                <div class="form-group mb-2">
                    <label>نشانی کامل *</label>
                    <textarea class="textarea" name="address" required></textarea>
                </div>
                <div class="form-group mb-2">
                    <label>کد پستی</label>
                    <input class="input" name="postal_code" dir="ltr">
                </div>
                <label class="form-check">
                    <input type="checkbox" name="is_default" value="1" checked>
                    <span>تنظیم به عنوان آدرس پیش‌فرض</span>
                </label>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit">ذخیره آدرس</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

// Customer Address Delete
route('POST', '/customers/(\d+)/addresses/(\d+)/delete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($userId, $addrId) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([(int)$addrId, (int)$userId]);
    flash('success', 'آدرس حذف شد.');
    redirect("/customers/$userId/addresses");
});
