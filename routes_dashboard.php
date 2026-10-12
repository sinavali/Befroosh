<?php
declare(strict_types=1);

/**
 * routes_dashboard.php
 * Role-Based Tailored Dashboard View with Context-Aware Progressive Disclosure:
 * - Solo Business Owners (Instagram/Telegram Shops, 0 staff): Clean action cockpit, bio-link sharing, urgent slip alerts.
 * - Growing Business Owners (1+ staff, multi-branch): Consolidated GMV, branch performance, staff oversight, accounting health.
 * - Branch Managers: Ground fulfillment operations, ready-to-ship packages, low branch stock, post dispatch.
 * - SuperAdmins: Multi-tenant SaaS control tower, subscription quota meters, escalated support triage.
 * - Customers: Automatically redirected to dedicated /my portal.
 */

route('GET', '/(?:app/)?dashboard(?:\.php)?', [], function () use ($pdo) {
    $user = require_login();

    // Customers belong exclusively in their dedicated portal
    if ($user['role'] === 'customer') {
        redirect('/my');
    }

    $isSuperAdmin = in_array($user['role'], ['superadmin', 'admin'], true);
    $isOwner = in_array($user['role'], ['business_owner', 'shop_owner'], true);
    $isBranchStaff = in_array($user['role'], ['branch_manager', 'shop_manager', 'manager'], true);

    // Filter pill selection for recent orders table
    $filter = $_GET['filter'] ?? 'all';
    $filterWhere = match ($filter) {
        'pending_receipts' => " AND o.payment_status = 'pending_verification'",
        'ready_to_pack' => " AND o.status = 'paid'",
        'shipped' => " AND o.status = 'shipped'",
        'completed' => " AND o.status = 'completed'",
        default => "",
    };

    if ($isSuperAdmin) {
        // ==========================================
        // SUPERADMIN: PLATFORM CONTROL TOWER
        // ==========================================
        $dashTitle = 'داشبورد پایش کلان سامانه بفروش';
        $dashSub = 'دید کلی بر کسب‌وکارها، زیرساخت اشتراک‌ها و پایداری شبکه';

        $totalShops = (int) $pdo->query("SELECT COUNT(*) FROM shops WHERE active = 1")->fetchColumn();
        $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
        $revenueToday = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE status IN ('finalised','completed','paid') AND date(created_at) = date('now')")->fetchColumn();
        $revenueTotal = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE status IN ('finalised','completed','paid')")->fetchColumn();
        $escalatedTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open' AND (is_escalated_to_superadmin = 1 OR is_elevated_to_superadmin = 1)")->fetchColumn();
        $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

        // Subscription Plans Distribution
        $planStats = $pdo->query("
            SELECT sp.id, sp.slug, sp.name, sp.price_irt,
                   (SELECT COUNT(*) FROM shops s WHERE s.subscription_plan_id = sp.id AND s.active = 1) AS active_shops
            FROM subscription_plans sp
            ORDER BY sp.sort_order ASC, sp.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentOrders = $pdo->query("
            SELECT o.*, u.nickname AS customer_nickname, s.name AS shop_name
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            LEFT JOIN shops s ON s.id = o.shop_id
            WHERE 1=1 {$filterWhere}
            ORDER BY o.id DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // ==========================================
        // MERCHANT & BRANCH STAFF
        // ==========================================
        [$shopId, $shop] = get_current_management_shop($user);
        $activeBranchId = active_branch_id();
        $branch = $activeBranchId ? get_branch($activeBranchId) : null;
        $branchWhere = ($branch ? " AND o.branch_id = {$activeBranchId}" : "");
        $prodBranchWhere = ($branch ? " AND (p.branch_id = {$activeBranchId} OR p.branch_id IS NULL)" : "");

        $ownerId = (int) ($shop['owner_id'] ?? 0);
        $staffCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE shop_id = {$shopId} AND id != {$ownerId} AND deleted_at IS NULL")->fetchColumn();
        $branchCount = (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE business_id = {$shopId} AND active = 1")->fetchColumn();
        $isSoloMerchant = ($isOwner && $staffCount === 0 && $branchCount <= 1 && !$branch);

        if ($isBranchStaff && $branch) {
            $dashTitle = 'داشبورد عملیات شعبه ' . ($branch['name'] ?? '');
            $dashSub = 'میز کار انبارداری، بسته‌بندی و ارسال سفارشات این شعبه (' . ($shop['name'] ?? '') . ')';
        } elseif ($isSoloMerchant) {
            $dashTitle = 'پیشخوان فروشگاه ' . ($shop['name'] ?? '');
            $dashSub = 'مرکز عملیات روزانه، پیگیری فیش‌های واریزی و سفارشات آنلاین';
        } else {
            $dashTitle = 'داشبورد سراسری کسب‌وکار ' . ($shop['name'] ?? '');
            $dashSub = 'دید کلی و تجمیعی تمامی شعب، انبارها، تراز مالی و پرسنل';
        }

        $revenueToday = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(o.final_total, o.estimated_total)), 0) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.status IN ('finalised','completed','paid') AND date(o.created_at) = date('now')")->fetchColumn();
        $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND date(o.created_at) = date('now')")->fetchColumn();
        $revenueTotal = (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(o.final_total, o.estimated_total)), 0) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.status IN ('finalised','completed','paid')")->fetchColumn();

        $pendingReceipts = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.payment_status = 'pending_verification'")->fetchColumn();
        $readyToPack = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.shop_id = {$shopId}{$branchWhere} AND o.status = 'paid'")->fetchColumn();
        $lowStockCount = (int) $pdo->query("SELECT COUNT(*) FROM products p WHERE p.shop_id = {$shopId}{$prodBranchWhere} AND p.deleted_at IS NULL AND p.active = 1 AND (p.stock_quantity - p.reserved_quantity) <= COALESCE(p.min_stock_alert, 3)")->fetchColumn();
        $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE shop_id = {$shopId} AND status = 'open'")->fetchColumn();

        // Double-entry accounting delta for business owners
        $ledgerDelta = (float) $pdo->query("SELECT COALESCE(SUM(debit - credit), 0) FROM accounting_ledger WHERE shop_id = {$shopId}")->fetchColumn();

        // Branches overview for team mode
        $shopBranches = [];
        if (!$isSoloMerchant) {
            $shopBranches = $pdo->query("
                SELECT b.*, 
                       (SELECT COUNT(*) FROM orders o WHERE o.branch_id = b.id AND date(o.created_at) = date('now')) as orders_today
                FROM branches b 
                WHERE b.business_id = {$shopId} AND b.active = 1
                ORDER BY b.is_main DESC, b.id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $recentOrders = $pdo->query("
            SELECT o.*, u.nickname AS customer_nickname, b.name AS branch_name
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            LEFT JOIN branches b ON b.id = o.branch_id
            WHERE o.shop_id = {$shopId}{$branchWhere} {$filterWhere}
            ORDER BY o.id DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $currentHost = $_SERVER['HTTP_HOST'] ?? 'befroosh.test';

    layout_start($dashTitle, $user);
    ?>
    <style>
        .dash-hero-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .bio-link-banner {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .bio-link-info { display: flex; align-items: center; gap: 14px; min-width: 280px; }
        .bio-link-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--surface-well);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .bio-link-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            max-width: 520px;
            min-width: 260px;
        }
        .urgent-attention-strip {
            background: #ffffff;
            border: 1px solid var(--border);
            border-right: 4px solid var(--amber);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .urgent-attention-strip.calm { border-right-color: var(--emerald); }
        .urgent-strip-items { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .urgent-strip-item { display: inline-flex; align-items: center; gap: 8px; font-size: 0.86rem; color: var(--ink); }
        .filter-pills { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
        .filter-pill {
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--muted);
            background: #ffffff;
            border: 1px solid var(--border);
            transition: all 0.15s ease;
        }
        .filter-pill:hover { color: var(--ink); border-color: #cbd5e1; }
        .filter-pill.active { background: var(--primary); color: #ffffff; border-color: var(--primary); }
    </style>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('dashboard', 18) ?></div>
            <div>
                <h1><?= e($dashTitle) ?></h1>
                <div class="page-sub"><?= e($dashSub) ?></div>
            </div>
        </div>

        <div class="dash-hero-actions">
            <?php if ($isSuperAdmin): ?>
                <a class="btn btn-outline" href="/shops/manage"><?= icon('store', 14) ?> مدیریت کسب‌وکارها</a>
                <a class="btn btn-outline" href="/app/plans"><?= icon('shield', 14) ?> پلن‌های اشتراک</a>
                <a class="btn btn-primary" href="/orders"><?= icon('orders', 14) ?> پایش سفارشات</a>
            <?php elseif ($isBranchStaff): ?>
                <a class="btn btn-outline" href="/inventory/inward"><?= icon('plus', 14) ?> ورود کالا به انبار</a>
                <a class="btn btn-outline" href="/orders?status=paid"><?= icon('print', 14) ?> بارکدهای آماده پست</a>
                <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش شعبه</a>
            <?php elseif ($isSoloMerchant): ?>
                <a class="btn btn-outline" href="/orders?status=all&payment_status=pending_verification"><?= icon('receipt', 14) ?> صف فیش‌ها</a>
                <a class="btn btn-outline" href="/inventory"><?= icon('inventory', 14) ?> انبارداری</a>
                <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش سریع (دایرکت/تلفن)</a>
            <?php else: ?>
                <a class="btn btn-outline" href="/orders?status=all&payment_status=pending_verification"><?= icon('receipt', 14) ?> صف تایید فیش‌ها</a>
                <a class="btn btn-outline" href="/app/branches"><?= icon('store', 14) ?> شعب (<?= en_to_fa_digits((string)$branchCount) ?>)</a>
                <a class="btn btn-primary" href="/orders/create"><?= icon('plus', 14) ?> ثبت سفارش جدید</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isSuperAdmin && !empty($shop)): ?>
        <!-- SOLO / BOUTIQUE BIO-LINK SHARING BAR -->
        <div class="bio-link-banner">
            <div class="bio-link-info">
                <div class="bio-link-icon"><?= icon('link', 20) ?></div>
                <div>
                    <div style="font-weight:700; font-size:0.92rem; color:var(--ink);">لینک مستقیم ویترین شما</div>
                    <div style="font-size:0.75rem; color:var(--muted);">مناسب برای بایو اینستاگرام، کانال تلگرام و پیام‌رسان‌ها</div>
                </div>
            </div>

            <?php $storeUrl = 'https://' . $currentHost . '/b/' . ($shop['slug'] ?? 'central'); ?>
            <div class="bio-link-input-group">
                <input class="input" dir="ltr" readonly value="<?= e($storeUrl) ?>" id="bioLinkInput" style="font-size:0.84rem; background:#f8fafc; font-weight:600; text-align:left; cursor:pointer;" onclick="this.select();">
                <button type="button" class="btn btn-outline btn-sm" id="bioCopyBtn" onclick="copyBioLink('<?= e($storeUrl) ?>', 'bioCopyBtn')">
                    <?= icon('copy', 14) ?> کپی لینک
                </button>
                <a href="/b/<?= e($shop['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="مشاهده ویترین زنده">
                    <?= icon('eye', 14) ?> ویترین
                </a>
            </div>
        </div>

        <!-- URGENT ATTENTION STRIP -->
        <?php 
        $hasAlerts = ($pendingReceipts > 0 || $readyToPack > 0 || $lowStockCount > 0);
        ?>
        <div class="urgent-attention-strip <?= $hasAlerts ? '' : 'calm' ?>">
            <div class="urgent-strip-items">
                <?php if ($pendingReceipts > 0): ?>
                    <div class="urgent-strip-item">
                        <span class="badge badge-rose">اقدام فوری</span>
                        <span><strong><?= en_to_fa_digits((string)$pendingReceipts) ?></strong> فیش کارت‌به‌کارت در صف بررسی</span>
                        <a href="/orders?status=all&payment_status=pending_verification" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:0.75rem;">بررسی</a>
                    </div>
                <?php endif; ?>

                <?php if ($readyToPack > 0): ?>
                    <div class="urgent-strip-item">
                        <span class="badge badge-blue">بسته‌بندی</span>
                        <span><strong><?= en_to_fa_digits((string)$readyToPack) ?></strong> سفارش آماده تحویل به پست</span>
                        <a href="/orders?status=paid" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:0.75rem;">مشاهده</a>
                    </div>
                <?php endif; ?>

                <?php if ($lowStockCount > 0): ?>
                    <div class="urgent-strip-item">
                        <span class="badge badge-amber">موجودی کم</span>
                        <span><strong><?= en_to_fa_digits((string)$lowStockCount) ?></strong> کالا در آستانه اتمام موجودی</span>
                        <a href="/inventory" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:0.75rem;">شارژ انبار</a>
                    </div>
                <?php endif; ?>

                <?php if (!$hasAlerts): ?>
                    <div class="urgent-strip-item">
                        <span class="badge badge-emerald">وضعیت پایدار</span>
                        <span style="color:var(--muted); font-size:0.84rem;">تمامی فیش‌ها بررسی شده، بسته‌ها ارسال شده و موجودی انبار کافی است.</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isSoloMerchant && $isOwner): ?>
                <div style="font-size:0.78rem; color:var(--muted);">
                    تراز حسابداری: 
                    <?php if (abs($ledgerDelta) < 0.01): ?>
                        <span class="badge badge-emerald">متوازن (Δ = ۰)</span>
                    <?php else: ?>
                        <span class="badge badge-rose">نامتوازن (<?= format_irt(abs($ledgerDelta)) ?>)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- STATS / KPI GRID -->
    <div class="stats-grid">
        <?php if ($isSuperAdmin): ?>
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">کسب‌وکارهای فعال</span>
                    <span class="badge badge-blue">سراسری</span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $totalShops) ?></div>
                <div class="metric-sub">تعداد کل فروشگاه‌های فعال سامانه</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">گردش مالی امروز سامانه</span>
                    <span class="badge badge-emerald">تومان</span>
                </div>
                <div class="metric-value"><?= format_irt($revenueToday) ?></div>
                <div class="metric-sub">کل فروش سراسری: <?= format_irt($revenueTotal) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">سفارش‌های امروز سامانه</span>
                    <span class="badge badge-amber">لحظه‌ای</span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $ordersToday) ?></div>
                <div class="metric-sub">تراکنش‌های ثبت شده سراسر کشور</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">تیکت‌های ویژه آلتیمیت</span>
                    <span class="badge <?= $escalatedTickets > 0 ? 'badge-rose' : 'badge-purple' ?>">
                        <?= $escalatedTickets > 0 ? 'نیازمند مداخله' : 'عادی' ?>
                    </span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $escalatedTickets) ?></div>
                <div class="metric-sub">کل تیکت‌های باز سامانه: <?= en_to_fa_digits((string)$openTickets) ?></div>
            </div>

        <?php elseif ($isBranchStaff): ?>
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">سفارش‌های امروز شعبه</span>
                    <span class="badge badge-blue">لحظه‌ای</span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $ordersToday) ?></div>
                <div class="metric-sub">تعداد سفارش‌های محوله به این شعبه</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">آماده تحویل به پست</span>
                    <span class="badge badge-emerald">بسته‌بندی</span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $readyToPack) ?></div>
                <div class="metric-sub">فاکتورهای پرداخت‌شده و آماده ارسال</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">موجودی بحرانی انبار</span>
                    <span class="badge <?= $lowStockCount > 0 ? 'badge-rose' : 'badge-amber' ?>">
                        <?= $lowStockCount > 0 ? 'نیاز به شارژ' : 'کافی' ?>
                    </span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $lowStockCount) ?></div>
                <div class="metric-sub">کالاهای ۳ عدد یا کمتر در این شعبه</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">تیکت‌های مشتریان شعبه</span>
                    <span class="badge badge-purple"><?= $openTickets > 0 ? 'نیازمند پاسخ' : 'تکمیل' ?></span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
                <div class="metric-sub">درخواست‌های باز مشتریان این شعبه</div>
            </div>

        <?php else: /* Business Owner (Solo or Growing) */ ?>
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">فروش امروز</span>
                    <span class="badge badge-emerald">تومان</span>
                </div>
                <div class="metric-value"><?= format_irt($revenueToday) ?></div>
                <div class="metric-sub"><?= en_to_fa_digits((string) $ordersToday) ?> سفارش ثبت شده امروز</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">فیش در صف تایید</span>
                    <span class="badge <?= $pendingReceipts > 0 ? 'badge-rose' : 'badge-amber' ?>">
                        <?= $pendingReceipts > 0 ? 'اقدام فوری' : 'به‌روز' ?>
                    </span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $pendingReceipts) ?></div>
                <div class="metric-sub">رسیدهای کارت‌به‌کارت نیازمند بررسی</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">آماده بسته‌بندی و ارسال</span>
                    <span class="badge badge-blue">پست پیشتاز</span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $readyToPack) ?></div>
                <div class="metric-sub">سفارش‌های تسویه‌شده آماده ارسال</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-label">هشدار اتمام موجودی</span>
                    <span class="badge <?= $lowStockCount > 0 ? 'badge-rose' : 'badge-purple' ?>">
                        <?= $lowStockCount > 0 ? 'شارژ انبار' : 'موجود' ?>
                    </span>
                </div>
                <div class="metric-value"><?= en_to_fa_digits((string) $lowStockCount) ?></div>
                <div class="metric-sub">کالاهای با موجودی ۳ عدد یا کمتر</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SUPERADMIN: SUBSCRIPTION PLANS METERS -->
    <?php if ($isSuperAdmin && !empty($planStats)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2>وضعیت پلن‌های اشتراک پلتفرم</h2>
                <a class="btn btn-outline btn-sm" href="/app/plans">مدیریت پلن‌ها</a>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                    <?php foreach ($planStats as $ps): ?>
                        <div style="background:var(--surface-well); border:1px solid var(--border); border-radius:10px; padding:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="font-size:0.9rem;"><?= e($ps['name']) ?></strong>
                                <span class="badge badge-blue"><?= $ps['price_irt'] > 0 ? format_irt((float)$ps['price_irt']) : 'رایگان' ?></span>
                            </div>
                            <div style="font-size:1.3rem; font-weight:800; color:var(--ink);">
                                <?= en_to_fa_digits((string)$ps['active_shops']) ?> <span style="font-size:0.75rem; font-weight:normal; color:var(--muted);">کسب‌وکار</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TEAM MODE: BRANCHES PERFORMANCE STRIP -->
    <?php if (!$isSuperAdmin && !$isSoloMerchant && !empty($shopBranches) && count($shopBranches) > 1): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2>عملکرد شعب فعال</h2>
                <a class="btn btn-outline btn-sm" href="/app/branches">مدیریت شعب</a>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
                    <?php foreach ($shopBranches as $sb): ?>
                        <div style="background:var(--surface-well); border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <strong><?= e($sb['name']) ?></strong>
                                <?php if ($sb['is_main']): ?><span class="badge badge-emerald">شعبه اصلی</span><?php endif; ?>
                            </div>
                            <div style="font-size:0.8rem; color:var(--muted); margin-bottom:4px;">
                                سفارش‌های امروز: <strong><?= en_to_fa_digits((string)$sb['orders_today']) ?></strong>
                            </div>
                            <div style="font-size:0.75rem; color:var(--muted);"><?= e($sb['phone'] ?: 'بدون شماره') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- RECENT ORDERS SECTION WITH QUICK FILTER PILLS -->
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap; gap:12px;">
            <div>
                <h2>آخرین سفارشات</h2>
                <div style="font-size:0.78rem; color:var(--muted); margin-top:2px;">بررسی لحظه‌ای وضعیت پرداخت، فیش‌ها و ارسال</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه سفارشات</a>
            </div>
        </div>

        <div class="card-body" style="padding-bottom:8px;">
            <!-- FILTER PILLS -->
            <div class="filter-pills">
                <a href="/dashboard?filter=all" class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>">همه</a>
                <a href="/dashboard?filter=pending_receipts" class="filter-pill <?= $filter === 'pending_receipts' ? 'active' : '' ?>">
                    در انتظار تایید فیش <?= $pendingReceipts > 0 ? '(' . en_to_fa_digits((string)$pendingReceipts) . ')' : '' ?>
                </a>
                <a href="/dashboard?filter=ready_to_pack" class="filter-pill <?= $filter === 'ready_to_pack' ? 'active' : '' ?>">
                    آماده بسته‌بندی <?= $readyToPack > 0 ? '(' . en_to_fa_digits((string)$readyToPack) . ')' : '' ?>
                </a>
                <a href="/dashboard?filter=shipped" class="filter-pill <?= $filter === 'shipped' ? 'active' : '' ?>">تحویل به پست</a>
                <a href="/dashboard?filter=completed" class="filter-pill <?= $filter === 'completed' ? 'active' : '' ?>">تکمیل‌شده</a>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>شماره سفارش</th>
                            <th>مشتری</th>
                            <?php if ($isSuperAdmin): ?><th>فروشگاه</th><?php endif; ?>
                            <?php if (!$isSoloMerchant && !$isSuperAdmin): ?><th>شعبه</th><?php endif; ?>
                            <th>وضعیت سفارش</th>
                            <th>وضعیت پرداخت</th>
                            <th>مبلغ</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentOrders): ?>
                            <tr>
                                <td colspan="<?= $isSuperAdmin || !$isSoloMerchant ? 8 : 7 ?>">
                                    <?= empty_state('سفارشی در این وضعیت یافت نشد', 'برای ثبت سفارش جدید روی دکمه ثبت سفارش کلیک فرمایید.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><strong><?= e($o['uuid']) ?></strong></td>
                                    <td>
                                        <div><?= e($o['customer_nickname']) ?></div>
                                        <?php if (!empty($o['customer_phone'])): ?>
                                            <div style="font-size:0.75rem; color:var(--muted);"><?= en_to_fa_digits($o['customer_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isSuperAdmin): ?>
                                        <td><?= e($o['shop_name'] ?? 'مرکزی') ?></td>
                                    <?php endif; ?>
                                    <?php if (!$isSoloMerchant && !$isSuperAdmin): ?>
                                        <td><span class="badge badge-outline"><?= e($o['branch_name'] ?? 'مرکزی') ?></span></td>
                                    <?php endif; ?>
                                    <td><?= order_badge($o['status']) ?></td>
                                    <td>
                                        <?php if ($o['payment_status'] === 'pending_verification'): ?>
                                            <span class="badge badge-amber">در انتظار تایید فیش</span>
                                        <?php elseif ($o['payment_status'] === 'paid'): ?>
                                            <span class="badge badge-emerald">پرداخت‌شده</span>
                                        <?php else: ?>
                                            <span class="badge badge-slate">پرداخت‌نشده</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tabular-nums" style="font-weight:600;">
                                        <?= format_irt((float) ($o['final_total'] ?? $o['estimated_total'])) ?>
                                    </td>
                                    <td class="date-cell"><?= format_jalali($o['created_at']) ?></td>
                                    <td>
                                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int) $o['id'] ?>">مشاهده</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function copyBioLink(url, btnId) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    showCopiedFeedback(btnId);
                }).catch(function() {
                    fallbackCopy(url, btnId);
                });
            } else {
                fallbackCopy(url, btnId);
            }
        }

        function fallbackCopy(text, btnId) {
            var input = document.getElementById('bioLinkInput');
            if (input) {
                input.select();
                document.execCommand('copy');
                showCopiedFeedback(btnId);
            }
        }

        function showCopiedFeedback(btnId) {
            var btn = document.getElementById(btnId);
            if (!btn) return;
            var origText = btn.innerHTML;
            btn.innerHTML = 'کپی شد';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline');
            setTimeout(function() {
                btn.innerHTML = origText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline');
            }, 2000);
        }
    </script>
    <?php
    layout_end();
});
