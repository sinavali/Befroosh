<?php
declare(strict_types=1);

/**
 * routes_accounting.php
 * Store Accounting Ledger & Trial Balance (/merchant/accounting & /accounting)
 * Design Spec: Double-Entry Matrix, Tabular Numerals, Balance Equality Indicator, Account Hierarchy
 */

route('GET', '/(?:merchant/)?accounting(?:\.php)?', ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin']);
    $shop = get_current_management_shop($user);
    $shopId = (int)($shop['id'] ?? $shop[0] ?? 1);

    // 1. Overall sum of debit and credit across the ledger for this shop (Mathematical Equality Check)
    $stmtLedgerBalance = $pdo->prepare("
        SELECT 
            COALESCE(SUM(debit), 0) AS total_debit,
            COALESCE(SUM(credit), 0) AS total_credit
        FROM accounting_ledger
        WHERE shop_id = ?
    ");
    $stmtLedgerBalance->execute([$shopId]);
    $balanceRow = $stmtLedgerBalance->fetch(PDO::FETCH_ASSOC);
    $ledgerTotalDebit = (float)($balanceRow['total_debit'] ?? 0);
    $ledgerTotalCredit = (float)($balanceRow['total_credit'] ?? 0);
    $ledgerDifference = abs($ledgerTotalDebit - $ledgerTotalCredit);
    $isBalanced = ($ledgerDifference < 0.01);

    // 2. Compute key financial metrics from ledger per account
    $stmtTotals = $pdo->prepare("
        SELECT 
            account,
            COALESCE(SUM(debit), 0) AS total_debit,
            COALESCE(SUM(credit), 0) AS total_credit
        FROM accounting_ledger
        WHERE shop_id = ?
        GROUP BY account
    ");
    $stmtTotals->execute([$shopId]);
    $acctRows = $stmtTotals->fetchAll(PDO::FETCH_ASSOC);

    // Aliases to standardize account keys
    $accountAliases = [
        'موجودی نقد و بانک' => 'cash_bank',
        'درآمد حاصل از فروش کالا' => 'sales_revenue',
        'sales_income' => 'sales_revenue',
        'shipping_income' => 'shipping_revenue',
    ];

    $accounts = [];
    foreach ($acctRows as $ar) {
        $rawCode = trim((string)$ar['account']);
        $code = $accountAliases[$rawCode] ?? $rawCode;
        if (!isset($accounts[$code])) {
            $accounts[$code] = ['debit' => 0.0, 'credit' => 0.0, 'net' => 0.0];
        }
        $accounts[$code]['debit'] += (float)$ar['total_debit'];
        $accounts[$code]['credit'] += (float)$ar['total_credit'];
        $accounts[$code]['net'] = $accounts[$code]['debit'] - $accounts[$code]['credit'];
    }

    $salesRevenue = (float)($accounts['sales_revenue']['credit'] ?? 0);
    $shippingRevenue = (float)($accounts['shipping_revenue']['credit'] ?? 0);
    $cogs = (float)($accounts['cogs']['debit'] ?? 0);
    $taxPayable = (float)(($accounts['tax_payable']['credit'] ?? 0) - ($accounts['tax_payable']['debit'] ?? 0));
    $cashBank = (float)(($accounts['cash_bank']['debit'] ?? 0) - ($accounts['cash_bank']['credit'] ?? 0));
    $inventoryAsset = (float)(($accounts['inventory_asset']['debit'] ?? 0) - ($accounts['inventory_asset']['credit'] ?? 0));
    $discounts = (float)($accounts['discounts']['debit'] ?? 0);
    $grossProfit = $salesRevenue - $cogs;
    $profitMargin = $salesRevenue > 0 ? round(($grossProfit / $salesRevenue) * 100, 1) : 0.0;

    // 3. Recent double-entry journal vouchers
    $stmtRecent = $pdo->prepare("
        SELECT al.*, o.uuid AS order_uuid, u.nickname AS user_name
        FROM accounting_ledger al
        LEFT JOIN orders o ON o.id = al.order_id
        LEFT JOIN users u ON u.id = al.created_by_id
        WHERE al.shop_id = ?
        ORDER BY al.id DESC
        LIMIT 15
    ");
    $stmtRecent->execute([$shopId]);
    $recentEntries = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    // Chart of Accounts Hierarchy Definition
    $chartOfAccounts = [
        [
            'class' => 'دارایی‌های جاری (Assets)',
            'accounts' => [
                'cash_bank' => ['code' => '۱۱۰۱', 'name' => 'نقد و بانک (وجوه دریافتی، کارت‌به‌کارت و پوز)', 'nature' => 'بدهکار', 'is_debit' => true],
                'inventory_asset' => ['code' => '۱۲۰۱', 'name' => 'موجودی کالای انبار (ارزش دفتری دارایی کالا)', 'nature' => 'بدهکار', 'is_debit' => true],
            ]
        ],
        [
            'class' => 'بدهی‌های جاری (Liabilities)',
            'accounts' => [
                'tax_payable' => ['code' => '۲۱۰۱', 'name' => 'مالیات بر ارزش افزوده پرداختنی (سازمان امور مالیاتی)', 'nature' => 'بستانکار', 'is_debit' => false],
            ]
        ],
        [
            'class' => 'درآمدهای عملیاتی و جانبی (Revenues)',
            'accounts' => [
                'sales_revenue' => ['code' => '۳۱۰۱', 'name' => 'درآمد حاصل از فروش قطعی کالا', 'nature' => 'بستانکار', 'is_debit' => false],
                'shipping_revenue' => ['code' => '۳۲۰۱', 'name' => 'درآمد خدمات ارسال و پست پیشتاز', 'nature' => 'بستانکار', 'is_debit' => false],
            ]
        ],
        [
            'class' => 'بهای تمام‌شده و هزینه‌ها (COGS & Expenses)',
            'accounts' => [
                'cogs' => ['code' => '۴۱۰۱', 'name' => 'بهای تمام‌شده کالای فروش‌رفته (COGS)', 'nature' => 'بدهکار', 'is_debit' => true],
                'discounts' => ['code' => '۴۲۰۱', 'name' => 'تخفیفات اعطایی و بازاریابی', 'nature' => 'بدهکار', 'is_debit' => true],
            ]
        ],
        [
            'class' => 'تعدیلات و تسویه (Adjustments & Clearings)',
            'accounts' => [
                'adjustment' => ['code' => '۵۱۰۱', 'name' => 'تعدیلات انبارداری و انکسار ریالی', 'nature' => 'متغیر', 'is_debit' => true],
            ]
        ],
    ];

    layout_start('دفتر کل و تراز آزمایشی دوبل', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>دفتر کل و تراز آزمایشی دوبل (General Ledger & Trial Balance)</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? '—') ?></strong> | ماتریس اسناد دوبل، تراز دفاتر و ساختار کدینگ حساب‌ها</div>
            </div>
        </div>
        <div class="action-cluster">
            <button type="button" class="btn btn-outline" onclick="window.print()"><?= icon('document', 14) ?> چاپ ترازنامه</button>
            <a class="btn btn-outline" href="/accounting/ledger"><?= icon('orders', 14) ?> ریز دفتر روزنامه</a>
            <a class="btn btn-outline" href="/accounting/tax-report"><?= icon('report', 14) ?> گزارش فصلی ارزش افزوده (VAT)</a>
            <a class="btn btn-primary" href="/accounting/manual"><?= icon('plus', 14) ?> ثبت سند دستی</a>
        </div>
    </div>

    <!-- 1. STRICT MATHEMATICAL BALANCE INDICATOR -->
    <div class="balance-check-banner">
        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <div class="balance-metric">
                <span class="balance-metric-label">مجموع کل گردش بدهکار</span>
                <span class="balance-metric-value" style="color:#0f172a;"><?= format_irr($ledgerTotalDebit) ?></span>
            </div>
            <div style="font-size:1.4rem; color:#94a3b8; font-weight:300;">=</div>
            <div class="balance-metric">
                <span class="balance-metric-label">مجموع کل گردش بستانکار</span>
                <span class="balance-metric-value" style="color:#0f172a;"><?= format_irr($ledgerTotalCredit) ?></span>
            </div>
            <div style="width:1px; height:36px; background:#e2e8f0; margin:0 4px;"></div>
            <div class="balance-metric">
                <span class="balance-metric-label">اختلاف تراز دفاتر</span>
                <span class="balance-metric-value" style="color:<?= $isBalanced ? '#059669' : '#dc2626' ?>;">
                    <?= format_irr($ledgerDifference) ?>
                </span>
            </div>
        </div>

        <div>
            <?php if ($isBalanced): ?>
                <div class="badge badge-emerald" style="padding:10px 18px; font-size:0.92rem; font-weight:700; gap:8px;">
                    <span style="font-size:1.15rem;">✓</span> تراز دفاتر کاملاً متعادل است (مجموع بدهکار = مجموع بستانکار | اختلاف ۰ تومان)
                </div>
            <?php else: ?>
                <div class="badge badge-rose" style="padding:10px 18px; font-size:0.92rem; font-weight:700; gap:8px;">
                    <span style="font-size:1.15rem;">✕</span> هشدار عدم تعادل دفاتر: اختلاف <?= format_irr($ledgerDifference) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. KPI METRIC CARDS -->
    <div class="stats-grid mb-3" style="grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));">
        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($salesRevenue) ?></div>
                <div class="stat-label">درآمد خالص حاصل از فروش کالا</div>
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
                <div class="stat-label">سود ناخالص عملیاتی (حاشیه: <?= en_to_fa_digits((string)$profitMargin) ?>٪)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><?= icon('clock', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($taxPayable) ?></div>
                <div class="stat-label">مالیات ارزش افزوده پرداختنی</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('store', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr($cashBank) ?></div>
                <div class="stat-label">موجودی نقد و بانک وصول‌شده</div>
            </div>
        </div>
    </div>

    <!-- 3. TRIAL BALANCE & CHART OF ACCOUNTS HIERARCHY -->
    <div class="card mb-3">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0; font-size:1.1rem;">جدول تراز آزمایشی و ساختار کدینگ حساب‌ها (Chart of Accounts & Trial Balance)</h2>
                <div style="font-size:0.8rem; color:var(--muted); margin-top:2px;">تفکیک طبقات حساب، سرفصل‌های معین، گردش عملیات و مانده نهایی دفاتر</div>
            </div>
            <span class="badge badge-gray">استاندارد حسابداری دوبل</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:70px;">کد حساب</th>
                        <th>سرفصل معین حسابداری</th>
                        <th>طبقه مالی</th>
                        <th>ماهیت</th>
                        <th>گردش بدهکار (تومان)</th>
                        <th>گردش بستانکار (تومان)</th>
                        <th>مانده نهایی حساب (تومان)</th>
                        <th>وضعیت تراز</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sumDebitAll = 0.0;
                    $sumCreditAll = 0.0;

                    foreach ($chartOfAccounts as $group):
                    ?>
                        <tr style="background:#f8fafc; font-weight:700; color:#334155;">
                            <td colspan="8" style="padding:10px 16px; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">
                                <?= e($group['class']) ?>
                            </td>
                        </tr>
                        <?php foreach ($group['accounts'] as $acctKey => $meta):
                            $d = (float)($accounts[$acctKey]['debit'] ?? 0);
                            $c = (float)($accounts[$acctKey]['credit'] ?? 0);
                            $sumDebitAll += $d;
                            $sumCreditAll += $c;
                            $bal = $meta['is_debit'] ? ($d - $c) : ($c - $d);
                        ?>
                            <tr>
                                <td><span class="account-hierarchy-tag"><?= e($meta['code']) ?></span></td>
                                <td>
                                    <strong><?= e($meta['name']) ?></strong>
                                    <div style="font-size:0.75rem; color:#64748b; font-family:'JetBrains Mono', monospace;"><code><?= e($acctKey) ?></code></div>
                                </td>
                                <td style="font-size:0.82rem; color:#475569;"><?= e(explode(' ', $group['class'])[0]) ?></td>
                                <td>
                                    <span class="badge <?= $meta['nature'] === 'بدهکار' ? 'badge-blue' : ($meta['nature'] === 'بستانکار' ? 'badge-purple' : 'badge-gray') ?>" style="font-size:0.72rem;">
                                        <?= e($meta['nature']) ?>
                                    </span>
                                </td>
                                <td style="font-weight:600; font-variant-numeric:tabular-nums;"><?= $d > 0 ? format_irr($d) : '۰' ?></td>
                                <td style="font-weight:600; font-variant-numeric:tabular-nums;"><?= $c > 0 ? format_irr($c) : '۰' ?></td>
                                <td style="font-weight:800; font-variant-numeric:tabular-nums;">
                                    <span style="color:<?= $bal >= 0 ? '#15803d' : '#dc2626' ?>;">
                                        <?= format_irr(abs($bal)) ?> <?= $bal < 0 ? '<small style="color:#dc2626;">(منفی)</small>' : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($d > 0 || $c > 0): ?>
                                        <span class="badge badge-emerald" style="font-size:0.7rem;">فعال و گردش‌دار</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted" style="font-size:0.7rem;">بدون گردش</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f1f5f9; font-weight:800; border-top:2px solid #cbd5e1;">
                        <td colspan="4" style="text-align:left; font-size:0.95rem; padding:14px 16px;">
                            جمع کل تراز آزمایشی (مجموع گردش ستون‌ها):
                        </td>
                        <td style="font-size:1.05rem; color:#0f172a; font-variant-numeric:tabular-nums;">
                            <?= format_irr($ledgerTotalDebit) ?>
                        </td>
                        <td style="font-size:1.05rem; color:#0f172a; font-variant-numeric:tabular-nums;">
                            <?= format_irr($ledgerTotalCredit) ?>
                        </td>
                        <td colspan="2" style="font-size:0.9rem;">
                            <?php if ($isBalanced): ?>
                                <span style="color:#059669; display:inline-flex; align-items:center; gap:4px;">
                                    <?= icon('check', 14) ?> تراز آزمایشی کاملاً مساوی
                                </span>
                            <?php else: ?>
                                <span style="color:#dc2626;">اختلاف: <?= format_irr($ledgerDifference) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 4. DOUBLE-ENTRY JOURNAL VOUCHERS TABLE -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0; font-size:1.1rem;">اسناد ثبت‌شده در دفتر روزنامه (Journal Vouchers)</h2>
                <div style="font-size:0.8rem; color:var(--muted); margin-top:2px;">ردیف‌های ثبت دوطرفه و ارجاع به فاکتورهای فروش و پرداخت</div>
            </div>
            <a class="btn btn-outline btn-sm" href="/accounting/ledger">مشاهده تمام اسناد و فیلتر پیشرفته</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:75px;">شماره سند</th>
                        <th style="width:130px;">تاریخ و ساعت</th>
                        <th>سرفصل معین حسابداری</th>
                        <th>شرح کامل سند</th>
                        <th>مبلغ بدهکار (تومان)</th>
                        <th>مبلغ بستانکار (تومان)</th>
                        <th>سفارش مرجع</th>
                        <th>ثبت‌کننده</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentEntries): ?>
                        <tr><td colspan="9"><?= empty_state('هیچ سند حسابداری در سیستم ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentEntries as $entry): ?>
                            <tr>
                                <td>
                                    <strong style="font-family:'JetBrains Mono', monospace; color:var(--primary); font-size:0.85rem;">
                                        #<?= en_to_fa_digits(str_pad((string)$entry['id'], 5, '0', STR_PAD_LEFT)) ?>
                                    </strong>
                                </td>
                                <td class="date-cell" style="font-size:0.82rem;"><?= format_jalali($entry['entry_date']) ?></td>
                                <td>
                                    <span class="account-hierarchy-tag"><?= e($entry['account']) ?></span>
                                </td>
                                <td style="font-size:0.85rem; max-width:280px;"><?= e($entry['description']) ?></td>
                                <td style="font-weight:700; font-variant-numeric:tabular-nums; color:#0f172a;">
                                    <?= (float)$entry['debit'] > 0 ? format_irr((float)$entry['debit']) : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                                <td style="font-weight:700; font-variant-numeric:tabular-nums; color:#0f172a;">
                                    <?= (float)$entry['credit'] > 0 ? format_irr((float)$entry['credit']) : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['order_id'])): ?>
                                        <a href="/orders/<?= (int)$entry['order_id'] ?>" style="color:var(--primary); font-weight:bold; font-family:'JetBrains Mono', monospace; font-size:0.82rem;">
                                            #<?= e($entry['order_uuid'] ?: (string)$entry['order_id']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.8rem; color:#64748b;"><?= e($entry['user_name'] ?: 'سیستم خودکار') ?></td>
                                <td>
                                    <span class="badge badge-emerald" style="font-size:0.7rem;">✓ قطعی</span>
                                </td>
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
