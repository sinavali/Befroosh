<?php
declare(strict_types=1);

/**
 * routes_dashboard.php
 * Role-Based Dashboard View for Customers and Admins/Staff
 */

route('GET', '/(?:app/)?dashboard(?:\.php)?', [], function () use ($pdo) {
    $user = require_login();

    if ($user['role'] === 'customer') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $stmt->execute([$user['id']]);
        $totalOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'placed'");
        $stmt->execute([$user['id']]);
        $placedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'finalised'");
        $stmt->execute([$user['id']]);
        $finalisedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'completed'");
        $stmt->execute([$user['id']]);
        $completedOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE customer_id = ? AND status IN ('finalised','completed')");
        $stmt->execute([$user['id']]);
        $totalSpend = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND status = 'open'");
        $stmt->execute([$user['id']]);
        $openTickets = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        layout_public_start('داشبورد من', null, $user);
        ?>
        <div class="page-header" style="margin-bottom:20px;">
            <div class="page-title-wrap">
                <div class="page-icon"><?= icon('dashboard', 18) ?></div>
                <div>
                    <h1 style="font-size:1.35rem; font-weight:800; color:#0f172a;">داشبورد من</h1>
                    <div class="page-sub">خلاصه سفارشات و درخواست‌های شما</div>
                </div>
            </div>

            <div class="action-cluster">
                <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش جدید</a>
                <a class="btn btn-outline" href="/tickets/create"><?= icon('tickets', 14) ?> تیکت جدید</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $totalOrders) ?></div>
                    <div class="stat-label">کل سفارشات</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber"><?= icon('clock', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $placedOrders) ?></div>
                    <div class="stat-label">در انتظار نهایی‌سازی</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon emerald"><?= icon('check', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $completedOrders) ?></div>
                    <div class="stat-label">تکمیل شده</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('report', 18) ?></div>
                <div>
                    <div class="stat-value"><?= format_irr($totalSpend) ?></div>
                    <div class="stat-label">مجموع خرید نهایی</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon rose"><?= icon('tickets', 18) ?></div>
                <div>
                    <div class="stat-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
                    <div class="stat-label">تیکت باز</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h2>آخرین سفارش شما</h2>
                <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه سفارشات</a>
            </div>
            <div class="card-body">
                <?php if (!$lastOrder): ?>
                    <?= empty_state('هنوز سفارشی ثبت نکرده‌اید', 'اولین سفارش خود را از دکمه «ثبت سفارش جدید» ایجاد کنید.') ?>
                <?php else: ?>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">شماره سفارش</div>
                            <div class="detail-value"><?= e($lastOrder['uuid']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">وضعیت</div>
                            <div class="detail-value"><?= order_badge($lastOrder['status']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">تاریخ ثبت</div>
                            <div class="detail-value date-cell"><?= format_jalali($lastOrder['created_at']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">مبلغ</div>
                            <div class="detail-value">
                                <?= format_irr((float) ($lastOrder['final_total'] ?? $lastOrder['estimated_total'])) ?></div>
                        </div>
                    </div>

                    <div class="mt-2">
                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int) $lastOrder['id'] ?>">مشاهده جزئیات</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>سفارشات اخیر</h2>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>وضعیت</th>
                            <th>مبلغ</th>
                            <th>تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentOrders): ?>
                            <tr>
                                <td colspan="5"><?= empty_state('سفارشی یافت نشد') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><strong><?= e($o['uuid']) ?></strong></td>
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
        layout_public_end(null);
        return;
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
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string) $ordersToday) ?></div>
                <div class="stat-label">سفارش امروز</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irt($revenue) ?></div>
                <div class="stat-label">درآمد نهایی/تکمیل‌شده</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('customers', 18) ?></div>
            <div>
                <div class="stat-value"><?= $extraKpiVal ?></div>
                <div class="stat-label"><?= e($extraKpiLabel) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><?= icon('tickets', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
                <div class="stat-label">تیکت باز</div>
            </div>
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
