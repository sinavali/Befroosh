<?php
declare(strict_types=1);

// Accounting Dashboard & P&L Summary
route('GET', '/accounting(?:\.php)?', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $shop = current_shop();

    $where = "WHERE shop_id = ?";
    $params = [$shopId];

    // Compute key financial metrics from ledger and orders
    $stmtTotals = $pdo->prepare("
        SELECT 
            account,
            COALESCE(SUM(debit), 0) AS total_debit,
            COALESCE(SUM(credit), 0) AS total_credit
        FROM accounting_ledger
        $where
        GROUP BY account
    ");
    $stmtTotals->execute($params);
    $acctRows = $stmtTotals->fetchAll(PDO::FETCH_ASSOC);

    $accounts = [];
    foreach ($acctRows as $ar) {
        $accounts[$ar['account']] = [
            'debit' => (float)$ar['total_debit'],
            'credit' => (float)$ar['total_credit'],
            'net' => (float)$ar['debit'] - (float)$ar['credit'],
        ];
    }

    $salesRevenue = (float)($accounts['sales_income']['credit'] ?? 0);
    $cogs = (float)($accounts['cogs']['debit'] ?? 0);
    $taxPayable = (float)(($accounts['tax_payable']['credit'] ?? 0) - ($accounts['tax_payable']['debit'] ?? 0));
    $cashBank = (float)(($accounts['cash_bank']['debit'] ?? 0) - ($accounts['cash_bank']['credit'] ?? 0));
    $grossProfit = $salesRevenue - $cogs;
    $profitMargin = $salesRevenue > 0 ? round(($grossProfit / $salesRevenue) * 100, 1) : 0.0;

    // Recent ledger entries
    $stmtRecent = $pdo->prepare("
        SELECT al.*, o.uuid AS order_uuid, u.nickname AS user_name
        FROM accounting_ledger al
        LEFT JOIN orders o ON o.id = al.order_id
        LEFT JOIN users u ON u.id = al.created_by_id
        $where
        ORDER BY al.id DESC
        LIMIT 12
    ");
    $stmtRecent->execute($params);
    $recentEntries = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    layout_start('حسابداری و تراز مالی', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>مدیریت مالی و حسابداری</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? '—') ?></strong> | تراز دفاتر، بهای تمام‌شده و سود و زیان عملیاتی</div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/accounting/ledger"><?= icon('orders', 14) ?> دفتر روزنامه کل</a>
            <a class="btn btn-outline" href="/accounting/tax-report"><?= icon('report', 14) ?> گزارش مالیات بر ارزش افزوده</a>
            <a class="btn btn-primary" href="/accounting/manual"><?= icon('plus', 14) ?> ثبت سند دستی</a>
        </div>
    </div>

    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($salesRevenue) ?></div>
                <div class="stat-label">درآمد خالص حاصل از فروش</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('products', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($cogs) ?></div>
                <div class="stat-label">بهای تمام‌شده کالای فروش‌رفته (COGS)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('orders', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($grossProfit) ?></div>
                <div class="stat-label">سود ناخالص عملیاتی (حاشیه سود: <?= en_to_fa_digits((string)$profitMargin) ?>٪)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><?= icon('clock', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($taxPayable) ?></div>
                <div class="stat-label">مالیات بر ارزش افزوده پرداختنی</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2>خلاصه وضعیت سرفصل‌های حسابداری (تراز کل)</h2>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>سرفصل حساب</th>
                        <th>ماهیت</th>
                        <th>مجموع بدهکار (ریال)</th>
                        <th>مجموع بستانکار (ریال)</th>
                        <th>مانده حساب</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $heads = [
                        'cash_bank' => ['نقد و بانک (وجوه دریافتی)', 'دارایی جاری', true],
                        'inventory_asset' => ['موجودی کالای انبار', 'دارایی جاری', true],
                        'sales_income' => ['درآمد حاصل از فروش کالا', 'درآمد', false],
                        'cogs' => ['بهای تمام‌شده کالای فروش‌رفته', 'هزینه', true],
                        'tax_payable' => ['مالیات بر ارزش افزوده پرداختنی', 'بدهی جاری', false],
                        'shipping_income' => ['درآمد هزینه ارسال', 'درآمد', false],
                        'discounts' => ['تخفیفات اعطایی به مشتریان', 'کاهنده درآمد', true],
                    ];
                    foreach ($heads as $code => [$name, $nature, $normalDebit]):
                        $d = (float)($accounts[$code]['debit'] ?? 0);
                        $c = (float)($accounts[$code]['credit'] ?? 0);
                        $bal = $normalDebit ? ($d - $c) : ($c - $d);
                    ?>
                        <tr>
                            <td><strong><?= e($name) ?></strong> (<code><?= e($code) ?></code>)</td>
                            <td><?= e($nature) ?></td>
                            <td><?= format_irr($d) ?></td>
                            <td><?= format_irr($c) ?></td>
                            <td>
                                <strong style="color:<?= $bal >= 0 ? '#15803d' : '#dc2626' ?>;">
                                    <?= format_irr($bal) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>آخرین اسناد دفتر روزنامه</h2>
            <a class="btn btn-outline btn-sm" href="/accounting/ledger">مشاهده تمام اسناد</a>
        </div>
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
                        <th>شرح سند</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentEntries): ?>
                        <tr><td colspan="7"><?= empty_state('سند مالی ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentEntries as $entry): ?>
                            <tr>
                                <td><?= en_to_fa_digits((string)$entry['id']) ?></td>
                                <td class="date-cell"><?= format_jalali($entry['entry_date']) ?></td>
                                <td><code><?= e($entry['account']) ?></code></td>
                                <td><?= (float)$entry['debit'] > 0 ? format_irr((float)$entry['debit']) : '—' ?></td>
                                <td><?= (float)$entry['credit'] > 0 ? format_irr((float)$entry['credit']) : '—' ?></td>
                                <td>
                                    <?php if (!empty($entry['order_id'])): ?>
                                        <a href="/orders/<?= (int)$entry['order_id'] ?>" style="color:var(--primary); font-weight:bold;">
                                            #<?= e($entry['order_uuid'] ?: (string)$entry['order_id']) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= e($entry['description']) ?></td>
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
