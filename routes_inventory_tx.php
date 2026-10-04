<?php
declare(strict_types=1);

/**
 * routes_inventory_tx.php
 * Cardex / Stock Movement Transactions & Printable Count Sheet
 */

// Cardex & Stock Movement Transactions
route('GET', '/inventory/transactions', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;

    $where = "WHERE it.shop_id = ?";
    $params = [$shopId];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_transactions it $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT it.*, p.title AS product_title, p.sku AS product_sku, p.unit AS product_unit, u.nickname AS user_name
        FROM inventory_transactions it
        JOIN products p ON p.id = it.product_id
        LEFT JOIN users u ON u.id = it.created_by_id
        $where
        ORDER BY it.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('گردش انبار و کاردکس کالاها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>کاردکس انبار و سوابق ورود و خروج</h1>
                <div class="page-sub">ردگیری کلیه تراکنش‌های انبار شامل خرید، سفارش مشتری، تعدیل و ضایعات</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/inventory">بازگشت به انبار</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>تاریخ و زمان</th>
                        <th>نام کالا</th>
                        <th>نوع عملیات</th>
                        <th>تعداد</th>
                        <th>بهای تمام‌شده واحد</th>
                        <th>مبلغ کل</th>
                        <th>کاربر ثبت‌کننده</th>
                        <th>توضیحات و مرجع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9"><?= empty_state('هیچ تراکنش انباری ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php $isPlus = in_array($r['type'], ['inward', 'adjustment_plus', 'return_in'], true); ?>
                            <tr>
                                <td><?= en_to_fa_digits((string)$r['id']) ?></td>
                                <td class="date-cell"><?= format_jalali($r['created_at']) ?></td>
                                <td><strong><?= e($r['product_title']) ?></strong> (<?= e($r['product_sku'] ?: '—') ?>)</td>
                                <td>
                                    <span class="badge badge-<?= $isPlus ? 'emerald' : 'rose' ?>">
                                        <?= inv_tx_type_fa($r['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:<?= $isPlus ? '#15803d' : '#dc2626' ?>;">
                                        <?= $isPlus ? '+' : '-' ?><?= en_to_fa_digits((string)(float)$r['quantity']) ?>
                                    </strong>
                                    <?= e($r['product_unit'] ?: 'عدد') ?>
                                </td>
                                <td><?= format_irr((float)$r['unit_cost']) ?></td>
                                <td><?= format_irr((float)$r['unit_cost'] * (float)$r['quantity']) ?></td>
                                <td><?= e($r['user_name'] ?: 'سیستم') ?></td>
                                <td><?= e($r['notes'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/inventory/transactions', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

// Printable Inventory Count Sheet (برگه چاپی انبارگردانی)
route('GET', '/inventory/count-sheet', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $shop = current_shop();

    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.shop_id = ? AND p.deleted_at IS NULL
        ORDER BY c.name ASC, p.title ASC
    ");
    $stmt->execute([$shopId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>برگه انبارگردانی - <?= e($shop['name'] ?? 'فروشگاه') ?></title>
        <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
        <style>
            @page { size: A4; margin: 15mm; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #111; font-size: 11pt; background: #fff; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
            .header h1 { font-size: 16pt; margin-bottom: 4px; }
            .meta { display: flex; justify-content: space-between; font-size: 10pt; color: #333; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #333; padding: 6px 8px; text-align: right; }
            th { background: #f3f4f6; font-weight: bold; font-size: 10pt; }
            .count-col { width: 90px; }
            .sig-box { margin-top: 40px; display: flex; justify-content: space-between; padding: 0 40px; }
            .no-print-btn { padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 10pt; margin-bottom: 16px; }
            @media print { .no-print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="no-print-btn" onclick="window.print()">چاپ برگه انبارگردانی (Ctrl+P)</button>
        <div class="header">
            <h1>برگه رسمی شمارش موجودی و انبارگردانی</h1>
            <div><?= e($shop['name'] ?? 'فروشگاه') ?></div>
        </div>
        <div class="meta">
            <div>تاریخ تنظیم: <?= en_to_fa_digits(jalali_today(true)) ?></div>
            <div>مسئول شمارش انبار: <?= e($user['nickname']) ?></div>
            <div>تعداد اقلام: <?= en_to_fa_digits((string)count($products)) ?> قلم کالا</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:30px;">ردیف</th>
                    <th>کد کالا (SKU)</th>
                    <th>بارکد</th>
                    <th>نام کالا</th>
                    <th>دسته‌بندی</th>
                    <th>واحد</th>
                    <th>موجودی سیستمی</th>
                    <th class="count-col">شمارش اول</th>
                    <th class="count-col">شمارش دوم (مغایرت)</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($products as $p): ?>
                    <tr>
                        <td><?= en_to_fa_digits((string)$i++) ?></td>
                        <td><?= e($p['sku'] ?: '—') ?></td>
                        <td><?= e($p['barcode'] ?: '—') ?></td>
                        <td><strong><?= e($p['title']) ?></strong></td>
                        <td><?= e($p['category_name'] ?: 'عمومی') ?></td>
                        <td><?= e($p['unit'] ?: 'عدد') ?></td>
                        <td><?= en_to_fa_digits((string)(float)$p['stock_quantity']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sig-box">
            <div>امضای انباردار:<br><br>____________________</div>
            <div>امضای سرپرست انبارگردانی:<br><br>____________________</div>
            <div>امضای مدیریت فروشگاه:<br><br>____________________</div>
        </div>
    </body>
    </html>
    <?php
});
