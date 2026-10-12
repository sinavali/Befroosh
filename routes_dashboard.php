<?php
declare(strict_types=1);

/**
 * routes_dashboard.php
 * Role-Based Dashboard View for Customers and Admins/Staff
 */

route('GET', '/(?:app/)?dashboard(?:\.php)?', [], function () use ($pdo) {
    $user = require_login();

    if ($user['role'] === 'customer') {
        redirect('/my');
    }

    $isStaffRole = in_array($user['role'], ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager'], true);
    $extraKpiLabel = 'مشتری فعال';
    $extraKpiVal = '۰';

    if ($isStaffRole) {
        [$shopId, $shop] = get_current_management_shop($user);
        $activeBranchId = active_branch_id();
        $branch = $activeBranchId ? get_branch($activeBranchId) : null;
        $branchWhere = ($branch ? " AND o.branch_id = {$activeBranchId}" : "");

        if ($branch) {
            $dashTitle = 'داشبورد شعبه ' . ($branch['name'] ?? '') . ' (' . ($shop['name'] ?? '') . ')';
            $dashSub = 'آمار سفارشات، درآمد و فیش‌های واریزی این شعبه';
        } else {
            $dashTitle = 'داشبورد سراسری کسب‌وکار ' . ($shop['name'] ?? '');
            $dashSub = 'دید کلی و تجمیعی تمامی شعب، انبارها و فیش‌های بانکی';
        }

        $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND date(o.created_at) = date('now')")->fetchColumn();
        $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE shop_id = {$shopId} AND status = 'open'")->fetchColumn();
        $pendingReceipts = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.payment_status = 'pending_verification'")->fetchColumn();
        $revenue = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(o.final_total, o.estimated_total)), 0) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.status IN ('finalised','completed','paid')")->fetchColumn();

        $extraKpiLabel = 'فیش در صف تایید';
        $extraKpiVal = en_to_fa_digits((string)$pendingReceipts);

        $recentOrders = $pdo->query("
            SELECT o.*, u.nickname AS customer_nickname
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            WHERE o.shop_id = {$shopId}{$branchWhere}
            ORDER BY o.id DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $dashTitle = 'داشبورد مدیریت کل سامانه';
        $dashSub = 'خلاصه وضعیت سراسری کسب‌وکارها، سفارشات، شعب و تیکت‌ها';

        $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
        $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
        $totalShops = (int) $pdo->query("SELECT COUNT(*) FROM shops WHERE active = 1")->fetchColumn();
        $revenue = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE status IN ('finalised','completed','paid')")->fetchColumn();

        $extraKpiLabel = 'کسب‌وکارهای فعال';
        $extraKpiVal = en_to_fa_digits((string)$totalShops);

        $recentOrders = $pdo->query("
            SELECT o.*, u.nickname AS customer_nickname
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            ORDER BY o.id DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    layout_start($dashTitle, $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('dashboard', 18) ?></div>
            <div>
                <h1><?= e($dashTitle) ?></h1>
                <div class="page-sub"><?= e($dashSub) ?></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/orders?status=all&payment_status=pending_verification"><?= icon('receipt', 14) ?> صف تایید فیش‌ها</a>
            <a class="btn btn-outline" href="/inventory"><?= icon('inventory', 14) ?> انبارداری</a>
            <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">سفارش‌های امروز</span>
                <span class="badge badge-blue">لحظه‌ای</span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $ordersToday) ?></div>
            <div class="metric-sub">تعداد کل سفارش‌های ثبت شده امروز</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">درآمد نهایی / تکمیل‌شده</span>
                <span class="badge badge-emerald">تومان</span>
            </div>
            <div class="metric-value"><?= format_irt($revenue) ?></div>
            <div class="metric-sub">ارزش کل فاکتورهای پرداخت‌شده و تحویل‌شده</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label"><?= e($extraKpiLabel) ?></span>
                <span class="badge badge-amber">عملیاتی</span>
            </div>
            <div class="metric-value"><?= $extraKpiVal ?></div>
            <div class="metric-sub"><?= $isStaffRole ? 'رسیدهای بانکی نیازمند تطبیق و ثبت سند' : 'کسب‌وکارهای فعال در پلتفرم' ?></div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">تیکت‌های باز پشتیبانی</span>
                <span class="badge badge-purple"><?= $openTickets > 0 ? 'نیازمند پاسخ' : 'تکمیل' ?></span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
            <div class="metric-sub">درخواست‌های باز مشتریان و همکاران</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>آخرین سفارشات</h2>
            <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>مشتری</th>
                        <th>وضعیت</th>
                        <th>مبلغ</th>
                        <th>تاریخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr>
                            <td colspan="6">
                                <?= empty_state('سفارشی ثبت نشده است', 'اولین سفارش را از بخش ثبت سفارش ایجاد کنید.') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['uuid']) ?></strong></td>
                                <td><?= e($o['customer_nickname']) ?></td>
                                <td><?= order_badge($o['status']) ?></td>
                                <td><?= format_irr((float) ($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                <td class="date-cell"><?= format_jalali($o['created_at']) ?></td>
                                <td><a class="btn btn-outline btn-sm" href="/orders/<?= (int) $o['id'] ?>">مشاهده</a></td>
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
