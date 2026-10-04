<?php
declare(strict_types=1);

/**
 * routes_shops_edit.php
 * Superadmin: Shop Creation and Modification
 */

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
