<?php
declare(strict_types=1);

/**
 * routes_shop_settings.php
 * Shop owner/manager settings: Rich-text policy & about editor, bank cards, staff managers
 */

// 1. Shop Settings Page (with Rich Text Policy & About Editors)
route('GET|POST', '/shop/settings', ['shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'superadmin']);
    $shopId = (int)($user['shop_id'] ?? active_shop_id());
    $shop = get_shop($shopId);
    if (!$shop) {
        error_page(404, 'یافت نشد', 'فروشگاهی برای شما یافت نشد.');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            die('خطای اعتبارسنجی فرم.');
        }

        $phone = trim($_POST['phone'] ?? '') ?: null;
        $email = trim($_POST['email'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '') ?: null;
        $nationalId = trim(fa_to_en_digits($_POST['national_id'] ?? '')) ?: null;
        $economicCode = trim(fa_to_en_digits($_POST['economic_code'] ?? '')) ?: null;
        $reservationDays = max(1, (int)fa_to_en_digits($_POST['reservation_days'] ?? '4'));
        $taxRate = max(0, (float)($_POST['tax_rate'] ?? 0)) / 100.0;
        $defaultShipping = max(0, clean_price_input($_POST['default_shipping_cost'] ?? '0'));
        $freeShippingThreshold = max(0, clean_price_input($_POST['free_shipping_threshold'] ?? '0'));
        $cardEnabled = isset($_POST['card_to_card_enabled']) ? 1 : 0;
        $policiesHtml = trim($_POST['policies_html'] ?? '');
        $aboutHtml = trim($_POST['about_html'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE shops 
                SET phone = ?, email = ?, address = ?, national_id = ?, economic_code = ?, card_to_card_enabled = ?,
                    reservation_days = ?, tax_rate = ?, default_shipping_cost = ?, free_shipping_threshold = ?,
                    policies_html = ?, about_html = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            flash('success', 'تنظیمات و متون قوانین و درباره‌ما با موفقیت ذخیره شدند.');
            redirect('/shop/settings');
        } catch (Throwable $e) {
            $error = 'خطا در ذخیره تنظیمات: ' . $e->getMessage();
        }
    }

    $cards = get_shop_active_cards($shopId);
    $managers = $pdo->query("SELECT * FROM users WHERE shop_id = {$shopId} AND role = 'shop_manager' AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);

    layout_start('تنظیمات فروشگاه', $user);
    ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:800; margin:0; color:#0f172a;">تنظیمات فروشگاه «<?= e($shop['name']) ?>»</h1>
            <div style="font-size:0.85rem; color:#64748b;">مدیریت کارت‌های بانکی، سیاست‌های خرید، متن درباره‌ما و مشخصات فروشگاه</div>
        </div>
        <a href="/shop/<?= e($shop['slug']) ?>" target="_blank" class="btn btn-outline"><?= icon('store', 14) ?> مشاهده ویترین عمومی</a>
    </div>

    <?php if ($error): ?><div class="card" style="background:#fef2f2; border-color:#fecaca; color:#991b1b; padding:12px;"><?= e($error) ?></div><?php endif; ?>

    <!-- MAIN SETTINGS & RICH TEXT FORM -->
    <div class="card" style="padding:24px; margin-bottom:24px;">
        <h2 style="font-size:1.05rem; font-weight:bold; margin-bottom:16px;">مشخصات مالی، ارسال و ویرایشگر متون فروشگاه</h2>
        <form method="post" action="/shop/settings" id="shopSettingsForm">
            <?= csrf_field() ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">تلفن پشتیبانی فروشگاه:</label>
                    <input type="text" name="phone" class="input" value="<?= e($shop['phone']) ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">ایمیل رسمی فروشگاه:</label>
                    <input type="email" name="email" class="input" value="<?= e($shop['email']) ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">مهلت رزرو کالا (روز کاری):</label>
                    <input type="number" name="reservation_days" class="input" min="1" max="14" value="<?= (int)($shop['reservation_days'] ?: 4) ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">درصد مالیات بر ارزش افزوده (%):</label>
                    <input type="number" name="tax_rate" class="input" step="0.1" value="<?= (float)($shop['tax_rate'] * 100) ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">هزینه پیش‌فرض ارسال (تومان):</label>
                    <input type="text" inputmode="numeric" name="default_shipping_cost" class="input price-input" value="<?= number_format((float)$shop['default_shipping_cost']) ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">آستانه ارسال رایگان (تومان):</label>
                    <input type="text" inputmode="numeric" name="free_shipping_threshold" class="input price-input" value="<?= number_format((float)$shop['free_shipping_threshold']) ?>">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.85rem; font-weight:bold; margin-bottom:4px;">نشانی فیزیکی فروشگاه:</label>
                <input type="text" name="address" class="input" value="<?= e($shop['address']) ?>">
            </div>

            <!-- RICH TEXT EDITOR: POLICIES -->
            <div style="margin-bottom:24px;">
                <label style="display:block; font-size:0.9rem; font-weight:bold; margin-bottom:6px; color:#1e293b;">
                    متن صفحه قوانین و رویه‌های ارسال و مرجوعی (Quill Rich Text):
                </label>
                <textarea name="policies_html" data-rich-editor="true"><?= e($shop['policies_html'] ?: '<p>قوانین ارسال و مرجوعی کالا در این قسمت نوشته می‌شود.</p>') ?></textarea>
            </div>

            <!-- RICH TEXT EDITOR: ABOUT -->
            <div style="margin-bottom:24px;">
                <label style="display:block; font-size:0.9rem; font-weight:bold; margin-bottom:6px; color:#1e293b;">
                    متن صفحه درباره فروشگاه (Quill Rich Text):
                </label>
                <textarea name="about_html" data-rich-editor="true"><?= e($shop['about_html'] ?: '<p>داستان شکل‌گیری و تعهدات فروشگاه ما در این قسمت قرار می‌گیرد.</p>') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:10px 24px;">ذخیره تنظیمات و صفحات</button>
        </form>
    </div>

    <!-- BANK CARDS SECTION -->
    <div class="card" style="padding:24px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h2 style="font-size:1.05rem; font-weight:bold; margin:0;">کارت‌های بانکی جهت تسویه کارت‌به‌کارت</h2>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>شماره کارت</th>
                    <th>نام صاحب حساب</th>
                    <th>بانک</th>
                    <th>شماره شبا</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cards as $c): ?>
                    <tr>
                        <td><code><?= e($c['card_number']) ?></code></td>
                        <td><?= e($c['card_holder']) ?></td>
                        <td><?= e($c['bank_name']) ?></td>
                        <td><small><?= e($c['shaba_number']) ?></small></td>
                        <td><span class="badge badge-emerald">فعال</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    layout_end();
});
