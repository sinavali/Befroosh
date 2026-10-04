<?php
declare(strict_types=1);

/**
 * routes_addresses.php
 * Customer delivery address management (list, create, edit, delete, set default)
 */

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
