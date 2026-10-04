<?php
declare(strict_types=1);

/**
 * routes_categories.php
 * Categories Management for Shop Owners and Managers
 */

route('GET', '/categories(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$shopId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت دسته‌بندی‌ها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('filter', 18) ?></div>
            <div>
                <h1>دسته‌بندی‌های محصولات</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name']) ?></strong></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/products">بازگشت به محصولات</a>
            <button class="btn btn-primary" data-modal="#newCategoryModal"><?= icon('plus', 14) ?> دسته‌بندی جدید</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام دسته‌بندی</th>
                        <th>ترتیب نمایش</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5"><?= empty_state('دسته‌بندی ثبت نشده است') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>#<?= en_to_fa_digits((string)$cat['id']) ?></td>
                            <td><strong><?= e($cat['name']) ?></strong></td>
                            <td><?= en_to_fa_digits((string)$cat['sort_order']) ?></td>
                            <td><?= $cat['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?></td>
                            <td>
                                <form method="post" action="/categories/<?= (int)$cat['id'] ?>/delete" class="inline-form" data-confirm="این دسته‌بندی حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?> حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- NEW CATEGORY MODAL -->
    <div class="modal-backdrop" id="newCategoryModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/categories/create">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>افزودن دسته‌بندی جدید</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>نام دسته‌بندی *</label>
                        <input class="input" name="name" required placeholder="مثال: پوشاک مردانه">
                    </div>
                    <div class="form-group">
                        <label>ترتیب نمایش</label>
                        <input class="input" type="number" name="sort_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ایجاد دسته‌بندی</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

route('POST', '/categories/create', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    [$shopId] = get_current_management_shop($user);

    $name = trim($_POST['name'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $slug = make_utf8_slug($name);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO categories (shop_id, name, slug, sort_order, active, created_at) VALUES (?, ?, ?, ?, 1, datetime('now'))");
        $stmt->execute([$shopId, $name, $slug, $sortOrder]);
        flash('success', 'دسته‌بندی با موفقیت ایجاد شد.');
    }
    redirect('/categories');
});

route('POST', '/categories/(\d+)/delete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    [$shopId] = get_current_management_shop($user);
    $id = (int)$id;

    $pdo->prepare("DELETE FROM categories WHERE id = ? AND shop_id = ?")->execute([$id, $shopId]);
    flash('success', 'دسته‌بندی حذف شد.');
    redirect('/categories');
});
