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

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:16px;">
        <?php if (empty($categories)): ?>
            <div style="grid-column: 1 / -1;"><?= empty_state('دسته‌بندی ثبت نشده است') ?></div>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <div class="card" style="padding:16px; border-radius:12px; display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h3 style="margin:0; font-size:1.1rem; color:#0f172a; font-weight:bold;"><?= e($cat['name']) ?></h3>
                            <?= $cat['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?>
                        </div>
                        <div style="font-size:0.85rem; color:#64748b; margin-bottom:16px;">
                            ترتیب نمایش: <?= en_to_fa_digits((string)$cat['sort_order']) ?>
                        </div>
                    </div>
                    <div style="border-top:1px solid #e2e8f0; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.8rem; color:#94a3b8;">شناسه: #<?= en_to_fa_digits((string)$cat['id']) ?></span>
                        <form method="post" action="/categories/<?= (int)$cat['id'] ?>/delete" class="inline-form" data-confirm="این دسته‌بندی حذف شود؟">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?> حذف</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
