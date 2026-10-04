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

require_once __DIR__ . '/routes_shops_edit.php';
