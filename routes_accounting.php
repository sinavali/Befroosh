<?php
declare(strict_types=1);

// Accounting Dashboard & P&L Summary
route('GET', '/accounting(?:\.php)?', ['shop_owner', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'admin', 'superadmin']);
    $shop = get_current_management_shop($user);
    $shopId = (int)$shop['id'];

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

require_once __DIR__ . '/routes_accounting_ledger.php';
