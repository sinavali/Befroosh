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

// Order List View (/orders, /my/orders, /merchant/orders, and alias /profile/orders)
route('GET', '/(?:my/|profile/|merchant/)?orders(?:\.php)?', ['customer', 'business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function () use ($pdo) {
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
    $mgmtShopId = 0;
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
        $mgmtShopId = $shopFilter;
    }

    // KPI Metrics for Merchants / Staff
    $isMerchant = $user['role'] !== 'customer';
    $kpis = [
        'today_gmv' => 0.0,
        'pending_slips' => 0,
        'low_stock' => 0,
        'expiring_reservations' => 0
    ];

    if ($isMerchant) {
        $shopKpiClause = $mgmtShopId > 0 ? "AND shop_id = {$mgmtShopId}" : "";
        try {
            $gmvStmt = $pdo->query("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE status IN ('paid', 'shipped', 'completed') {$shopKpiClause} AND date(created_at) = date('now')");
            $kpis['today_gmv'] = (float)$gmvStmt->fetchColumn();

            $slipsStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending_verification' {$shopKpiClause}");
            $kpis['pending_slips'] = (int)$slipsStmt->fetchColumn();

            $lowStmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_alert AND deleted_at IS NULL {$shopKpiClause}");
            $kpis['low_stock'] = (int)$lowStmt->fetchColumn();

            $expStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'submitted' AND reservation_expires_at IS NOT NULL AND reservation_expires_at >= datetime('now') AND reservation_expires_at <= datetime('now', '+24 hours') {$shopKpiClause}");
            $kpis['expiring_reservations'] = (int)$expStmt->fetchColumn();
        } catch (Throwable) {}
    }

    $merchantCards = $isMerchant ? get_shop_active_cards($mgmtShopId > 0 ? $mgmtShopId : active_shop_id()) : [];

    $branchFilter = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    if ($branchFilter > 0) {
        $where .= " AND o.branch_id = ?";
        $params[] = $branchFilter;
    }

    $fromJdate = trim($_GET['from_jdate'] ?? '');
    $toJdate = trim($_GET['to_jdate'] ?? '');
    if ($fromJdate !== '') {
        $utcFrom = jalali_to_utc($fromJdate, false);
        if ($utcFrom) {
            $where .= " AND o.created_at >= ?";
            $params[] = $utcFrom;
        }
    }
    if ($toJdate !== '') {
        $utcTo = jalali_to_utc($toJdate, true);
        if ($utcTo) {
            $where .= " AND o.created_at <= ?";
            $params[] = $utcTo;
        }
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

    // CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $user['role'] !== 'customer') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders_export_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['شناسه', 'کد سفارش', 'فروشگاه', 'خریدار', 'تلفن', 'وضعیت', 'پرداخت', 'مبلغ کل (تومان)', 'تاریخ ثبت']);
        $expStmt = $pdo->prepare("
            SELECT o.*, u.nickname AS customer_nickname, u.phone AS customer_phone, s.name AS shop_name
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            LEFT JOIN shops s ON s.id = o.shop_id
            $where
            ORDER BY o.id DESC
        ");
        $expStmt->execute($params);
        while ($r = $expStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'],
                $r['uuid'],
                $r['shop_name'] ?: 'مرکزی',
                $r['customer_nickname'],
                $r['customer_phone'],
                $r['status'],
                $r['payment_status'],
                (float)($r['final_total'] ?: $r['estimated_total']),
                format_jalali($r['created_at'])
            ]);
        }
        fclose($out);
        exit;
    }

    // Fetch active branches of business for filter dropdown
    $branchOptions = [];
    if ($isMerchant) {
        $sId = $mgmtShopId > 0 ? $mgmtShopId : active_shop_id();
        try {
            $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE business_id = ? AND active = 1 ORDER BY is_main DESC, name ASC");
            $bStmt->execute([$sId]);
            $branchOptions = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {}
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
 
    if ($user['role'] === 'customer') {
        layout_customer_start('سفارش‌های من', 'orders', $user);
    } else {
        layout_start('سفارشات', $user);
    }
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1><?= $user['role'] === 'customer' ? 'سفارشات من' : 'عملیات سفارشات و صف بررسی فیش‌ها' ?></h1>
                <div class="page-sub">
                    <?= $user['role'] === 'customer' ? 'مشاهده وضعیت سفارشات ثبت شده در تمامی فروشگاه‌ها' : 'میز عملیات مرسولات، بررسی فیش‌های واریزی کارت‌به‌کارت و صدور اسناد دوبل حسابداری' ?>
                </div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/orders/track"><?= icon('search', 14) ?> پیگیری با کد رهگیری</a>
            <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش جدید</a>
        </div>
    </div>

    <?php if ($isMerchant): ?>
        <!-- KPI METRICS GRID -->
        <div class="stats-grid mb-3">
            <div class="stat-card">
                <div class="stat-label">فروش خالص امروز (GMV)</div>
                <div class="stat-value tabular-nums"><?= format_irt($kpis['today_gmv']) ?></div>
                <div class="stat-subtext" style="font-size:0.75rem; color:var(--muted); margin-top:4px;">تراکنش‌های قطعی دفاتر</div>
            </div>

            <a href="/orders?payment_status=pending_verification" class="stat-card" style="text-decoration:none;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="stat-label">فیش‌های در انتظار بررسی</div>
                    <?php if ($kpis['pending_slips'] > 0): ?>
                        <span class="badge badge-amber" style="font-size:0.7rem; font-weight:600;">اقدام فوری</span>
                    <?php endif; ?>
                </div>
                <div class="stat-value tabular-nums" style="color:var(--amber);"><?= en_to_fa_digits((string)$kpis['pending_slips']) ?></div>
                <div class="stat-subtext" style="font-size:0.75rem; color:var(--muted); margin-top:4px;">واریز کارت‌به‌کارت نیازمند تطبیق</div>
            </a>

            <a href="/inventory?low_stock=1" class="stat-card" style="text-decoration:none;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="stat-label">کالاهای با موجودی بحرانی</div>
                    <?php if ($kpis['low_stock'] > 0): ?>
                        <span class="badge badge-rose" style="font-size:0.7rem; font-weight:600;">کسری انبار</span>
                    <?php endif; ?>
                </div>
                <div class="stat-value tabular-nums" style="color:var(--crimson);"><?= en_to_fa_digits((string)$kpis['low_stock']) ?></div>
                <div class="stat-subtext" style="font-size:0.75rem; color:var(--muted); margin-top:4px;">موجودی کمتر از حد آستانه</div>
            </a>

            <a href="/orders?status=submitted" class="stat-card" style="text-decoration:none;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="stat-label">رزروهای در آستانه انقضا</div>
                    <?php if ($kpis['expiring_reservations'] > 0): ?>
                        <span class="badge badge-blue" style="font-size:0.7rem; font-weight:600;">مهلت ۲۴h</span>
                    <?php endif; ?>
                </div>
                <div class="stat-value tabular-nums" style="color:var(--primary);"><?= en_to_fa_digits((string)$kpis['expiring_reservations']) ?></div>
                <div class="stat-subtext" style="font-size:0.75rem; color:var(--muted); margin-top:4px;">سفارش‌های در صف لغو خودکار</div>
            </a>
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجوی کد سفارش، مشتری..." value="<?= e($search) ?>" style="min-width:180px;">

                <?php if (in_array($user['role'], ['superadmin', 'admin'], true)): ?>
                    <select class="select" name="shop_id">
                        <option value="">همه فروشگاه‌ها</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $shopFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if (!empty($branchOptions)): ?>
                    <select class="select" name="branch_id">
                        <option value="">همه شعب</option>
                        <?php foreach ($branchOptions as $bo): ?>
                            <option value="<?= (int)$bo['id'] ?>" <?= $branchFilter === (int)$bo['id'] ? 'selected' : '' ?>><?= e($bo['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select class="select" name="status">
                    <option value="">همه وضعیت‌های سفارش</option>
                    <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>ثبت اولیه (رزرو ۴ روزه)</option>
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

                <!-- Shamsi Date Range Inputs -->
                <input class="input jdate" type="text" name="from_jdate" placeholder="از تاریخ..." value="<?= e($fromJdate) ?>" style="max-width:130px;" autocomplete="off">
                <input class="input jdate" type="text" name="to_jdate" placeholder="تا تاریخ..." value="<?= e($toJdate) ?>" style="max-width:130px;" autocomplete="off">

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <?php if ($search !== '' || $status !== '' || $paymentStatus !== '' || $shopFilter > 0 || $branchFilter > 0 || $fromJdate !== '' || $toJdate !== ''): ?>
                    <a class="btn btn-ghost" href="/orders">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ORDERS TABLE WITH BULK PRINT & BATCH ACTIONS FORM -->
    <form method="post" action="/orders/bulk-print">
        <?= csrf_field() ?>

        <?php if ($user['role'] !== 'customer' && !empty($orders)): ?>
            <div class="card mb-2" style="background:#f8fafc; padding:10px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border:1px solid var(--border);">
                <div style="font-size:0.85rem; color:var(--slate); font-weight:600;">
                    عملیات دسته‌ای برای سفارش‌های انتخاب‌شده:
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <button type="submit" name="print_type" value="invoice" formtarget="_blank" class="btn btn-outline btn-sm">
                        <?= icon('print', 13) ?> چاپ فاکتورها
                    </button>
                    <button type="submit" name="print_type" value="shipping_label" formtarget="_blank" class="btn btn-outline btn-sm">
                        چاپ بارکد و برچسب پستی
                    </button>
                    <?php
                    $csvParams = $_GET;
                    $csvParams['export'] = 'csv';
                    $csvUrl = '/orders?' . http_build_query($csvParams);
                    ?>
                    <a href="<?= e($csvUrl) ?>" class="btn btn-outline btn-sm">
                        خروجی اکسل / CSV
                    </a>
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
                            <?php
                            $seenField = $user['role'] === 'customer' ? 'seen_by_customer' : 'seen_by_admin';
                            $receiptImg = $o['receipt_image_path'] ?: $o['payment_receipt_path'];
                            $hasReceipt = !empty($receiptImg);
                            $isPendingVerification = ($o['payment_status'] === 'pending_verification');
                            ?>
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
                                <td class="tabular-nums" style="font-weight:700;"><?= format_irt((float)($o['final_total'] ?? $o['estimated_total'])) ?></td>
                                <td class="date-cell tabular-nums"><?= format_jalali($o['created_at']) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <?php if ($isMerchant && ($isPendingVerification || $hasReceipt)): ?>
                                            <?php
                                            $slipPayload = [
                                                'id' => (int)$o['id'],
                                                'uuid' => $o['uuid'],
                                                'customer' => $o['customer_nickname'],
                                                'phone' => $o['customer_phone'],
                                                'amount' => format_irt((float)($o['final_total'] ?? $o['estimated_total'])),
                                                'subtotal' => format_irt((float)($o['subtotal'] ?: ($o['final_total'] ?? $o['estimated_total']))),
                                                'shipping' => format_irt((float)($o['shipping_cost'] ?? 0)),
                                                'ref' => $o['payment_reference'] ?: 'ثبت نشده',
                                                'img' => $receiptImg ? '/storage/' . $receiptImg : '',
                                                'card_id' => (int)($o['payment_card_id'] ?? 0),
                                            ];
                                            ?>
                                            <button type="button" class="btn btn-sm btn-outline" style="color:var(--primary); font-weight:700;" data-slip='<?= json_encode($slipPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>' onclick="window.inspectSlip(JSON.parse(this.dataset.slip))">
                                                بررسی فیش
                                            </button>
                                        <?php endif; ?>
                                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int)$o['id'] ?>">مشاهده</a>
                                        <a class="btn btn-ghost btn-sm" href="/orders/<?= (int)$o['id'] ?>/print" target="_blank" title="چاپ فاکتور">چاپ</a>
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

    <!-- SPLIT-VIEW SLIP INSPECTION MODAL -->
    <?php if ($isMerchant): ?>
        <div id="slipInspectorModal" class="modal-overlay" onclick="if(event.target===this) window.closeSlipModal();">
            <div class="modal-sheet">
                <div class="card-header" style="background:#f8fafc;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <h2 style="margin:0; font-size:1.05rem;">بررسی و راستی‌آزمایی فیش کارت‌به‌کارت (<span id="inspectOrderUuid" class="font-mono"></span>)</h2>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="window.closeSlipModal()" aria-label="بستن" style="font-size:1.2rem; line-height:1;">&times;</button>
                </div>
                <div class="split-inspect-grid">
                    <!-- Left Pane: Slip Image with Zoom & Optical Checks -->
                    <div style="background:var(--surface-muted); border:1px solid var(--border); border-radius:var(--radius); padding:16px; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:360px; position:relative; overflow:hidden;" id="inspectImageContainer">
                        <div id="inspectImageWrapper" style="overflow:auto; max-height:360px; width:100%; text-align:center;">
                            <img id="inspectReceiptImg" src="" alt="رسید واریز" style="max-width:100%; max-height:320px; object-fit:contain; border-radius:8px; display:none; box-shadow:var(--shadow-whisper); transition:transform 0.25s ease; cursor:zoom-in;" onclick="window.toggleSlipZoom()">
                        </div>
                        <div id="inspectPdfBox" style="display:none; text-align:center; padding:20px;">
                            <div style="font-weight:700; color:var(--ink); margin-bottom:4px;">سند فیش در قالب فایل PDF بارگذاری شده است</div>
                            <a id="inspectPdfLink" href="#" target="_blank" class="btn btn-primary btn-sm" style="margin-top:8px;">
                                مشاهده فایل PDF رسید
                            </a>
                        </div>
                        <div id="inspectReceiptEmpty" style="color:var(--muted); font-size:0.85rem; text-align:center;">
                            تصویر فیش توسط خریدار بارگذاری نشده است (ثبت صرفاً شماره پیگیری).
                        </div>
                        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; justify-content:center;">
                            <button type="button" class="btn btn-outline btn-sm" id="zoomToggleBtn" onclick="window.toggleSlipZoom()" style="display:none;">
                                بزرگ‌نمایی تصویر
                            </button>
                            <a id="inspectReceiptLink" href="#" target="_blank" class="btn btn-outline btn-sm" style="display:none;">
                                اندازه کامل
                            </a>
                        </div>
                        <!-- Optical check indicator chips -->
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; justify-content:center;">
                            <span class="badge badge-emerald">تطبیق مبلغ فاکتور</span>
                            <span class="badge badge-blue">تطبیق کد رهگیری</span>
                            <span class="badge badge-purple">حساب مقصد فروشگاه</span>
                        </div>
                    </div>

                    <!-- Right Pane: Order Matching & Decision -->
                    <div style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px; margin-bottom:16px;">
                                <div style="font-size:0.8rem; color:var(--muted); margin-bottom:2px;">مبلغ مورد انتظار صورتحساب:</div>
                                <div id="inspectOrderAmount" style="font-size:1.4rem; font-weight:800; color:var(--emerald);" class="tabular-nums"></div>
                            </div>

                            <div class="detail-grid" style="grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                                <div class="detail-item">
                                    <div class="detail-label">نام خریدار</div>
                                    <div class="detail-value" id="inspectCustomerName"></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">تلفن خریدار</div>
                                    <div class="detail-value font-mono" id="inspectCustomerPhone" dir="ltr"></div>
                                </div>
                                <div class="detail-item" style="grid-column:1/-1;">
                                    <div class="detail-label">کد رهگیری / شماره ارجاع اعلامی</div>
                                    <div class="detail-value font-mono" id="inspectOrderRef" style="color:var(--primary);"></div>
                                </div>
                            </div>

                            <!-- Live Double-Entry Accounting Ledger Preview -->
                            <div class="ledger-preview-card" id="ledgerPreviewCard">
                                <div class="ledger-preview-header">
                                    <span>پیش‌نمایش سند دوبل حسابداری (سیستم پس از تأیید)</span>
                                    <span class="ledger-preview-delta font-mono">تراز دفاتر: &Delta; = ۰</span>
                                </div>
                                <div class="ledger-preview-row">
                                    <span>بدهکار (1010 Dr.): حساب نقد و بانک</span>
                                    <strong id="ledgerDrAmount" class="tabular-nums font-mono" style="color:var(--emerald);"></strong>
                                </div>
                                <div class="ledger-preview-row">
                                    <span>بستانکار (4010 Cr.): حساب درآمد فروش کالا</span>
                                    <strong id="ledgerCrSales" class="tabular-nums font-mono" style="color:var(--primary);"></strong>
                                </div>
                                <div class="ledger-preview-row" id="ledgerCrShippingRow">
                                    <span>بستانکار (2050 Cr.): حساب درآمد خدمات ارسال و پست</span>
                                    <strong id="ledgerCrShipping" class="tabular-nums font-mono" style="color:var(--slate);"></strong>
                                </div>
                            </div>

                            <div style="padding:10px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; font-size:0.78rem; color:#166534; line-height:1.6; margin-bottom:14px;">
                                <strong>اتوماسیون مالی:</strong> با تأیید فیش، فاکتور در وضعیت «پرداخت‌شده» قرار گرفته و اسناد دوبل بدهکار نقد/بانک و بستانکار درآمد فروش به صورت خودکار صادر می‌شوند.
                            </div>
                        </div>

                        <!-- Actions cluster & Bank Account Selector -->
                        <div style="border-top:1px solid var(--border); padding-top:14px; display:flex; flex-direction:column; gap:10px;">
                            <form id="inspectApproveForm" method="post" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">

                                <!-- Bank Account Selector -->
                                <div class="form-group" style="margin-bottom:12px;">
                                    <label style="font-size:0.8rem; font-weight:700; color:var(--slate);">حساب بانکی مقصد (تأیید واریز به):</label>
                                    <select name="bank_card_id" class="select" id="inspectBankCardSelect" style="font-size:0.84rem;">
                                        <?php foreach ($merchantCards as $bc): ?>
                                            <option value="<?= (int)$bc['id'] ?>">
                                                <?= e($bc['card_holder']) ?> — <?= e($bc['bank_name'] ?: 'بانک') ?> (<?= format_card_number($bc['card_number']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (empty($merchantCards)): ?>
                                            <option value="">حساب پیش‌فرض فروشگاه</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg" style="width:100%; gap:8px;">
                                    تأیید فیش و صدور اسناد دوبل حسابداری
                                </button>
                            </form>

                            <form id="inspectRejectForm" method="post" action="" onsubmit="return window.confirmReject(this);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="reason" id="rejectReasonInput" value="">
                                <button type="button" class="btn btn-outline btn-sm" style="width:100%; color:var(--crimson); border-color:#fca5a5;" onclick="window.promptReject()">
                                    رد فیش با ثبت دلیل به مشتری
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        let isSlipZoomed = false;
        window.toggleSlipZoom = function() {
            const imgEl = document.getElementById('inspectReceiptImg');
            const btn = document.getElementById('zoomToggleBtn');
            if (!imgEl) return;
            isSlipZoomed = !isSlipZoomed;
            if (isSlipZoomed) {
                imgEl.style.transform = 'scale(2.2)';
                imgEl.style.transformOrigin = 'top center';
                imgEl.style.maxHeight = 'none';
                imgEl.style.cursor = 'zoom-out';
                if (btn) btn.textContent = 'بازگشت به اندازه عادی';
            } else {
                imgEl.style.transform = 'none';
                imgEl.style.maxHeight = '320px';
                imgEl.style.cursor = 'zoom-in';
                if (btn) btn.textContent = 'بزرگ‌نمایی تصویر';
            }
        };

        window.inspectSlip = function(data) {
            document.getElementById('inspectOrderUuid').textContent = data.uuid;
            document.getElementById('inspectOrderAmount').textContent = data.amount;
            document.getElementById('inspectCustomerName').textContent = data.customer;
            document.getElementById('inspectCustomerPhone').textContent = data.phone;
            document.getElementById('inspectOrderRef').textContent = data.ref;

            const drEl = document.getElementById('ledgerDrAmount');
            if (drEl) drEl.textContent = data.amount;
            const crSalesEl = document.getElementById('ledgerCrSales');
            if (crSalesEl) crSalesEl.textContent = data.subtotal || data.amount;
            const crShipEl = document.getElementById('ledgerCrShipping');
            if (crShipEl) crShipEl.textContent = data.shipping || '۰ تومان';

            const imgEl = document.getElementById('inspectReceiptImg');
            const linkEl = document.getElementById('inspectReceiptLink');
            const emptyEl = document.getElementById('inspectReceiptEmpty');
            const pdfBox = document.getElementById('inspectPdfBox');
            const pdfLink = document.getElementById('inspectPdfLink');
            const zoomBtn = document.getElementById('zoomToggleBtn');

            isSlipZoomed = true;
            window.toggleSlipZoom(); // reset zoom

            if (data.img) {
                const isPdf = data.img.toLowerCase().endsWith('.pdf');
                if (isPdf) {
                    imgEl.style.display = 'none';
                    linkEl.style.display = 'none';
                    zoomBtn.style.display = 'none';
                    emptyEl.style.display = 'none';
                    pdfBox.style.display = 'block';
                    pdfLink.href = data.img;
                } else {
                    pdfBox.style.display = 'none';
                    imgEl.src = data.img;
                    imgEl.style.display = 'block';
                    linkEl.href = data.img;
                    linkEl.style.display = 'inline-flex';
                    zoomBtn.style.display = 'inline-flex';
                    emptyEl.style.display = 'none';
                }
            } else {
                imgEl.style.display = 'none';
                linkEl.style.display = 'none';
                zoomBtn.style.display = 'none';
                pdfBox.style.display = 'none';
                emptyEl.style.display = 'block';
            }

            if (data.card_id) {
                const sel = document.getElementById('inspectBankCardSelect');
                if (sel) sel.value = String(data.card_id);
            }

            document.getElementById('inspectApproveForm').action = '/orders/' + data.id + '/payment/verify';
            document.getElementById('inspectRejectForm').action = '/orders/' + data.id + '/payment/verify';

            document.getElementById('slipInspectorModal').classList.add('open');
        };

        window.closeSlipModal = function() {
            document.getElementById('slipInspectorModal').classList.remove('open');
        };

        window.promptReject = function() {
            const reason = prompt('لطفاً دلیل رد فیش واریزی را جهت اطلاع به خریدار وارد نمایید:', 'مبلغ واریزی با فاکتور همخوانی ندارد یا تصویر فیش ناخوانا است.');
            if (reason) {
                document.getElementById('rejectReasonInput').value = reason;
                document.getElementById('inspectRejectForm').submit();
            }
        };
        </script>
    <?php endif; ?>

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
    if ($user['role'] === 'customer') {
        layout_customer_end();
    } else {
        layout_end();
    }
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