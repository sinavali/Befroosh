<?php
declare(strict_types=1);

/**
 * routes_accounting_ledger.php
 * General Ledger, VAT Tax Report, and Manual Accounting Journal Entries
 */

// Full General Ledger / دفتر روزنامه کل
route('GET', '/accounting/ledger', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 30;
    $accountFilter = trim($_GET['account'] ?? '');

    $where = "WHERE al.shop_id = ?";
    $params = [$shopId];

    if ($accountFilter !== '') {
        $where .= " AND al.account = ?";
        $params[] = $accountFilter;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM accounting_ledger al $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT al.*, o.uuid AS order_uuid, u.nickname AS user_name
        FROM accounting_ledger al
        LEFT JOIN orders o ON o.id = al.order_id
        LEFT JOIN users u ON u.id = al.created_by_id
        $where
        ORDER BY al.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('دفتر روزنامه حسابداری', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>دفتر روزنامه و ریز گردش حساب‌ها</h1>
                <div class="page-sub">کلیه تراکنش‌های مالی و ثبتی سیستم به همراه سند مرجع</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/accounting">بازگشت به داشبورد مالی</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="filter-bar">
                <select class="select" name="account" onchange="this.form.submit()">
                    <option value="">همه سرفصل‌های حساب</option>
                    <option value="cash_bank" <?= $accountFilter === 'cash_bank' ? 'selected' : '' ?>>نقد و بانک</option>
                    <option value="inventory_asset" <?= $accountFilter === 'inventory_asset' ? 'selected' : '' ?>>موجودی کالا</option>
                    <option value="sales_income" <?= $accountFilter === 'sales_income' ? 'selected' : '' ?>>درآمد فروش</option>
                    <option value="cogs" <?= $accountFilter === 'cogs' ? 'selected' : '' ?>>بهای تمام‌شده (COGS)</option>
                    <option value="tax_payable" <?= $accountFilter === 'tax_payable' ? 'selected' : '' ?>>مالیات بر ارزش افزوده</option>
                    <option value="discounts" <?= $accountFilter === 'discounts' ? 'selected' : '' ?>>تخفیفات</option>
                </select>
                <?php if ($accountFilter): ?>
                    <a class="btn btn-outline btn-sm" href="/accounting/ledger">نمایش همه</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره سند</th>
                        <th>تاریخ</th>
                        <th>سرفصل حساب</th>
                        <th>بدهکار</th>
                        <th>بستانکار</th>
                        <th>سفارش</th>
                        <th>کاربر ثبت‌کننده</th>
                        <th>شرح سند</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8"><?= empty_state('سندی یافت نشد') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= en_to_fa_digits((string)$r['id']) ?></td>
                                <td class="date-cell"><?= format_jalali($r['entry_date']) ?></td>
                                <td><code><?= e($r['account']) ?></code></td>
                                <td><?= (float)$r['debit'] > 0 ? format_irr((float)$r['debit']) : '—' ?></td>
                                <td><?= (float)$r['credit'] > 0 ? format_irr((float)$r['credit']) : '—' ?></td>
                                <td>
                                    <?php if (!empty($r['order_id'])): ?>
                                        <a href="/orders/<?= (int)$r['order_id'] ?>" style="color:var(--primary); font-weight:bold;">
                                            #<?= e($r['order_uuid'] ?: (string)$r['order_id']) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= e($r['user_name'] ?: 'سیستم خودکار') ?></td>
                                <td><?= e($r['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/accounting/ledger', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

// Quarterly Tax / VAT Report
route('GET', '/accounting/tax-report', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $shop = current_shop();

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(o.subtotal), 0) AS total_taxable_sales,
            COALESCE(SUM(o.tax_amount), 0) AS total_vat_collected
        FROM orders o
        WHERE o.shop_id = ? AND o.status IN ('finalised', 'completed')
    ");
    $stmt->execute([$shopId]);
    $taxSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    layout_start('گزارش مالیات بر ارزش افزوده', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>گزارش فصلی مالیات بر ارزش افزوده (VAT)</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? '—') ?></strong> | آماده‌سازی اظهارنامه مالیات بر ارزش افزوده سامانه مودیان</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/accounting">بازگشت</a>
    </div>

    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('orders', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$taxSummary['total_orders']) ?></div>
                <div class="stat-label">تعداد فاکتورهای رسمی نهایی</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr((float)$taxSummary['total_taxable_sales']) ?></div>
                <div class="stat-label">مأخذ مشمول مالیات (جمع فروش خالص)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rose"><?= icon('clock', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr((float)$taxSummary['total_vat_collected']) ?></div>
                <div class="stat-label">مجموع مالیات ارزش افزوده وصول‌شده</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>مشخصات اظهارنامه فروشگاه</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">شناسه ملی / کد ملی</div>
                    <div class="detail-value"><?= e($shop['national_id'] ?: 'ثبت نشده در تنظیمات') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">کد اقتصادی</div>
                    <div class="detail-value"><?= e($shop['economic_code'] ?: 'ثبت نشده') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">نرخ پیش‌فرض ارزش افزوده</div>
                    <div class="detail-value"><?= en_to_fa_digits((string)(($shop['tax_rate'] ?? 0) * 100)) ?>٪</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">نشانی رسمی</div>
                    <div class="detail-value"><?= e($shop['address'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    layout_end();
});

// Manual Accounting Entry
route('GET|POST', '/accounting/manual', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $account = $_POST['account'] ?? '';
        $debit = (float)fa_to_en_digits($_POST['debit'] ?? '0');
        $credit = (float)fa_to_en_digits($_POST['credit'] ?? '0');
        $desc = trim($_POST['description'] ?? '');

        if (!$desc || (!$debit && !$credit) || !in_array($account, ['cash_bank', 'inventory_asset', 'sales_income', 'cogs', 'tax_payable', 'discounts', 'adjustment'], true)) {
            $error = 'لطفاً سرفصل حساب، مبلغ و شرح سند را به طور کامل وارد نمایید.';
        } else {
            try {
                record_accounting_entry($shopId, 'manual_entry', null, $debit, $credit, $account, $desc, (int)$user['id']);
                flash('success', 'سند حسابداری با موفقیت در دفتر روزنامه ثبت شد.');
                redirect('/accounting/ledger');
            } catch (Throwable $e) {
                $error = 'خطا در ثبت سند: ' . $e->getMessage();
            }
        }
    }

    layout_start('ثبت سند حسابداری دستی', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>ثبت سند حسابداری دستی</h1>
                <div class="page-sub">ثبت هزینه‌های عملیاتی، اصلاحات اسناد یا واریز و برداشت‌ها</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/accounting">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>سرفصل حساب *</label>
                    <select class="select" name="account" required>
                        <option value="cash_bank">نقد و بانک (cash_bank)</option>
                        <option value="inventory_asset">موجودی کالا (inventory_asset)</option>
                        <option value="sales_income">درآمد حاصل از فروش (sales_income)</option>
                        <option value="cogs">بهای تمام‌شده کالای فروش‌رفته (cogs)</option>
                        <option value="tax_payable">مالیات ارزش افزوده پرداختنی (tax_payable)</option>
                        <option value="discounts">تخفیفات اعطایی (discounts)</option>
                        <option value="adjustment">تعدیلات و متفرقه (adjustment)</option>
                    </select>
                </div>

                <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>مبلغ بدهکار (ریال)</label>
                        <input class="input" type="number" step="1" name="debit" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>مبلغ بستانکار (ریال)</label>
                        <input class="input" type="number" step="1" name="credit" placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>شرح کامل سند *</label>
                    <textarea class="textarea" name="description" required placeholder="علت و جزئیات سند مالی..."></textarea>
                </div>

                <button class="btn btn-primary"><?= icon('check', 14) ?> ثبت در دفتر روزنامه</button>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});
