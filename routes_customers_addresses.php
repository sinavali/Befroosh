<?php
declare(strict_types=1);

/**
 * routes_customers_addresses.php
 * Customer Delivery Addresses Management (List, Add, Delete)
 */

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
