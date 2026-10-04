<?php
declare(strict_types=1);

/**
 * routes_orders_print.php
 * High-Fidelity Printing Engine:
 * - Iranian Official Tax Invoice (صورتحساب رسمی فروش کالا و خدمات)
 * - Postal Shipping Label (برچسب پستی مرسوله پیشتاز)
 * - Multi-Order Bulk Printing with CSS page breaks
 */

// Helper to fetch order with full tenant and customer snapshot
function fetch_printable_order(PDO $pdo, int $orderId, ?array $user): ?array
{
    $stmt = $pdo->prepare("
        SELECT o.*, u.nickname AS customer_nickname, u.phone AS customer_phone, u.national_code AS customer_national_code,
               s.name AS shop_name, s.national_id AS shop_national_id, s.economic_code AS shop_economic_code,
               s.phone AS shop_phone, s.address AS shop_address, s.card_number AS shop_card_number,
               s.card_holder AS shop_card_holder, s.bank_name AS shop_bank_name, s.shaba_number AS shop_shaba
        FROM orders o
        JOIN users u ON u.id = o.customer_id
        LEFT JOIN shops s ON s.id = o.shop_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) return null;

    // Permissions check
    if ($user && $user['role'] === 'customer' && (int)$order['customer_id'] !== (int)$user['id']) {
        return null;
    }
    if ($user && in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$order['shop_id'] !== (int)$user['shop_id']) {
        return null;
    }

    $itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $itStmt->execute([$orderId]);
    $order['items'] = $itStmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
}

// Single Official Tax Invoice
route('GET', '/orders/(\d+)/(?:print|invoice)', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    $order = fetch_printable_order($pdo, (int)$id, $user);

    if (!$order) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به فاکتور این سفارش دسترسی ندارید یا سفارش یافت نشد.');
    }

    render_official_invoices([$order]);
});

// Single Postal Shipping Label
route('GET', '/orders/(\d+)/shipping-label', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    $order = fetch_printable_order($pdo, (int)$id, $user);

    if (!$order) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این سفارش دسترسی ندارید یا سفارش یافت نشد.');
    }

    render_shipping_labels([$order]);
});

// Bulk Print handler: POST /orders/bulk-print
route('POST', '/orders/bulk-print', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();

    $orderIds = $_POST['order_ids'] ?? [];
    $printType = $_POST['print_type'] ?? 'invoice';

    if (empty($orderIds) || !is_array($orderIds)) {
        flash('error', 'حداقل یک سفارش را برای چاپ گروهی انتخاب کنید.');
        redirect('/orders');
    }

    $orders = [];
    foreach ($orderIds as $oid) {
        $ord = fetch_printable_order($pdo, (int)$oid, $user);
        if ($ord) {
            $orders[] = $ord;
        }
    }

    if (empty($orders)) {
        flash('error', 'هیچ سفارش معتبری برای چاپ یافت نشد.');
        redirect('/orders');
    }

    if ($printType === 'shipping_label') {
        render_shipping_labels($orders);
    } else {
        render_official_invoices($orders);
    }
});

/**
 * Render Official Iranian Tax Invoice(s)
 */
