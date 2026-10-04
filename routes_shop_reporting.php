<?php
declare(strict_types=1);

/**
 * routes_shop_reporting.php
 * Comprehensive Merchant & Platform Reporting Center
 * KPIs, VAT Regulatory Report, Sales Trends, Top Products, and Inventory Turnover
 */

route('GET', '/reports(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $shop = get_current_management_shop($user);
    $shopId = (int)$shop['id'];

    $fromInput = trim($_GET['from'] ?? '');
    $toInput = trim($_GET['to'] ?? '');

    $fromUtc = $fromInput ? jalali_to_utc($fromInput, false) : null;
    $toUtc = $toInput ? jalali_to_utc($toInput, true) : null;

    $dateWhere = "";
    $dateParams = [$shopId];

    if ($fromUtc) {
        $dateWhere .= " AND o.created_at >= ?";
        $dateParams[] = $fromUtc;
    }
    if ($toUtc) {
        $dateWhere .= " AND o.created_at <= ?";
        $dateParams[] = $toUtc;
    }

    // 1. Executive Sales & Tax KPIs
    $kpiSql = "
        SELECT 
            COUNT(DISTINCT o.id) AS total_orders,
            COUNT(DISTINCT CASE WHEN o.status IN ('finalised', 'completed') THEN o.id END) AS completed_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) AS cancelled_orders,
            COALESCE(SUM(CASE WHEN o.status IN ('finalised', 'completed') THEN COALESCE(o.final_total, o.estimated_total) ELSE 0 END), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN o.status IN ('finalised', 'completed') THEN o.subtotal ELSE 0 END), 0) AS taxable_sales,
            COALESCE(SUM(CASE WHEN o.status IN ('finalised', 'completed') THEN o.tax_amount ELSE 0 END), 0) AS total_vat,
            COALESCE(SUM(CASE WHEN o.status IN ('finalised', 'completed') THEN o.shipping_cost ELSE 0 END), 0) AS total_shipping
        FROM orders o
        WHERE o.shop_id = ? $dateWhere
    ";
    $kpiStmt = $pdo->prepare($kpiSql);
    $kpiStmt->execute($dateParams);
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    $completedCount = (int)$kpi['completed_orders'];
    $totalRevenue = (float)$kpi['total_revenue'];
    $aov = $completedCount > 0 ? ($totalRevenue / $completedCount) : 0.0;

    // 2. Top Selling Products
    $topProdSql = "
        SELECT 
            p.id, p.title, p.sku,
            COALESCE(SUM(oi.quantity), 0) AS total_qty_sold,
            COALESCE(SUM(oi.line_total), 0) AS total_sales_amount
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        WHERE o.shop_id = ? AND o.status IN ('finalised', 'completed') $dateWhere
        GROUP BY p.id, p.title, p.sku
        ORDER BY total_sales_amount DESC
        LIMIT 7
    ";
    $topStmt = $pdo->prepare($topProdSql);
    $topStmt->execute($dateParams);
    $topProducts = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Inventory Turnover & Alerts
    $invStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_products,
            COALESCE(SUM(stock_quantity), 0) AS total_units_in_stock,
            COUNT(CASE WHEN stock_quantity <= 0 THEN 1 END) AS out_of_stock_count,
            COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity <= COALESCE(min_stock_alert, 3) THEN 1 END) AS low_stock_count
        FROM products
        WHERE shop_id = ? AND deleted_at IS NULL
    ");
    $invStmt->execute([$shopId]);
    $invStats = $invStmt->fetch(PDO::FETCH_ASSOC);

    // 4. Sales Trend (Recent 7 active days)
    $trendStmt = $pdo->prepare("
        SELECT 
            substr(o.created_at, 1, 10) AS order_date,
            COUNT(o.id) AS day_orders,
            COALESCE(SUM(COALESCE(o.final_total, o.estimated_total)), 0) AS day_revenue
        FROM orders o
        WHERE o.shop_id = ? AND o.status IN ('finalised', 'completed')
        GROUP BY substr(o.created_at, 1, 10)
        ORDER BY order_date DESC
        LIMIT 7
    ");
    $trendStmt->execute([$shopId]);
    $salesTrend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مرکز گزارشات و شاخص‌های کلیدی', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>گزارشات مدیریتی و شاخص‌های کلیدی عملکرد (KPIs)</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name']) ?></strong> | تحلیل درآمد، مالیات بر ارزش افزوده و گردش انبار</div>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/orders/report" class="btn btn-outline btn-sm"><?= icon('orders', 14) ?> جزئیات فروش و CSV</a>
            <a href="/accounting/tax-report" class="btn btn-outline btn-sm"><?= icon('report', 14) ?> اظهارنامه مالیاتی (VAT)</a>
        </div>
    </div>

    <!-- DATE FILTER BAR WITH PERSIAN DATEPICKER -->
    <div class="card mb-3">
        <div class="card-body" style="padding:14px 20px;">
            <form method="get" action="/reports" class="filter-bar" style="justify-content:space-between;">
                <div class="flex gap-2" style="flex-wrap:wrap;">
                    <div style="font-size:0.85rem; font-weight:bold; color:#475569; display:flex; align-items:center;">بازه زمانی شمسی:</div>
                    <input type="text" name="from" class="input jdate" value="<?= e($fromInput) ?>" placeholder="از تاریخ (۱۴۰۳/۰۱/۰۱)" style="width:160px; font-size:0.85rem;" autocomplete="off">
                    <input type="text" name="to" class="input jdate" value="<?= e($toInput) ?>" placeholder="تا تاریخ (۱۴۰۳/۱۲/۲۹)" style="width:160px; font-size:0.85rem;" autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', 13) ?> اعمال گزارش</button>
                    <?php if ($fromInput || $toInput): ?>
                        <a href="/reports" class="btn btn-ghost btn-sm">حذف فیلتر</a>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.8rem; color:#94a3b8;">
                    تاریخ امروز: <strong><?= jdate_now() ?></strong>
                </div>
            </form>
        </div>
    </div>

    <!-- 1. EXECUTIVE KPIS -->
    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('orders', 22) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($totalRevenue) ?></div>
                <div class="stat-label">درآمد ناخالص نهایی</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('check', 22) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$completedCount) ?></div>
                <div class="stat-label">سفارشات موفق تکمیل‌شده</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('report', 22) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($aov) ?></div>
                <div class="stat-label">میانگین ارزش هر سفارش (AOV)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><?= icon('report', 22) ?></div>
            <div>
                <div class="stat-value"><?= format_irr((float)$kpi['total_vat']) ?></div>
                <div class="stat-label">مالیات ارزش افزوده وصولی (VAT)</div>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:20px; margin-bottom:24px;">
        <!-- 2. VAT REGULATORY REPORT -->
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <h2>گزارش نظارتی مالیات ارزش افزوده (سامانه مودیان)</h2>
                <a href="/accounting/tax-report" class="btn btn-outline btn-sm">جزئیات کامل</a>
            </div>
            <div class="card-body">
                <table class="table" style="font-size:0.88rem;">
                    <tbody>
                        <tr>
                            <td style="color:#64748b;">مجموع فروش مشمول مالیات:</td>
                            <td style="text-align:left; font-weight:bold;"><?= format_irr((float)$kpi['taxable_sales']) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748b;">نرخ استاندارد مالیات بر ارزش افزوده:</td>
                            <td style="text-align:left; font-weight:bold; color:#2563eb;">۱۰ درصد قانونی</td>
                        </tr>
                        <tr>
                            <td style="color:#64748b;">کل مالیات و عوارض متعلق:</td>
                            <td style="text-align:left; font-weight:bold; color:#059669; font-size:1rem;"><?= format_irr((float)$kpi['total_vat']) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748b;">جمع کل کرایه و هزینه ارسال:</td>
                            <td style="text-align:left; font-weight:bold;"><?= format_irr((float)$kpi['total_shipping']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. INVENTORY TURNOVER & ALERTS -->
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <h2>وضعیت انبار و کالاهای در آستانه اتمام</h2>
                <a href="/inventory" class="btn btn-outline btn-sm">مدیریت انبار</a>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; text-align:center;">
                        <div style="font-size:1.3rem; font-weight:800; color:#0f172a;"><?= en_to_fa_digits((string)$invStats['total_units_in_stock']) ?></div>
                        <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">کل واحدهای موجود کالا</div>
                    </div>
                    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; text-align:center;">
                        <div style="font-size:1.3rem; font-weight:800; color:#dc2626;"><?= en_to_fa_digits((string)$invStats['out_of_stock_count']) ?></div>
                        <div style="font-size:0.75rem; color:#dc2626; margin-top:2px;">کالاهای ناموجود (صفر)</div>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:10px 14px; font-size:0.85rem; color:#92400e;">
                    <span>کالاهای در آستانه کسری (زیر حد هشدار):</span>
                    <strong><?= en_to_fa_digits((string)$invStats['low_stock_count']) ?> قلم کالا</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. TOP SELLING PRODUCTS -->
    <div class="card mb-3">
        <div class="card-header">
            <h2>پرفروش‌ترین کالاها در این بازه</h2>
            <a href="/products" class="btn btn-outline btn-sm">مشاهده کاتالوگ محصولات</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان کالا</th>
                        <th>کد کالا (SKU)</th>
                        <th>تعداد فروخته‌شده</th>
                        <th>مجموع فروش ریالی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:24px;">اطلاعات فروشی در این بازه ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $tp): ?>
                            <tr>
                                <td style="font-weight:bold; color:#0f172a;"><?= e($tp['title']) ?></td>
                                <td><code><?= e($tp['sku'] ?: '—') ?></code></td>
                                <td><span class="badge badge-emerald"><?= en_to_fa_digits((string)$tp['total_qty_sold']) ?> عدد</span></td>
                                <td><strong><?= format_irr((float)$tp['total_sales_amount']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. RECENT SALES TREND TABLE -->
    <div class="card">
        <div class="card-header">
            <h2>روند فروش روزانه اخیر</h2>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>تاریخ روز</th>
                        <th>تعداد سفارشات قطعی</th>
                        <th>درآمد روزانه (ریال)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salesTrend)): ?>
                        <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">هیچ سفارش نهایی در روزهای اخیر ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($salesTrend as $st): ?>
                            <tr>
                                <td><?= jdate_date($st['order_date']) ?></td>
                                <td><?= en_to_fa_digits((string)$st['day_orders']) ?> سفارش</td>
                                <td style="font-weight:bold; color:#059669;"><?= format_irr((float)$st['day_revenue']) ?></td>
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
