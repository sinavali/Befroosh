<?php
declare(strict_types=1);

// Superadmin: Manage all shops
route('GET', '/shops/manage(?:\.php)?', ['superadmin'], function () use ($pdo) {
    $user = require_roles(['superadmin']);

    $stmt = $pdo->query("
        SELECT s.*, u.nickname AS owner_name,
            (SELECT COUNT(*) FROM orders o WHERE o.shop_id = s.id) AS orders_count,
            (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.deleted_at IS NULL) AS products_count,
            (SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders o WHERE o.shop_id = s.id AND o.status IN ('finalised','completed')) AS total_revenue
        FROM shops s
        LEFT JOIN users u ON u.id = s.owner_id
        ORDER BY s.id ASC
    ");
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت فروشگاه‌ها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('settings', 18) ?></div>
            <div>
                <h1>مدیریت فروشگاه‌ها (چندمستأجری)</h1>
                <div class="page-sub">تعریف فروشگاه‌های جدید، تنظیمات و نظارت بر درآمد هر فروشگاه</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/shops/create"><?= icon('plus', 14) ?> تعریف فروشگاه جدید</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام فروشگاه</th>
                        <th>شناسه یکتا (اسلاگ)</th>
                        <th>مدیر مسئول</th>
                        <th>تلفن</th>
                        <th>تعداد کالا</th>
                        <th>سفارشات</th>
                        <th>درآمد کل</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$shops): ?>
                        <tr><td colspan="10"><?= empty_state('هیچ فروشگاهی ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($shops as $s): ?>
                            <tr>
                                <td><?= en_to_fa_digits((string)$s['id']) ?></td>
                                <td>
                                    <strong><?= e($s['name']) ?></strong>
                                    <?php if ($s['card_to_card_enabled']): ?>
                                        <span class="badge badge-emerald" title="کارت به کارت فعال">کارت‌به‌کارت</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($s['slug']) ?></code></td>
                                <td><?= e($s['owner_name'] ?: '—') ?></td>
                                <td><?= e($s['phone'] ? en_to_fa_digits($s['phone']) : '—') ?></td>
                                <td><?= en_to_fa_digits((string)$s['products_count']) ?></td>
                                <td><?= en_to_fa_digits((string)$s['orders_count']) ?></td>
                                <td><?= format_irr((float)$s['total_revenue']) ?></td>
                                <td>
                                    <?= $s['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-rose">غیرفعال</span>' ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a class="btn btn-outline btn-sm" href="/shops/<?= (int)$s['id'] ?>/switch" title="ورود به عنوان این فروشگاه">انتخاب</a>
                                        <a class="btn btn-outline btn-sm" href="/shops/<?= (int)$s['id'] ?>/edit"><?= icon('edit', 14) ?> ویرایش</a>
                                    </div>
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

// Superadmin: Create new shop
route('GET|POST', '/shops/create', ['superadmin'], function () use ($pdo) {
    $user = require_roles(['superadmin']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '') ?: null;
        $ownerId = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
        $cardNumber = trim(fa_to_en_digits($_POST['card_number'] ?? '')) ?: null;
        $cardHolder = trim($_POST['card_holder'] ?? '') ?: null;
        $bankName = trim($_POST['bank_name'] ?? '') ?: null;
        $taxRate = max(0, (float)($_POST['tax_rate'] ?? 0)) / 100.0;
        $defaultShipping = max(0, (float)fa_to_en_digits($_POST['default_shipping_cost'] ?? '0'));
        $freeShippingThreshold = max(0, (float)fa_to_en_digits($_POST['free_shipping_threshold'] ?? '0'));
        $cardEnabled = isset($_POST['card_to_card_enabled']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$name || !$slug) {
            $error = 'نام و شناسه یکتای فروشگاه الزامی هستند.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO shops (name, slug, owner_id, phone, address, card_number, card_holder, bank_name, card_to_card_enabled, tax_rate, default_shipping_cost, free_shipping_threshold, active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                $stmt->execute([$name, $slug, $ownerId, $phone, $address, $cardNumber, $cardHolder, $bankName, $cardEnabled, $taxRate, $defaultShipping, $freeShippingThreshold, $active]);
                
                flash('success', 'فروشگاه جدید با موفقیت ایجاد شد.');
                redirect('/shops/manage');
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'شناسه یکتای فروشگاه (اسلاگ) تکراری است.' : 'خطا در ثبت فروشگاه: ' . $e->getMessage();
            }
        }
    }

    $admins = $pdo->query("SELECT id, nickname, username FROM users WHERE role IN ('admin', 'superadmin') AND active = 1 ORDER BY nickname")->fetchAll(PDO::FETCH_ASSOC);

    layout_start('تعریف فروشگاه جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>تعریف فروشگاه جدید</h1>
                <div class="page-sub">مشخصات هویتی، بانکی و تنظیمات مالی فروشگاه</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/shops/manage">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات پایه</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>نام فروشگاه *</label>
                        <input class="input" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>شناسه یکتا (انگلیسی، مثلاً digi-store) *</label>
                        <input class="input" name="slug" dir="ltr" value="<?= e($_POST['slug'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>تلفن تماس</label>
                        <input class="input" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>مدیر مسئول</label>
                        <select class="select" name="owner_id">
                            <option value="">انتخاب نشده</option>
                            <?php foreach ($admins as $adm): ?>
                                <option value="<?= (int)$adm['id'] ?>"><?= e($adm['nickname']) ?> (<?= e($adm['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>نشانی کامل</label>
                        <input class="input" name="address" value="<?= e($_POST['address'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>تنظیمات پرداخت کارت‌به‌کارت</h2></div>
            <div class="card-body">
                <div class="form-group mb-2">
                    <label class="form-check">
                        <input type="checkbox" name="card_to_card_enabled" value="1" <?= isset($_POST['card_to_card_enabled']) || !isset($_POST['name']) ? 'checked' : '' ?>>
                        <span>فعال بودن روش پرداخت کارت‌به‌کارت</span>
                    </label>
                </div>
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>شماره کارت ۱۶ رقمی</label>
                        <input class="input" name="card_number" dir="ltr" placeholder="6037-xxxx-xxxx-xxxx" value="<?= e($_POST['card_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>نام صاحب حساب / کارت</label>
                        <input class="input" name="card_holder" value="<?= e($_POST['card_holder'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>نام بانک</label>
                        <input class="input" name="bank_name" placeholder="مثلاً بانک ملی ایران" value="<?= e($_POST['bank_name'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>تنظیمات مالی و ارسال</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>درصد مالیات بر ارزش افزوده (مثلاً 10 برای ۱۰٪)</label>
                        <input class="input" type="number" step="0.1" name="tax_rate" value="<?= e($_POST['tax_rate'] ?? '10') ?>">
                    </div>
                    <div class="form-group">
                        <label>هزینه پیش‌فرض ارسال (ریال)</label>
                        <input class="input" type="number" name="default_shipping_cost" value="<?= e($_POST['default_shipping_cost'] ?? '250000') ?>">
                    </div>
                    <div class="form-group">
                        <label>حداقل مبلغ خرید برای ارسال رایگان (ریال)</label>
                        <input class="input" type="number" name="free_shipping_threshold" value="<?= e($_POST['free_shipping_threshold'] ?? '2500000') ?>">
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label class="form-check">
                        <input type="checkbox" name="active" value="1" checked>
                        <span>فروشگاه فعال باشد</span>
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('check', 14) ?> ایجاد فروشگاه</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

// Superadmin: Edit shop
route('GET|POST', '/shops/(\d+)/edit', ['superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['superadmin']);
    $shop = get_shop((int)$id);
    if (!$shop) {
        error_page(404, 'یافت نشد', 'فروشگاه مورد نظر پیدا نشد.');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '') ?: null;
        $ownerId = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
        $cardNumber = trim(fa_to_en_digits($_POST['card_number'] ?? '')) ?: null;
        $cardHolder = trim($_POST['card_holder'] ?? '') ?: null;
        $bankName = trim($_POST['bank_name'] ?? '') ?: null;
        $taxRate = max(0, (float)($_POST['tax_rate'] ?? 0)) / 100.0;
        $defaultShipping = max(0, (float)fa_to_en_digits($_POST['default_shipping_cost'] ?? '0'));
        $freeShippingThreshold = max(0, (float)fa_to_en_digits($_POST['free_shipping_threshold'] ?? '0'));
        $cardEnabled = isset($_POST['card_to_card_enabled']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$name || !$slug) {
            $error = 'نام و شناسه یکتای فروشگاه الزامی است.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE shops SET name = ?, slug = ?, owner_id = ?, phone = ?, address = ?, card_number = ?, card_holder = ?, bank_name = ?, card_to_card_enabled = ?, tax_rate = ?, default_shipping_cost = ?, free_shipping_threshold = ?, active = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $ownerId, $phone, $address, $cardNumber, $cardHolder, $bankName, $cardEnabled, $taxRate, $defaultShipping, $freeShippingThreshold, $active, $id]);
                
                flash('success', 'اطلاعات فروشگاه با موفقیت بروزرسانی شد.');
                redirect('/shops/manage');
            } catch (Throwable $e) {
                $error = 'خطا در ویرایش فروشگاه: ' . $e->getMessage();
            }
        }
    }

    $admins = $pdo->query("SELECT id, nickname, username FROM users WHERE role IN ('admin', 'superadmin') AND active = 1 ORDER BY nickname")->fetchAll(PDO::FETCH_ASSOC);

    layout_start('ویرایش فروشگاه: ' . $shop['name'], $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>ویرایش فروشگاه «<?= e($shop['name']) ?>»</h1>
                <div class="page-sub">تنظیمات، اطلاعات کارت به کارت و نرخ‌های مالی</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/shops/manage">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات پایه</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>نام فروشگاه *</label>
                        <input class="input" name="name" value="<?= e($_POST['name'] ?? $shop['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>شناسه یکتا (اسلاگ) *</label>
                        <input class="input" name="slug" dir="ltr" value="<?= e($_POST['slug'] ?? $shop['slug']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>تلفن تماس</label>
                        <input class="input" name="phone" value="<?= e($_POST['phone'] ?? $shop['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>مدیر مسئول</label>
                        <select class="select" name="owner_id">
                            <option value="">انتخاب نشده</option>
                            <?php foreach ($admins as $adm): ?>
                                <option value="<?= (int)$adm['id'] ?>" <?= (int)$shop['owner_id'] === (int)$adm['id'] ? 'selected' : '' ?>><?= e($adm['nickname']) ?> (<?= e($adm['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>نشانی کامل</label>
                        <input class="input" name="address" value="<?= e($_POST['address'] ?? $shop['address'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>تنظیمات پرداخت کارت‌به‌کارت</h2></div>
            <div class="card-body">
                <div class="form-group mb-2">
                    <label class="form-check">
                        <input type="checkbox" name="card_to_card_enabled" value="1" <?= (!empty($shop['card_to_card_enabled'])) ? 'checked' : '' ?>>
                        <span>فعال بودن روش پرداخت کارت‌به‌کارت برای این فروشگاه</span>
                    </label>
                </div>
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>شماره کارت ۱۶ رقمی</label>
                        <input class="input" name="card_number" dir="ltr" value="<?= e($_POST['card_number'] ?? $shop['card_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>نام صاحب حساب / کارت</label>
                        <input class="input" name="card_holder" value="<?= e($_POST['card_holder'] ?? $shop['card_holder'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>نام بانک</label>
                        <input class="input" name="bank_name" value="<?= e($_POST['bank_name'] ?? $shop['bank_name'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>تنظیمات مالی و ارسال</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>درصد مالیات ارزش افزوده (%)</label>
                        <input class="input" type="number" step="0.1" name="tax_rate" value="<?= e((string)(($shop['tax_rate'] ?? 0) * 100)) ?>">
                    </div>
                    <div class="form-group">
                        <label>هزینه پیش‌فرض ارسال (ریال)</label>
                        <input class="input" type="number" name="default_shipping_cost" value="<?= e((string)$shop['default_shipping_cost']) ?>">
                    </div>
                    <div class="form-group">
                        <label>حداقل خرید برای ارسال رایگان (ریال)</label>
                        <input class="input" type="number" name="free_shipping_threshold" value="<?= e((string)$shop['free_shipping_threshold']) ?>">
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label class="form-check">
                        <input type="checkbox" name="active" value="1" <?= $shop['active'] ? 'checked' : '' ?>>
                        <span>فروشگاه فعال باشد</span>
                    </label>
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

// Superadmin: Quick switch active shop
route('GET', '/shops/(\d+)/switch', ['superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['superadmin']);
    $shop = get_shop((int)$id);
    if ($shop) {
        $_SESSION['active_shop_id'] = (int)$shop['id'];
        flash('success', 'فروشگاه فعال به «' . $shop['name'] . '» تغییر یافت.');
    }
    safe_redirect_back('/dashboard');
});

// Shop Owner & Superadmin: Settings for shop, multiple bank cards & manager delegation
route('GET|POST', '/shop/settings', ['shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $shop = get_shop($shopId);
    if (!$shop) {
        error_page(404, 'یافت نشد', 'فروشگاهی برای شما یافت نشد.');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '') ?: null;
        $nationalId = trim(fa_to_en_digits($_POST['national_id'] ?? '')) ?: null;
        $economicCode = trim(fa_to_en_digits($_POST['economic_code'] ?? '')) ?: null;
        $reservationDays = max(1, (int)fa_to_en_digits($_POST['reservation_days'] ?? '4'));
        $taxRate = max(0, (float)($_POST['tax_rate'] ?? 0)) / 100.0;
        $defaultShipping = max(0, (float)fa_to_en_digits($_POST['default_shipping_cost'] ?? '0'));
        $freeShippingThreshold = max(0, (float)fa_to_en_digits($_POST['free_shipping_threshold'] ?? '0'));
        $cardEnabled = isset($_POST['card_to_card_enabled']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE shops 
                SET phone = ?, address = ?, national_id = ?, economic_code = ?, card_to_card_enabled = ?,
                    reservation_days = ?, tax_rate = ?, default_shipping_cost = ?, free_shipping_threshold = ?,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$phone, $address, $nationalId, $economicCode, $cardEnabled, $reservationDays, $taxRate, $defaultShipping, $freeShippingThreshold, $shopId]);
            flash('success', 'تنظیمات فروشگاه با موفقیت ذخیره شد.');
            redirect('/shop/settings');
        } catch (Throwable $e) {
            $error = 'خطا در ذخیره تنظیمات: ' . $e->getMessage();
        }
    }

    // Fetch all cards of this shop
    $cardsStmt = $pdo->prepare("SELECT * FROM shop_bank_cards WHERE shop_id = ? ORDER BY id DESC");
    $cardsStmt->execute([$shopId]);
    $cards = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch managers of this shop
    $mgrStmt = $pdo->prepare("SELECT * FROM users WHERE shop_id = ? AND role = 'shop_manager' AND deleted_at IS NULL");
    $mgrStmt->execute([$shopId]);
    $managers = $mgrStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('تنظیمات فروشگاه', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('settings', 18) ?></div>
            <div>
                <h1>تنظیمات فروشگاه «<?= e($shop['name']) ?>»</h1>
                <div class="page-sub">مدیریت کارت‌های بانکی، انتصاب مدیران داخلی و مشخصات ارسال و مالیات</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/s/<?= urlencode($shop['slug']) ?>" target="_blank">مشاهده ویترین آنلاین فروشگاه</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <!-- 1. MULTIPLE BANK CARDS MANAGEMENT -->
    <div class="card mb-3">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2>کارت‌های بانکی متصل جهت واریز کارت‌به‌کارت</h2>
            <button type="button" class="btn btn-primary btn-sm" data-modal="#addCardModal"><?= icon('plus', 13) ?> افزودن کارت بانکی</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>شماره کارت (۱۶ رقمی)</th>
                            <th>نام صاحب حساب</th>
                            <th>بانک</th>
                            <th>شماره شبا</th>
                            <th>تاریخ انقضا</th>
                            <th>وضعیت کارت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cards)): ?>
                        <tr><td colspan="7"><?= empty_state('هیچ کارت بانکی ثبت نشده است', 'برای دریافت وجه کارت‌به‌کارت، حداقل یک کارت بانکی فعال اضافه نمایید.') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($cards as $c): ?>
                            <tr>
                                <td dir="ltr" style="font-family:monospace; font-weight:800; color:#1e3a8a; font-size:1.05rem;">
                                    <?= format_card_number($c['card_number']) ?>
                                </td>
                                <td><strong><?= e($c['card_holder']) ?></strong></td>
                                <td><?= e($c['bank_name'] ?: '—') ?></td>
                                <td dir="ltr"><code><?= e($c['shaba_number'] ?: '—') ?></code></td>
                                <td><?= e($c['expires_at'] ?: 'نامحدود') ?></td>
                                <td>
                                    <?= $c['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <form method="post" action="/shop/cards/<?= (int)$c['id'] ?>/toggle">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-outline btn-sm"><?= $c['active'] ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button>
                                        </form>
                                        <form method="post" action="/shop/cards/<?= (int)$c['id'] ?>/delete" data-confirm="کارت حذف شود؟">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. SHOP MANAGERS ELEVATION -->
    <div class="card mb-3">
        <div class="card-header">
            <h2>مدیران داخلی این فروشگاه (Shop Managers)</h2>
        </div>
        <div class="card-body">
            <div style="font-size:0.85rem; color:#475569; margin-bottom:12px;">
                مدیران داخلی اجازه تعریف و ویرایش محصولات، تایید فیش‌های پرداختی و ثبت ارسال سفارشات را دارند اما به تنظیمات بانکی و انتصاب مدیران دیگر دسترسی ندارند.
            </div>

            <!-- Add Manager via Phone -->
            <form method="post" action="/shop/elevate-manager" style="display:flex; gap:10px; max-width:540px; margin-bottom:16px;">
                <?= csrf_field() ?>
                <input class="input" name="phone" dir="ltr" placeholder="شماره موبایل ۱۰ رقمی کاربر (مثال: 9123456789)" required>
                <button class="btn btn-primary" type="submit"><?= icon('plus', 13) ?> انتصاب به عنوان مدیر</button>
            </form>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>نام مدیر</th>
                            <th>شماره موبایل</th>
                            <th>نام کاربری</th>
                            <th>تاریخ انتصاب</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($managers)): ?>
                        <tr><td colspan="5"><?= empty_state('مدیر داخلی برای این فروشگاه منصوب نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($managers as $m): ?>
                            <tr>
                                <td><strong><?= e($m['nickname']) ?></strong></td>
                                <td dir="ltr"><?= format_phone($m['phone']) ?></td>
                                <td><code><?= e($m['username']) ?></code></td>
                                <td class="date-cell"><?= format_jalali($m['created_at']) ?></td>
                                <td>
                                    <form method="post" action="/shop/managers/<?= (int)$m['id'] ?>/demote" data-confirm="این کاربر به مشتری عادی تغییر یابد؟">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline btn-sm">عزل از مدیریت</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. GENERAL SHOP SETTINGS -->
    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>تنظیمات فاکتور رسمی، رزرو و ارسال</h2></div>
            <div class="card-body">
                <div class="form-group mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="card_to_card_enabled" value="1" <?= $shop['card_to_card_enabled'] ? 'checked' : '' ?>>
                        <span>پذیرش پرداخت به روش کارت‌به‌کارت در این فروشگاه فعال باشد</span>
                    </label>
                </div>

                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>مهلت رزرو کالا برای سفارشات پرداخت‌نشده (روز)</label>
                        <input class="input" type="number" name="reservation_days" value="<?= (int)($shop['reservation_days'] ?: 4) ?>" min="1" required>
                    </div>

                    <div class="form-group">
                        <label>نرخ مالیات بر ارزش افزوده (درصد)</label>
                        <input class="input" type="number" step="0.1" name="tax_rate" value="<?= e((string)(($shop['tax_rate'] ?? 0) * 100)) ?>">
                    </div>

                    <div class="form-group">
                        <label>هزینه پیش‌فرض ارسال سفارشات (ریال)</label>
                        <input class="input" type="number" name="default_shipping_cost" value="<?= e((string)$shop['default_shipping_cost']) ?>">
                    </div>

                    <div class="form-group">
                        <label>حداقل مبلغ خرید برای ارسال رایگان (ریال)</label>
                        <input class="input" type="number" name="free_shipping_threshold" value="<?= e((string)$shop['free_shipping_threshold']) ?>">
                    </div>

                    <div class="form-group">
                        <label>شناسه / کد ملی فروشگاه (جهت فاکتور رسمی)</label>
                        <input class="input" name="national_id" dir="ltr" value="<?= e($shop['national_id'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>کد اقتصادی فروشگاه</label>
                        <input class="input" name="economic_code" dir="ltr" value="<?= e($shop['economic_code'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>تلفن تماس فروشگاه</label>
                        <input class="input" name="phone" value="<?= e($shop['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>نشانی کامل پستی فروشگاه (جهت درج در فاکتور رسمی و برچسب پستی)</label>
                        <input class="input" name="address" value="<?= e($shop['address'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تنظیمات فروشگاه</button>
            </div>
        </div>
    </form>

    <!-- ADD BANK CARD MODAL -->
    <div class="modal-backdrop" id="addCardModal">
        <div class="modal" style="max-width:480px;">
            <form method="post" action="/shop/cards/create">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>افزودن کارت بانکی جدید</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>شماره کارت ۱۶ رقمی *</label>
                        <input class="input" name="card_number" dir="ltr" placeholder="6037xxxxxxxxxxxx" required maxlength="16" style="font-family:monospace; font-size:1.1rem;">
                    </div>
                    <div class="form-group mb-2">
                        <label>نام صاحب حساب / کارت *</label>
                        <input class="input" name="card_holder" required placeholder="مثال: علی احمدی">
                    </div>
                    <div class="form-group mb-2">
                        <label>نام بانک</label>
                        <input class="input" name="bank_name" placeholder="مثال: بانک ملی">
                    </div>
                    <div class="form-group mb-2">
                        <label>شماره شبا (اختیاری)</label>
                        <input class="input" name="shaba_number" dir="ltr" placeholder="IR000000000000000000000000">
                    </div>
                    <div class="form-group">
                        <label>تاریخ انقضای پذیرش کارت (شمسی)</label>
                        <input class="input" name="expires_at" placeholder="۱۴۰۵/۱۲/۲۹">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ذخیره کارت</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

// Create bank card
route('POST', '/shop/cards/create', ['shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    verify_csrf_or_die();
    $shopId = (int)($user['shop_id'] ?? active_shop_id());

    $cardNum = trim(fa_to_en_digits($_POST['card_number'] ?? ''));
    $cardHolder = trim($_POST['card_holder'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '') ?: null;
    $shaba = trim($_POST['shaba_number'] ?? '') ?: null;
    $expiresAt = trim($_POST['expires_at'] ?? '') ?: null;

    if (strlen($cardNum) === 16 && $cardHolder) {
        $pdo->prepare("
            INSERT INTO shop_bank_cards (shop_id, card_number, card_holder, bank_name, shaba_number, active, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, datetime('now'), datetime('now'))
        ")->execute([$shopId, $cardNum, $cardHolder, $bankName, $shaba, $expiresAt]);

        flash('success', 'کارت بانکی جدید با موفقیت اضافه شد.');
    } else {
        flash('error', 'شماره کارت باید ۱۶ رقم باشد و نام دارنده الزامی است.');
    }

    redirect('/shop/settings');
});

// Toggle bank card active state
route('POST', '/shop/cards/(\d+)/toggle', ['shop_owner', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    verify_csrf_or_die();
    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $id = (int)$id;

    $pdo->prepare("UPDATE shop_bank_cards SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ? AND shop_id = ?")
        ->execute([$id, $shopId]);

    flash('success', 'وضعیت کارت بانکی تغییر کرد.');
    redirect('/shop/settings');
});

// Delete bank card
route('POST', '/shop/cards/(\d+)/delete', ['shop_owner', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    verify_csrf_or_die();
    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $id = (int)$id;

    $pdo->prepare("DELETE FROM shop_bank_cards WHERE id = ? AND shop_id = ?")->execute([$id, $shopId]);
    flash('success', 'کارت بانکی حذف شد.');
    redirect('/shop/settings');
});

// Demote manager back to customer
route('POST', '/shop/managers/(\d+)/demote', ['shop_owner', 'superadmin'], function ($mgrId) use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    verify_csrf_or_die();
    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $mgrId = (int)$mgrId;

    $pdo->prepare("UPDATE users SET role = 'customer', shop_id = NULL WHERE id = ? AND shop_id = ? AND role = 'shop_manager'")
        ->execute([$mgrId, $shopId]);

    flash('success', 'کاربر از مدیریت عزل و به نقش مشتری عادی تغییر یافت.');
    redirect('/shop/settings');
});

// Customer / Universal: Browse shops and products
route('GET', '/shops(?:\.php)?', [], function () use ($pdo) {
    $user = current_user();
    $shops = all_active_shops();

    layout_start('ویترین فروشگاه‌ها', $user ?: ['role' => 'customer', 'nickname' => 'کاربر مهمان']);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>فروشگاه‌های سامانه</h1>
                <div class="page-sub">فروشگاه مورد نظر خود را جهت مشاهده محصولات و ثبت سفارش انتخاب نمایید</div>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
        <?php foreach ($shops as $s): ?>
            <?php
            $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE shop_id = {$s['id']} AND active = 1 AND deleted_at IS NULL")->fetchColumn();
            ?>
            <div class="card">
                <div class="card-body">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div>
                            <h2 style="font-size:1.15rem; margin-bottom:4px;"><?= e($s['name']) ?></h2>
                            <div style="color:var(--muted); font-size:0.8rem;"><?= e($s['address'] ?: 'تهران') ?></div>
                        </div>
                        <span class="badge badge-emerald">فعال</span>
                    </div>
                    <div style="font-size:0.85rem; color:#4b5563; margin-bottom:14px; min-height:40px;">
                        <?= e($s['description'] ?: 'ارائه‌دهنده باکیفیت‌ترین محصولات و ارسال به سراسر کشور.') ?>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f3f4f6; padding-top:10px;">
                        <span style="font-size:0.8rem; color:var(--muted);"><?= en_to_fa_digits((string)$prodCount) ?> محصول فعال</span>
                        <a class="btn btn-primary btn-sm" href="/orders/create?shop_id=<?= (int)$s['id'] ?>">خرید از این فروشگاه</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    layout_end();
});