function render_official_invoices(array $orders): void
{
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>صورتحساب رسمی فروش کالا و خدمات</title>
<link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 10mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #111; font-size: 11.5px; background: #fff; line-height: 1.4; }
    .sheet { max-width: 780px; margin: 0 auto; padding: 12px; }
    .page-break { page-break-after: always; break-after: page; }
    .header-box { border: 2px solid #1e3a8a; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
    .header-title { text-align: center; flex: 1; }
    .header-title h1 { font-size: 16px; font-weight: 800; color: #1e3a8a; margin-bottom: 2px; }
    .header-meta { font-size: 10.5px; line-height: 1.6; }
    .section-title { background: #f1f5f9; border: 1px solid #cbd5e1; font-weight: 800; font-size: 11px; padding: 4px 8px; border-radius: 4px 4px 0 0; }
    .party-box { border: 1px solid #cbd5e1; border-top: none; padding: 8px 10px; border-radius: 0 0 6px 6px; margin-bottom: 10px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; font-size: 10.5px; }
    .party-col-span-2 { grid-column: span 2; }
    .party-col-span-4 { grid-column: span 4; }
    .label { color: #64748b; font-weight: 600; margin-left: 4px; }
    table.invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.invoice-table th, table.invoice-table td { border: 1px solid #94a3b8; padding: 5px 6px; text-align: center; }
    table.invoice-table th { background: #e2e8f0; font-weight: 700; font-size: 10.5px; }
    table.invoice-table td.text-right { text-align: right; }
    table.invoice-table td.text-left { text-align: left; }
    .totals-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-bottom: 12px; }
    .words-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; display: flex; flex-direction: column; justify-content: space-between; }
    .summary-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .summary-table td { border: 1px solid #cbd5e1; padding: 4px 8px; }
    .summary-table tr.grand-total td { background: #eff6ff; font-weight: 800; font-size: 12px; color: #1e3a8a; }
    .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; height: 90px; margin-top: 10px; }
    .sig-box { text-align: center; font-weight: 700; color: #475569; font-size: 11px; }
    .no-print-bar { background: #1e293b; color: #fff; padding: 10px; text-align: center; margin-bottom: 16px; position: sticky; top: 0; z-index: 999; }
    .no-print-bar button { padding: 6px 16px; font-weight: bold; background: #3b82f6; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 13px; margin: 0 4px; }
    @media print { .no-print-bar { display: none !important; } .sheet { padding: 0; } }
</style>
</head>
<body>
<div class="no-print-bar">
    <button onclick="window.print()">چاپ رسمی فاکتور (Print)</button>
    <button onclick="window.close()" style="background:#64748b;">بستن صفحه</button>
</div>

<?php foreach ($orders as $index => $order): ?>
<?php
    $addr = json_unsnapshot($order['address_snapshot'] ?? '{}');
    $cust = json_unsnapshot($order['customer_snapshot'] ?? '{}');
    $subtotal = (float)($order['subtotal'] ?: $order['estimated_total']);
    $shipping = (float)($order['shipping_cost'] ?? 0);
    $tax = (float)($order['tax_amount'] ?? 0);
    $discount = (float)($order['discount_amount'] ?? 0);
    $finalTotal = (float)($order['final_total'] ?? $order['estimated_total']);
    $totalInWords = number_to_fa_words($finalTotal);
?>
<div class="sheet <?= ($index < count($orders) - 1) ? 'page-break' : '' ?>">
    <!-- HEADER -->
    <div class="header-box">
        <div class="header-meta">
            <div><span class="label">شماره فاکتور:</span> <strong><?= e($order['uuid']) ?></strong></div>
            <div><span class="label">تاریخ صدور:</span> <?= format_jalali($order['created_at']) ?></div>
            <div><span class="label">وضعیت تسویه:</span> <?= payment_status_fa($order['payment_status']) ?></div>
        </div>
        <div class="header-title">
            <h1>صورتحساب فروش کالا و خدمات</h1>
            <div style="font-size:11px; color:#475569;">(ماده ۱۹ قانون مالیات بر ارزش افزوده)</div>
        </div>
        <div class="header-meta" style="text-align:left;" dir="ltr">
            <div>Ref: #<?= en_to_fa_digits((string)$order['id']) ?></div>
            <div><?= en_to_fa_digits(date('Y/m/d')) ?></div>
        </div>
    </div>

    <!-- SELLER DETAILS -->
    <div class="section-title">مشخصات فروشنده</div>
    <div class="party-box">
        <div class="party-col-span-2"><span class="label">فروشگاه / شرکت:</span> <strong><?= e($order['shop_name']) ?></strong></div>
        <div><span class="label">شماره اقتصادی:</span> <?= e($order['shop_economic_code'] ?: '—') ?></div>
        <div><span class="label">شناسه ملی / کد ملی:</span> <?= e($order['shop_national_id'] ?: '—') ?></div>
        <div class="party-col-span-2"><span class="label">نشانی کامل:</span> <?= e($order['shop_address'] ?: 'تهران') ?></div>
        <div><span class="label">تلفن:</span> <?= format_phone($order['shop_phone']) ?></div>
        <div><span class="label">کارت بانکی:</span> <span dir="ltr"><?= format_card_number($order['shop_card_number']) ?></span></div>
    </div>

    <!-- BUYER DETAILS -->
    <div class="section-title">مشخصات خریدار</div>
    <div class="party-box">
        <div class="party-col-span-2"><span class="label">نام شخص / شرکت:</span> <strong><?= e($order['customer_nickname']) ?></strong></div>
        <div><span class="label">کد ملی خریدار:</span> <?= e($order['customer_national_code'] ?: '—') ?></div>
        <div><span class="label">تلفن همراه:</span> <span dir="ltr"><?= format_phone($order['customer_phone'] ?: ($cust['phone'] ?? '')) ?></span></div>
        <div class="party-col-span-2"><span class="label">نشانی تحویل:</span> <?= e($addr['state'] ?? '') ?>، <?= e($addr['city'] ?? '') ?>، <?= e($addr['address'] ?? '') ?></div>
        <div><span class="label">کد پستی:</span> <?= e($addr['postal_code'] ?? '—') ?></div>
        <div><span class="label">روش تحویل:</span> <?= e($order['shipping_method'] ?: 'پست پیشتاز') ?></div>
    </div>

    <!-- GOODS TABLE -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width:30px;">ردیف</th>
                <th style="width:75px;">شناسه کالا</th>
                <th>شرح کالا یا خدمات</th>
                <th style="width:40px;">واحد</th>
                <th style="width:40px;">تعداد</th>
                <th style="width:75px;">مبلغ واحد (تومان)</th>
                <th style="width:65px;">تخفیف</th>
                <th style="width:70px;">مالیات و عوارض</th>
                <th style="width:85px;">مبلغ کل (تومان)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order['items'] as $idx => $it): ?>
                <tr>
                    <td><?= en_to_fa_digits((string)($idx + 1)) ?></td>
                    <td><code><?= e($it['product_sku'] ?: ('PR-' . $it['product_id'])) ?></code></td>
                    <td class="text-right"><strong><?= e($it['product_title']) ?></strong></td>
                    <td><?= e($it['unit'] ?: 'عدد') ?></td>
                    <td><?= en_to_fa_digits((string)$it['quantity']) ?></td>
                    <td class="text-left"><?= format_irr((float)$it['unit_price']) ?></td>
                    <td class="text-left"><?= format_irr(0) ?></td>
                    <td class="text-left"><?= format_irr((float)($it['tax_amount'] ?? 0)) ?></td>
                    <td class="text-left"><strong><?= format_irr((float)$it['line_total']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TOTALS SUMMARY -->
    <div class="totals-grid">
        <div class="words-box">
            <div>
                <span class="label">مبلغ به حروف:</span>
                <strong style="font-size:11.5px; color:#1e3a8a;"><?= e($totalInWords) ?> تومان</strong>
            </div>
            <div style="font-size:10px; color:#64748b; margin-top:6px; line-height:1.5;">
                شرایط پرداخت: کارت‌به‌کارت بانکی. تحویل کالا منوط به تایید فیش واریزی در حساب فروشگاه است.
                <?php if (!empty($order['tracking_code'])): ?>
                    <br>کد رهگیری پستی مرسوله: <strong dir="ltr"><?= e($order['tracking_code']) ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <table class="summary-table">
            <tr>
                <td class="label">جمع کل کالاها:</td>
                <td style="text-align:left;"><?= format_irr($subtotal) ?></td>
            </tr>
            <tr>
                <td class="label">هزینه بسته‌بندی و ارسال:</td>
                <td style="text-align:left;"><?= format_irr($shipping) ?></td>
            </tr>
            <tr>
                <td class="label">جمع مالیات بر ارزش افزوده:</td>
                <td style="text-align:left;"><?= format_irr($tax) ?></td>
            </tr>
            <tr class="grand-total">
                <td>مبلغ کل صورتحساب:</td>
                <td style="text-align:left;"><?= format_irr($finalTotal) ?></td>
            </tr>
        </table>
    </div>

    <!-- SIGNATURES & STAMPS -->
    <div class="signatures">
        <div class="sig-box">مهر و امضای فروشنده</div>
        <div class="sig-box">مهر و امضای خریدار / گیرنده</div>
    </div>
</div>
<?php endforeach; ?>
</body>
</html>
<?php
}

require_once __DIR__ . '/routes_orders_print_label.php';
