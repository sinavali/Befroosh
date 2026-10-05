<?php
declare(strict_types=1);

/**
 * routes_orders_report.php
 * Tenant-scoped order analytics & reporting with SQLite chunking for large datasets
 */

route('GET', '/orders/report(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);

    $dateType = $_GET['date_type'] ?? 'created';
    if (!in_array($dateType, ['created', 'finalised'], true)) {
        $dateType = 'created';
    }

    $fromInput = trim($_GET['from'] ?? '');
    $toInput = trim($_GET['to'] ?? '');

    $fromUtc = jalali_to_utc($fromInput, false);
    $toUtc = jalali_to_utc($toInput, true);

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $productId = (int)($_GET['product_id'] ?? 0);
    $shopFilter = (int)($_GET['shop_id'] ?? 0);
    $status = $_GET['status'] ?? '';
    $orderNo = trim($_GET['order_no'] ?? '');

    $where = "WHERE 1=1";
    $params = [];

    // Tenant scoping
    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true)) {
        $where .= " AND o.shop_id = ?";
        $params[] = (int)($user['shop_id'] ?? 1);
    } elseif ($shopFilter > 0) {
        $where .= " AND o.shop_id = ?";
        $params[] = $shopFilter;
    }

    if ($dateType === 'finalised') {
        $where .= " AND o.finalised_at IS NOT NULL";
    }

    if ($fromUtc) {
        $where .= " AND o." . $dateType . "_at >= ?";
        $params[] = $fromUtc;
    }

    if ($toUtc) {
        $where .= " AND o." . $dateType . "_at <= ?";
        $params[] = $toUtc;
    }

    if ($customerId > 0) {
        $where .= " AND o.customer_id = ?";
        $params[] = $customerId;
    }

    if ($status !== '') {
        $where .= " AND o.status = ?";
        $params[] = $status;
    }

    if ($orderNo !== '') {
        $where .= " AND o.uuid LIKE ?";
        $params[] = "%$orderNo%";
    }

    if ($productId > 0) {
        $where .= " AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.product_id = ?)";
        $params[] = $productId;
    }

    $sql = "
        SELECT o.*, u.nickname AS customer_nickname, s.name AS shop_name 
        FROM orders o 
        JOIN users u ON u.id = o.customer_id 
        LEFT JOIN shops s ON s.id = o.shop_id 
        $where 
        ORDER BY o.id DESC
    ";

    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="orders_report.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($out, ['شماره سفارش', 'فروشگاه', 'مشتری', 'وضعیت', 'وضعیت پرداخت', 'تاریخ', 'مبلغ کل']);

        foreach ($orders as $o) {
            fputcsv($out, [
                $o['uuid'],
                $o['shop_name'] ?: 'مرکزی',
                $o['customer_nickname'],
                order_status_fa($o['status']),
                payment_status_fa($o['payment_status']),
                format_jalali($o[$dateType . '_at'] ?? $o['created_at']),
                (string)($o['final_total'] ?? $o['estimated_total']),
            ]);
        }
        fclose($out);
        exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalOrders = count($orders);
    $totalRevenue = 0.0;
    $totalQty = 0.0;

    // Safe chunked aggregation avoiding SQLite 999 parameter limit
    if (!empty($orders)) {
        $ids = array_column($orders, 'id');
        $qtyMap = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $cStmt = $pdo->prepare("SELECT order_id, SUM(quantity) AS qty FROM order_items WHERE order_id IN ($placeholders) GROUP BY order_id");
            $cStmt->execute($chunk);
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $qtyMap[(int)$row['order_id']] = (float)$row['qty'];
            }
        }

        foreach ($orders as $o) {
            if ($o['status'] !== 'canceled') {
                $totalRevenue += (float)($o['final_total'] ?? $o['estimated_total']);
            }
            $totalQty += $qtyMap[(int)$o['id']] ?? 0.0;
        }
    }

    $customers = $pdo->query("SELECT id, nickname FROM users WHERE role = 'customer' AND deleted_at IS NULL ORDER BY nickname ASC")->fetchAll(PDO::FETCH_ASSOC);
    $products = $pdo->query("SELECT id, title FROM products WHERE deleted_at IS NULL ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
    $shops = all_active_shops();

    $exportParams = $_GET;
    unset($exportParams['export']);
    $exportUrl = '/orders/report?export=csv&' . http_build_query($exportParams);

    layout_start('گزارش و تحلیل سفارشات', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>گزارش و تحلیل سفارشات</h1>
                <div class="page-sub">فیلتر، آمار فروش، تحلیل اقلام و خروجی اکسل / CSV</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="get">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>نوع تاریخ</label>
                        <select class="select" name="date_type">
                            <option value="created" <?= $dateType === 'created' ? 'selected' : '' ?>>تاریخ ثبت</option>
                            <option value="finalised" <?= $dateType === 'finalised' ? 'selected' : '' ?>>تاریخ نهایی‌سازی/پرداخت</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>از تاریخ (شمسی)</label>
                        <input class="input jdate" type="text" name="from" value="<?= e($fromInput) ?>" placeholder="۱۴۰۳/۰۱/۰۱">
                    </div>

                    <div class="form-group">
                        <label>تا تاریخ (شمسی)</label>
                        <input class="input jdate" type="text" name="to" value="<?= e($toInput) ?>" placeholder="۱۴۰۳/۱۲/۲۹">
                    </div>

                    <?php if (in_array($user['role'], ['superadmin', 'admin'], true)): ?>
                        <div class="form-group">
                            <label>فروشگاه</label>
                            <select class="select" name="shop_id">
                                <option value="">همه فروشگاه‌ها</option>
                                <?php foreach ($shops as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= $shopFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>مشتری</label>
                        <select class="select" name="customer_id">
                            <option value="">همه مشتریان</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nickname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>وضعیت سفارش</label>
                        <select class="select" name="status">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>ثبت اولیه (رزرو)</option>
                            <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>پرداخت شده</option>
                            <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>ارسال شده</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                            <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>لغو شده</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>شماره سفارش</label>
                        <input class="input" type="text" name="order_no" value="<?= e($orderNo) ?>" placeholder="کد ۸ رقمی">
                    </div>
                </div>

                <div class="action-cluster mt-3">
                    <button class="btn btn-primary"><?= icon('filter', 14) ?> اعمال فیلتر</button>
                    <a class="btn btn-success" href="<?= e($exportUrl) ?>"><?= icon('download', 14) ?> خروجی CSV (اکسل)</a>
                    <button type="button" class="btn btn-outline" onclick="window.print()"><?= icon('print', 14) ?> چاپ گزارش</button>
                    <a class="btn btn-ghost" href="/orders/report">حذف فیلترها</a>
                </div>
            </form>
        </div>
    </div>

    <!-- STATS SUMMARY -->
    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$totalOrders) ?></div>
                <div class="stat-label">تعداد سفارشات</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($totalRevenue) ?></div>
                <div class="stat-label">مجموع فروش معتبر (بدون لغو شده‌ها)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('products', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$totalQty) ?></div>
                <div class="stat-label">تعداد اقلام فروخته شده</div>
            </div>
        </div>
    </div>

    <!-- ORDERS TABLE -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>فروشگاه</th>
                        <th>مشتری</th>
                        <th>وضعیت</th>
                        <th>پرداخت</th>
                        <th>تاریخ</th>
                        <th>مبلغ کل</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="8"><?= empty_state('هیچ سفارشی یافت نشد') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['uuid']) ?></strong></td>
                                <td><?= e($o['shop_name'] ?: 'مرکزی') ?></td>
                                <td><?= e($o['customer_nickname']) ?></td>
                                <td><?= order_badge($o['status']) ?></td>
                                <td><?= payment_badge($o['payment_status']) ?></td>
                                <td class="date-cell"><?= format_jalali($o[$dateType . '_at'] ?? $o['created_at']) ?></td>
                                <td><?= format_irr((float)($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                <td><a class="btn btn-outline btn-sm" href="/orders/<?= (int)$o['id'] ?>">مشاهده</a></td>
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
