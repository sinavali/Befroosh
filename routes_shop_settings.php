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
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('store', 18) ?></div>
            <div>
                <h1>تنظیمات فروشگاه «<?= e($shop['name']) ?>»</h1>
                <div class="page-sub">مدیریت کارت‌های بانکی مقصد، سیاست‌های خرید، متن درباره‌ما و مشخصات فروشگاه</div>
            </div>
        </div>
        <a href="/shop/<?= e($shop['slug']) ?>" target="_blank" class="btn btn-outline"><?= icon('eye', 14) ?> مشاهده ویترین عمومی</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error mb-3"><?= e($error) ?></div><?php endif; ?>

    <!-- MAIN SETTINGS & RICH TEXT FORM -->
    <div class="card mb-3">
        <div class="card-header">
            <h2>مشخصات مالی، ارسال و ویرایشگر متون فروشگاه</h2>
        </div>
        <div class="card-body">
            <form method="post" action="/shop/settings" id="shopSettingsForm">
                <?= csrf_field() ?>
                <div class="form-grid mb-3">
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
    <div class="card mb-3">
        <div class="card-header">
            <h2>کارت‌های بانکی مقصد جهت تسویه کارت‌به‌کارت</h2>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره کارت</th>
                        <th>نام صاحب حساب</th>
                        <th>بانک مقصد</th>
                        <th>شماره شبا (IBAN)</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cards)): ?>
                        <tr><td colspan="5"><?= empty_state('هیچ کارت بانکی فعالی ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($cards as $c): ?>
                            <tr>
                                <td><code class="tabular-nums" style="font-size:0.95rem; font-weight:700; background:var(--surface-well); padding:3px 8px; border-radius:4px; border:1px solid var(--border);"><?= chunk_card(e($c['card_number'])) ?></code></td>
                                <td style="font-weight:600;"><?= e($c['card_holder']) ?></td>
                                <td><?= e($c['bank_name']) ?></td>
                                <td><code class="tabular-nums" style="font-size:0.82rem; color:var(--slate);"><?= e($c['shaba_number'] ?: '—') ?></code></td>
                                <td><span class="badge badge-emerald">فعال</span></td>
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
