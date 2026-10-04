<?php
declare(strict_types=1);

/**
 * routes_orders_print_label.php
 * Postal Shipping Label (برچسب پستی مرسوله پیشتاز)
 */

/**
 * Render Postal Shipping Label(s)
 */
function render_shipping_labels(array $orders): void
{
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>برچسب پستی مرسوله</title>
<link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
<style>
    @page { size: A5 landscape; margin: 8mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #111; font-size: 12px; background: #fff; }
    .label-sheet { max-width: 680px; margin: 0 auto; padding: 12px; border: 2px dashed #334155; border-radius: 10px; }
    .page-break { page-break-after: always; break-after: page; }
    .top-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 12px; }
    .post-badge { background: #1e3a8a; color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 13px; }
    .box-container { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 12px; }
    .sender-box { border: 1.5px solid #94a3b8; border-radius: 8px; padding: 8px 12px; background: #f8fafc; font-size: 11.5px; }
    .receiver-box { border: 2px solid #1e3a8a; border-radius: 8px; padding: 12px 14px; background: #fff; }
    .box-title { font-weight: 800; font-size: 13px; color: #1e3a8a; margin-bottom: 4px; display: flex; justify-content: space-between; }
    .bold-address { font-size: 14px; font-weight: 800; line-height: 1.6; margin: 6px 0; color: #0f172a; }
    .postal-code-box { font-size: 15px; font-weight: 900; letter-spacing: 2px; font-family: monospace; color: #1e3a8a; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; display: inline-block; }
    .footer-bar { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 11px; color: #475569; }
    .no-print-bar { background: #1e293b; color: #fff; padding: 10px; text-align: center; margin-bottom: 16px; }
    .no-print-bar button { padding: 6px 16px; font-weight: bold; background: #3b82f6; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; margin: 0 4px; }
    @media print { .no-print-bar { display: none !important; } .label-sheet { border: 2px solid #000; } }
</style>
</head>
<body>
<div class="no-print-bar">
    <button onclick="window.print()">چاپ برچسب پستی (Print)</button>
    <button onclick="window.close()" style="background:#64748b;">بستن</button>
</div>

<?php foreach ($orders as $index => $order): ?>
<?php
    $addr = json_unsnapshot($order['address_snapshot'] ?? '{}');
    $cust = json_unsnapshot($order['customer_snapshot'] ?? '{}');
?>
<div class="label-sheet <?= ($index < count($orders) - 1) ? 'page-break' : '' ?>">
    <div class="top-header">
        <div class="post-badge">شرکت ملی پست جمهوری اسلامی ایران — پیشتاز</div>
        <div style="text-align:left;">
            <div>شماره سفارش: <strong><?= e($order['uuid']) ?></strong></div>
            <div style="font-size:10px; color:#64748b;">تاریخ: <?= format_jalali($order['created_at']) ?></div>
        </div>
    </div>

    <!-- SENDER BOX -->
    <div class="sender-box">
        <div class="box-title" style="color:#475569; font-size:11px;">
            <span>فرستنده:</span>
            <span>تلفن: <?= format_phone($order['shop_phone']) ?></span>
        </div>
        <div><strong><?= e($order['shop_name']) ?></strong> — <?= e($order['shop_address'] ?: 'تهران') ?></div>
    </div>

    <!-- RECEIVER BOX -->
    <div class="receiver-box">
        <div class="box-title">
            <span>گیرنده: <strong><?= e($order['customer_nickname']) ?></strong></span>
            <span>تلفن همراه: <strong dir="ltr"><?= format_phone($order['customer_phone'] ?: ($cust['phone'] ?? '')) ?></strong></span>
        </div>
        <div class="bold-address">
            استان <?= e($addr['state'] ?? '') ?>، شهر <?= e($addr['city'] ?? '') ?> — <?= e($addr['address'] ?? '') ?>
            <?php if (!empty($addr['description'])): ?>
                <div style="font-size:12px; font-weight:600; color:#475569; margin-top:2px;">(توضیحات: <?= e($addr['description']) ?>)</div>
            <?php endif; ?>
        </div>
        <div style="margin-top:8px;">
            کد پستی ۱۰ رقمی: <span class="postal-code-box"><?= e($addr['postal_code'] ?? '—') ?></span>
        </div>
    </div>

    <!-- FOOTER BAR -->
    <div class="footer-bar">
        <div>روش ارسال: <strong><?= e($order['shipping_method'] ?: 'پست پیشتاز') ?></strong></div>
        <?php if (!empty($order['tracking_code'])): ?>
            <div>بارکد رهگیری پستی: <strong dir="ltr" style="font-family:monospace; font-size:13px;"><?= e($order['tracking_code']) ?></strong></div>
        <?php endif; ?>
        <div>تعداد اقلام: <?= en_to_fa_digits((string)count($order['items'])) ?> قلم</div>
    </div>
</div>
<?php endforeach; ?>
</body>
</html>
<?php
}
