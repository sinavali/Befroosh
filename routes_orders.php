<?php
declare(strict_types=1);

/**
 * routes_orders.php
 * Master Order Router & Order Listing with Multi-Tenant Scoping and Bulk Printing
 */

require_once __DIR__ . '/routes_orders_create.php';
require_once __DIR__ . '/routes_orders_detail.php';
require_once __DIR__ . '/routes_orders_process.php';
require_once __DIR__ . '/routes_orders_print.php';
require_once __DIR__ . '/routes_orders_track.php';
require_once __DIR__ . '/routes_orders_report.php';

// Order List View (/orders and alias /profile/orders)
route('GET', '/(?:profile/)?orders(?:\.php)?', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    release_expired_reservations();

    $status = $_GET['status'] ?? '';
    $paymentStatus = $_GET['payment_status'] ?? '';
    $shopFilter = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    [$sort, $dir] = get_sort(['id', 'created_at', 'amount', 'status'], 'id');

    $where = "WHERE 1=1";
    $params = [];

    // Tenant scoping
    if ($user['role'] === 'customer') {
        $where .= " AND o.customer_id = ?";
        $params[] = (int)$user['id'];
    } elseif (in_array($user['role'], ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager'], true)) {
        [$mgmtShopId] = get_current_management_shop($user);
        $where .= " AND o.shop_id = ?";
        $params[] = $mgmtShopId;
        $activeBranchId = active_branch_id();
        if ($activeBranchId) {
            $where .= " AND o.branch_id = ?";
            $params[] = $activeBranchId;
        }
    } elseif ($shopFilter > 0) {
        $where .= " AND o.shop_id = ?";
        $params[] = $shopFilter;
    }

    if ($status !== '') {
        $where .= " AND o.status = ?";
        $params[] = $status;
    }

    if ($paymentStatus !== '') {
        $where .= " AND o.payment_status = ?";
        $params[] = $paymentStatus;
    }

    if ($search !== '') {
        if ($user['role'] === 'customer') {
            $where .= " AND o.uuid LIKE ?";
            $params[] = "%$search%";
        } else {
            $where .= " AND (o.uuid LIKE ? OR u.nickname LIKE ? OR u.phone LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders o 
        JOIN users u ON u.id = o.customer_id 
        LEFT JOIN shops s ON s.id = o.shop_id 
        $where
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $orderSql = match ($sort) {
        'created_at' => 'o.created_at',
        'status' => 'o.status',
        'amount' => 'COALESCE(o.final_total, o.estimated_total)',
        default => 'o.id',
    };

    $stmt = $pdo->prepare("
        SELECT o.*, u.nickname AS customer_nickname, u.phone AS customer_phone, s.name AS shop_name, s.slug AS shop_slug
        FROM orders o
        JOIN users u ON u.id = o.customer_id
        LEFT JOIN shops s ON s.id = o.shop_id
        $where
        ORDER BY {$orderSql} {$dir}
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $shops = all_active_shops();

    layout_start('سفارشات', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1><?= $user['role'] === 'customer' ? 'سفارشات من' : 'مدیریت سفارشات' ?></h1>
                <div class="page-sub">
                    <?= $user['role'] === 'customer' ? 'مشاهده وضعیت سفارشات ثبت شده در تمامی فروشگاه‌ها' : 'بررسی فیش‌های واریزی، تایید، ارسال و چاپ فاکتورها' ?>
                </div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/orders/track"><?= icon('search', 14) ?> پیگیری با کد رهگیری</a>
            <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش جدید</a>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجوی کد سفارش، مشتری..." value="<?= e($search) ?>" style="min-width:200px;">

                <?php if (in_array($user['role'], ['superadmin', 'admin'], true)): ?>
                    <select class="select" name="shop_id">
                        <option value="">همه فروشگاه‌ها</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $shopFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select class="select" name="status">
                    <option value="">همه وضعیت‌های سفارش</option>
                    <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>ثبت اولیه (رزرو)</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>پرداخت شده</option>
                    <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>ارسال شده</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                    <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>لغو شده</option>
                </select>

                <select class="select" name="payment_status">
                    <option value="">همه وضعیت‌های پرداخت</option>
                    <option value="unpaid" <?= $paymentStatus === 'unpaid' ? 'selected' : '' ?>>پرداخت نشده</option>
                    <option value="pending_verification" <?= $paymentStatus === 'pending_verification' ? 'selected' : '' ?>>در انتظار تایید فیش</option>
                    <option value="paid" <?= $paymentStatus === 'paid' ? 'selected' : '' ?>>پرداخت شده و تایید</option>
                    <option value="rejected" <?= $paymentStatus === 'rejected' ? 'selected' : '' ?>>فیش رد شده</option>
                </select>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <?php if ($search !== '' || $status !== '' || $paymentStatus !== '' || $shopFilter > 0): ?>
                    <a class="btn btn-ghost" href="/orders">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ORDERS TABLE WITH BULK PRINT FORM -->
    <form method="post" action="/orders/bulk-print">
        <?= csrf_field() ?>

        <?php if ($user['role'] !== 'customer' && !empty($orders)): ?>
            <div class="card mb-2" style="background:#f8fafc; padding:8px 14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div style="font-size:0.85rem; color:#475569;">
                    عملیات چاپ گروهی برای سفارشات انتخاب شده:
                </div>
                <div style="display:flex; gap:6px;">
                    <button type="submit" name="print_type" value="invoice" formtarget="_blank" class="btn btn-outline btn-sm">
                        <?= icon('print', 13) ?> چاپ گروهی فاکتور رسمی
                    </button>
                    <button type="submit" name="print_type" value="shipping_label" formtarget="_blank" class="btn btn-outline btn-sm">
                        چاپ گروهی برچسب پستی
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($user['role'] !== 'customer'): ?>
                                <th style="width:30px;"><input type="checkbox" id="check-all" title="انتخاب همه"></th>
                            <?php endif; ?>
                            <th>شماره سفارش</th>
                            <?php if ($user['role'] === 'customer'): ?>
                                <th>فروشگاه</th>
                            <?php else: ?>
                                <th>فروشگاه</th>
                                <th>مشتری</th>
                            <?php endif; ?>
                            <th><?= sort_link('/orders', 'وضعیت', 'status', $sort, $dir) ?></th>
                            <th>پرداخت</th>
                            <th><?= sort_link('/orders', 'مبلغ کل', 'amount', $sort, $dir) ?></th>
                            <th><?= sort_link('/orders', 'تاریخ ثبت', 'created_at', $sort, $dir) ?></th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="<?= $user['role'] === 'customer' ? 7 : 9 ?>"><?= empty_state('هیچ سفارشی یافت نشد') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <?php $seenField = $user['role'] === 'customer' ? 'seen_by_customer' : 'seen_by_admin'; ?>
                            <tr>
                                <?php if ($user['role'] !== 'customer'): ?>
                                    <td><input type="checkbox" name="order_ids[]" value="<?= (int)$o['id'] ?>" class="order-checkbox"></td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= e($o['uuid']) ?></strong>
                                    <?php if (!$o[$seenField]): ?><span class="badge badge-rose">جدید</span><?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-gray"><?= e($o['shop_name'] ?: 'مرکزی') ?></span>
                                </td>
                                <?php if ($user['role'] !== 'customer'): ?>
                                    <td>
                                        <?= e($o['customer_nickname']) ?>
                                        <div style="font-size:0.72rem; color:#6b7280;" dir="ltr"><?= format_phone($o['customer_phone']) ?></div>
                                    </td>
                                <?php endif; ?>
                                <td><?= order_badge($o['status']) ?></td>
                                <td><?= payment_badge($o['payment_status']) ?></td>
                                <td><?= format_irr((float)($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                <td class="date-cell"><?= format_jalali($o['created_at']) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int)$o['id'] ?>">مشاهده</a>
                                        <a class="btn btn-ghost btn-sm" href="/orders/<?= (int)$o['id'] ?>/print" target="_blank" title="چاپ فاکتور"><?= icon('print', 13) ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?= pagination_html('/orders', $pag['page'], $pag['pages']) ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAll = document.getElementById('check-all');
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                document.querySelectorAll('.order-checkbox').forEach(cb => { cb.checked = checkAll.checked; });
            });
        }
    });
    </script>
    <?php
    layout_end();
});

// Re-order POST /orders/{id}/reorder
route('POST', '/orders/(\d+)/reorder', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) redirect('/orders');

    if ($user['role'] === 'customer' && (int)$old['customer_id'] !== (int)$user['id']) {
        redirect('/orders');
    }

    $shopId = (int)($old['shop_id'] ?: 1);
    $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $items->execute([$id]);

    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $pId = (int)$it['product_id'];
        $qty = (float)$it['quantity'];

        $chk = $pdo->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?");
        $chk->execute([$user['id'], $pId]);
        if (!$chk->fetch()) {
            $pdo->prepare("
                INSERT INTO cart_items (user_id, shop_id, product_id, quantity, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ")->execute([$user['id'], $shopId, $pId, $qty, $expiresAt]);
        }
    }

    flash('success', 'اقلام سفارش به سبد خرید اضافه شدند.');
    redirect('/cart?shop_id=' . $shopId);
});