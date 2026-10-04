<?php
declare(strict_types=1);

/**
 * routes_addresses.php
 * Customer delivery address management (list, create, edit, delete, set default)
 * Accessible via /profile/addresses and backwards-compatible /account/addresses
 */

route('GET', '/(?:profile|account)/addresses(?:\.php)?', ['customer'], function () use ($pdo) {
    $user = require_roles(['customer']);

    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt->execute([$user['id']]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('دفترچه آدرس‌های تحویل', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>آدرس‌های تحویل من</h1>
                <div class="page-sub">مدیریت آدرس‌ها و گیرندگان سفارشات</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/profile/addresses/create"><?= icon('plus', 14) ?> افزودن آدرس جدید</a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!$addresses): ?>
                <?= empty_state('آدرسی ثبت نشده است', 'برای ثبت و ارسال سریع سفارشات، یک آدرس تحویل اضافه فرمایید.', 'location') ?>
            <?php else: ?>
                <div class="detail-grid">
                    <?php foreach ($addresses as $a): ?>
                        <div class="mini-card" style="position:relative;">
                            <div class="flex justify-between items-center gap-1 flex-wrap mb-1">
                                <strong><?= e($a['state']) ?>، <?= e($a['city']) ?></strong>
                                <?php if ($a['is_default']): ?><span class="badge badge-emerald">پیش‌فرض</span><?php endif; ?>
                            </div>

                            <?php if (!empty($a['recipient_name']) || !empty($a['recipient_phone'])): ?>
                                <div style="font-size:0.84rem; color:#1e293b; font-weight:bold; margin-bottom:4px;">
                                    👤 گیرنده: <?= e($a['recipient_name'] ?: $user['nickname']) ?>
                                    <?php if (!empty($a['recipient_phone'])): ?> | 📞 <?= en_to_fa_digits($a['recipient_phone']) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="text-sm" style="color:#475569; line-height:1.7;"><?= e($a['address']) ?></div>

                            <?php if ($a['postal_code']): ?>
                                <div class="text-sm text-muted mt-1">کد پستی: <?= en_to_fa_digits($a['postal_code']) ?></div>
                            <?php endif; ?>

                            <?php if ($a['description']): ?>
                                <div class="text-sm text-muted mt-1"><?= e($a['description']) ?></div>
                            <?php endif; ?>

                            <div class="action-cluster mt-2">
                                <a class="btn btn-outline btn-sm" href="/profile/addresses/<?= (int) $a['id'] ?>/edit">ویرایش</a>

                                <?php if (!$a['is_default']): ?>
                                    <form method="post" action="/profile/addresses/<?= (int) $a['id'] ?>/default" class="inline-form">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline btn-sm">پیش‌فرض</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="/profile/addresses/<?= (int) $a['id'] ?>/delete" class="inline-form"
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

route('GET|POST', '/(?:profile|account)/addresses/create', ['customer'], function () use ($pdo) {
    $user = require_roles(['customer']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $recName = trim($_POST['recipient_name'] ?? '');
        $recPhone = trim($_POST['recipient_phone'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $default = isset($_POST['is_default']) ? 1 : 0;

        if (!$state || !$city || !$address) {
            $error = 'استان، شهر و آدرس کامل الزامی هستند.';
        } else {
            if ($default) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }

            $pdo->prepare("
                INSERT INTO addresses (user_id, recipient_name, recipient_phone, state, city, address, postal_code, description, is_default, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ")->execute([$user['id'], $recName ?: $user['nickname'], $recPhone ?: $user['phone'], $state, $city, $address, $postal, $desc, $default]);

            flash('success', 'آدرس جدید با موفقیت ذخیره شد.');
            redirect('/profile/addresses');
        }
    }

    layout_start('ثبت آدرس جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('location', 18) ?></div>
            <div>
                <h1>ثبت آدرس جدید</h1>
                <div class="page-sub">اطلاعات تحویل‌گیرنده و نشانی پستی</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/profile/addresses">بازگشت</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>نام و نام خانوادگی تحویل‌گیرنده:</label>
                        <input class="input" name="recipient_name" value="<?= e($user['nickname'] ?? '') ?>" placeholder="مثال: علی محمدی">
                    </div>
                    <div class="form-group">
                        <label>شماره تماس گیرنده:</label>
                        <input class="input" name="recipient_phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="مثال: 09123456789">
                    </div>
                    <div class="form-group">
                        <label>استان:</label>
                        <input class="input" name="state" placeholder="مثال: تهران" required>
                    </div>
                    <div class="form-group">
                        <label>شهر:</label>
                        <input class="input" name="city" placeholder="مثال: تهران" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>نشانی کامل پستی:</label>
                        <textarea class="textarea" name="address" placeholder="خیابان، کوچه، پلاک، واحد و نکات ضروری..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>کد پستی (۱۰ رقمی):</label>
                        <input class="input" name="postal_code" placeholder="اختیاری ولی توصیه می‌شود">
                    </div>
                    <div class="form-group">
                        <label>توضیحات و راهنمای تحویل:</label>
                        <input class="input" name="description" placeholder="مثال: زنگ واحد ۳">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-check">
                            <input type="checkbox" name="is_default">
                            تنظیم به عنوان آدرس پیش‌فرض سفارشات
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

route('GET|POST', '/(?:profile|account)/addresses/(\d+)/edit', ['customer'], function ($id) use ($pdo) {
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

        $recName = trim($_POST['recipient_name'] ?? '');
        $recPhone = trim($_POST['recipient_phone'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '') ?: null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $default = isset($_POST['is_default']) ? 1 : 0;

        if (!$state || !$city || !$addr) {
            $error = 'استان، شهر و نشانی پستی الزامی هستند.';
        } else {
            if ($default) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }

            $pdo->prepare("
                UPDATE addresses 
                SET recipient_name = ?, recipient_phone = ?, state = ?, city = ?, address = ?, postal_code = ?, description = ?, is_default = ?, updated_at = datetime('now') 
                WHERE id = ? AND user_id = ?
            ")->execute([$recName, $recPhone, $state, $city, $addr, $postal, $desc, $default, $id, $user['id']]);

            flash('success', 'آدرس با موفقیت به‌روزرسانی شد.');
            redirect('/profile/addresses');
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
        <a class="btn btn-outline" href="/profile/addresses">بازگشت</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>نام و نام خانوادگی تحویل‌گیرنده:</label>
                        <input class="input" name="recipient_name" value="<?= e($address['recipient_name'] ?? $user['nickname']) ?>">
                    </div>
                    <div class="form-group">
                        <label>شماره تماس تحویل‌گیرنده:</label>
                        <input class="input" name="recipient_phone" value="<?= e($address['recipient_phone'] ?? $user['phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>استان:</label>
                        <input class="input" name="state" value="<?= e($address['state']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>شهر:</label>
                        <input class="input" name="city" value="<?= e($address['city']) ?>" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>آدرس کامل:</label>
                        <textarea class="textarea" name="address" required><?= e($address['address']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>کد پستی:</label>
                        <input class="input" name="postal_code" value="<?= e($address['postal_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>توضیحات تحویل:</label>
                        <input class="input" name="description" value="<?= e($address['description'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-check">
                            <input type="checkbox" name="is_default" <?= $address['is_default'] ? 'checked' : '' ?>>
                            به عنوان آدرس پیش‌فرض تحویل
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

route('POST', '/(?:profile|account)/addresses/(\d+)/delete', ['customer'], function ($id) use ($pdo) {
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
    redirect('/profile/addresses');
});

route('POST', '/(?:profile|account)/addresses/(\d+)/default', ['customer'], function ($id) use ($pdo) {
    $user = require_roles(['customer']);
    verify_csrf_or_die();

    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
    $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);

    flash('success', 'آدرس پیش‌فرض تغییر کرد.');
    redirect('/profile/addresses');
});
