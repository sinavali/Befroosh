<?php
declare(strict_types=1);

/**
 * routes_orders_process.php
 * Order Detail View, Card-to-Card Verification, Shipping, Completion, and Cancellation
 */

// Order Detail View
route('GET', '/(?:app/)?orders/(\d+)', ['customer', 'business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    release_expired_reservations();

    $id = (int)$id;
    $stmt = $pdo->prepare("
        SELECT o.*, u.nickname AS customer_name, u.phone AS customer_phone, u.national_code AS customer_national_code,
               s.name AS shop_name, s.slug AS shop_slug, s.phone AS shop_phone, s.address AS shop_address, s.card_number AS shop_card_number,
               s.card_holder AS shop_card_holder, s.bank_name AS shop_bank_name, s.shaba_number AS shop_shaba
        FROM orders o
        JOIN users u ON u.id = o.customer_id
        LEFT JOIN shops s ON s.id = o.shop_id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_page(404, 'سفارش یافت نشد', 'سفارش مورد نظر وجود ندارد.');
    }

    // Tenant / Ownership checks
    $isCustomerOwner = ($user['role'] === 'customer' && (int)$order['customer_id'] === (int)$user['id']);
    $isShopStaff = can_manage_shop($user, (int)$order['shop_id']);

    if (!$isCustomerOwner && !$isShopStaff) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این سفارش دسترسی ندارید.');
    }

    // Fetch line items
    $itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $itStmt->execute([$id]);
    $items = $itStmt->fetchAll(PDO::FETCH_ASSOC);

    // Active bank cards of shop
    $activeCards = get_shop_active_cards((int)$order['shop_id']);

    // Mark seen
    update_seen('orders', $id, $user['role'] === 'customer' ? 'customer' : 'admin');

    $addr = json_unsnapshot($order['address_snapshot'] ?? '{}');
    $cust = json_unsnapshot($order['customer_snapshot'] ?? '{}');

    $canUploadReceipt = ($isCustomerOwner || $isShopStaff || $isPlatformAdmin) && in_array($order['payment_status'], ['unpaid', 'rejected'], true) && $order['status'] !== 'canceled';
    $canVerifyPayment = ($isShopStaff || $isPlatformAdmin) && $order['payment_status'] === 'pending_verification';
    $canShip = ($isShopStaff || $isPlatformAdmin) && in_array($order['status'], ['paid', 'finalised'], true);
    $canComplete = ($isShopStaff || $isPlatformAdmin) && $order['status'] === 'shipped';
    $canCancel = in_array($order['status'], ['submitted', 'placed', 'paid'], true) && ($isCustomerOwner || $isShopStaff || $isPlatformAdmin);

    layout_start('جزئیات سفارش ' . $order['uuid'], $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>سفارش <?= e($order['uuid']) ?></h1>
                <div class="page-sub">
                    ثبت شده در فروشگاه <strong><?= e($order['shop_name']) ?></strong> | <?= format_jalali($order['created_at']) ?>
                </div>
            </div>
        </div>

        <div class="action-cluster no-print">
            <?= order_badge($order['status']) ?>
            <?= payment_badge($order['payment_status']) ?>
            <a class="btn btn-outline btn-sm" href="/orders/<?= $id ?>/print" target="_blank"><?= icon('print', 13) ?> چاپ فاکتور رسمی</a>
            <a class="btn btn-outline btn-sm" href="/orders/<?= $id ?>/shipping-label" target="_blank">برچسب پستی</a>
        </div>
    </div>

    <!-- TIMELINE STATUS STEPPER -->
    <?php
    $statusSteps = ['placed' => 1, 'submitted' => 1, 'paid' => 2, 'finalised' => 2, 'shipped' => 3, 'completed' => 4];
    $currentStep = $statusSteps[$order['status']] ?? 1;
    $isCanceled = ($order['status'] === 'canceled');
    ?>
    <div class="card mb-3 no-print" style="padding:16px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; position:relative;">
            <div style="position:absolute; top:18px; left:20px; right:20px; height:3px; background:#e2e8f0; z-index:1;"></div>
            <?php
            $steps = [1 => 'ثبت فاکتور', 2 => 'پرداخت و تایید', 3 => 'ارسال مرسوله', 4 => 'تکمیل سفارش'];
            foreach ($steps as $stepNum => $stepLabel):
                $done = (!$isCanceled && $currentStep >= $stepNum);
                $active = (!$isCanceled && $currentStep === $stepNum);
            ?>
                <div style="position:relative; z-index:2; text-align:center; background:#fff; padding:0 8px;">
                    <div style="width:34px; height:34px; border-radius:50%; margin:0 auto 4px; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.85rem; background:<?= $done ? '#10b981' : ($active ? '#2563eb' : '#f1f5f9') ?>; color:<?= ($done || $active) ? '#fff' : '#64748b' ?>; border:2px solid <?= $done ? '#10b981' : ($active ? '#2563eb' : '#cbd5e1') ?>;">
                        <?= $done ? '✓' : en_to_fa_digits((string)$stepNum) ?>
                    </div>
                    <div style="font-size:0.75rem; font-weight:<?= $active ? '800' : 'bold' ?>; color:<?= $active ? '#2563eb' : ($done ? '#0f172a' : '#94a3b8') ?>;">
                        <?= $stepLabel ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RESERVATION NOTICE -->
    <?php if ($order['status'] === 'submitted' && !empty($order['reservation_expires_at'])): ?>
        <div class="alert alert-warning mb-3">
            <strong>توجه:</strong> اقلام این سفارش تا تاریخ <strong><?= format_jalali($order['reservation_expires_at']) ?></strong> در انبار رزرو است. لطفاً نسبت به پرداخت کارت‌به‌کارت و بارگذاری فیش اقدام فرمایید.
        </div>
    <?php endif; ?>

    <?php if ($order['status'] === 'canceled'): ?>
        <div class="alert alert-error mb-3">
            <strong>این سفارش لغو شده است.</strong>
            <?php if (!empty($order['cancellation_reason'])): ?>
                <div>علت لغو: <?= e($order['cancellation_reason']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- CARD-TO-CARD PAYMENT BLOCK -->
    <div class="card mb-3">
        <div class="card-header">
            <h2>اطلاعات و وضعیت پرداخت کارت‌به‌کارت</h2>
        </div>
        <div class="card-body">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
                <div style="flex:1; min-width:260px;">
                    <div style="font-size:0.85rem; color:#64748b; margin-bottom:6px;">شماره کارت‌های معتبر این فروشگاه جهت واریز:</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($activeCards as $card): ?>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:700; color:#1e293b;"><?= e($card['card_holder']) ?> — <?= e($card['bank_name'] ?: 'بانک') ?></div>
                                    <div style="font-size:0.8rem; color:#64748b;">انقضای کارت: <?= e($card['expires_at'] ?: 'نامحدود') ?></div>
                                </div>
                                <div style="font-size:1.15rem; font-weight:900; font-family:monospace; color:#1e3a8a;" dir="ltr">
                                    <?= format_card_number($card['card_number']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Status Details -->
                <div style="flex:1; min-width:260px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px;">
                    <div style="font-size:0.85rem; margin-bottom:8px;">
                        وضعیت پرداخت: <?= payment_badge($order['payment_status']) ?>
                    </div>

                    <?php if ($order['payment_reference']): ?>
                        <div style="font-size:0.85rem; margin-bottom:6px;">
                            شماره پیگیری / ارجاع: <strong dir="ltr"><?= e($order['payment_reference']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['paid_at']): ?>
                        <div style="font-size:0.85rem; color:#059669; margin-bottom:6px;">
                            تاریخ تایید پرداخت: <?= format_jalali($order['paid_at']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['payment_receipt_path']): ?>
                        <div style="margin-top:10px;">
                            <a class="btn btn-outline btn-sm" href="/orders/<?= $id ?>/receipt" target="_blank">
                                <?= icon('eye', 13) ?> مشاهده تصویر فیش بارگذاری‌شده
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['payment_reject_reason']): ?>
                        <div class="alert alert-error mt-2" style="font-size:0.8rem; padding:8px;">
                            علت رد فیش قبلی: <?= e($order['payment_reject_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- VERIFICATION BUTTONS FOR SHOP STAFF -->
                    <?php if ($canVerifyPayment): ?>
                        <div style="display:flex; gap:8px; margin-top:14px;">
                            <form method="post" action="/orders/<?= $id ?>/payment/verify" style="flex:1;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-success btn-sm" style="width:100%;">تایید فیش واریزی</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" style="flex:1;" data-modal="#rejectPaymentModal">رد فیش</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CUSTOMER RECEIPT UPLOAD FORM -->
            <?php if ($canUploadReceipt): ?>
                <div style="margin-top:20px; border-top:1px solid #e5e7eb; padding-top:16px;">
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:4px;">ثبت اطلاعات یا فیش پرداخت کارت‌به‌کارت</h3>
                    <div style="font-size:0.8rem; color:#64748b; margin-bottom:10px;">بارگذاری تصویر فیش، شماره پیگیری/توضیحات واریز یا هر دو مورد مجاز است.</div>
                    <form method="post" action="/orders/<?= $id ?>/payment/card-to-card" enctype="multipart/form-data" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; align-items:flex-end;">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.8rem;">شماره پیگیری / توضیحات واریز:</label>
                            <input class="input" type="text" name="receipt_description" placeholder="کد رهگیری یا شماره ارجاع">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.8rem;">تصویر فیش واریزی (عکس یا PDF):</label>
                            <input class="input" type="file" name="receipt" accept="image/*,application/pdf">
                        </div>
                        <button class="btn btn-primary" type="submit">ارسال اطلاعات پرداخت</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- POSTAL TRACKING BOX (IF SHIPPED) -->
    <?php if (!empty($order['tracking_code'])): ?>
        <div class="card mb-3" style="background:#eff6ff; border:1px solid #93c5fd;">
            <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="font-size:0.95rem; color:#1e40af; margin-bottom:4px;">مرسوله ارسال شده است — شرکت پست پیشتاز</h3>
                    <div>کد رهگیری پستی: <strong dir="ltr" style="font-family:monospace; font-size:1.2rem;"><?= e($order['tracking_code']) ?></strong></div>
                </div>
                <a class="btn btn-primary btn-sm" href="https://tracking.post.ir/?id=<?= urlencode($order['tracking_code']) ?>" target="_blank" rel="noopener noreferrer">رهگیری در سامانه پست ↗</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ORDER SUMMARY & ADDRESS -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px; margin-bottom:16px;">
        <div class="card">
            <div class="card-header"><h2>مشخصات خریدار و تحویل‌گیرنده</h2></div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item"><div class="detail-label">نام خریدار</div><div class="detail-value"><?= e($order['customer_name']) ?></div></div>
                    <div class="detail-item"><div class="detail-label">تلفن همراه</div><div class="detail-value" dir="ltr"><?= format_phone($order['customer_phone']) ?></div></div>
                    <div class="detail-item" style="grid-column:1/-1;"><div class="detail-label">آدرس تحویل</div><div class="detail-value"><?= e($addr['state'] ?? '') ?>، <?= e($addr['city'] ?? '') ?> — <?= e($addr['address'] ?? '') ?><?php if (!empty($addr['postal_code'])): ?><span style="font-size:0.75rem; color:#6b7280;"> (کد پستی: <?= e($addr['postal_code']) ?>)</span><?php endif; ?></div></div>
                    <?php if (!empty($order['description'])): ?><div class="detail-item" style="grid-column:1/-1;"><div class="detail-label">توضیحات خریدار</div><div class="detail-value"><?= nl2br(e($order['description'])) ?></div></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>خلاصه مالی صورتحساب</h2></div>
            <div class="card-body">
                <table class="table">
                    <tr><td>جمع اقلام:</td><td style="text-align:left;"><strong><?= format_irt((float)($order['subtotal'] ?: $order['estimated_total'])) ?></strong></td></tr>
                    <tr><td>هزینه ارسال:</td><td style="text-align:left;"><?= format_irt((float)$order['shipping_cost']) ?></td></tr>
                    <tr><td>مالیات بر ارزش افزوده:</td><td style="text-align:left;"><?= format_irt((float)$order['tax_amount']) ?></td></tr>
                    <tr style="background:#f8fafc; font-size:1.1rem; font-weight:800; color:#1e3a8a;"><td>مبلغ کل سفارش:</td><td style="text-align:left;"><?= format_irt((float)($order['final_total'] ?? $order['estimated_total'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ORDER ITEMS TABLE -->
    <div class="card mb-3">
        <div class="card-header"><h2>اقلام سفارش</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>عنوان کالا</th>
                        <th>کد کالا (SKU)</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع خط</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td><?= en_to_fa_digits((string)($idx + 1)) ?></td>
                            <td>
                                <a href="/s/<?= urlencode($order['shop_slug'] ?? 'central') ?>/p/<?= (int)$it['product_id'] ?>" target="_blank" style="color:#2563eb; font-weight:bold;">
                                    <?= e($it['product_title']) ?> ↗
                                </a>
                            </td>
                            <td><code><?= e($it['product_sku'] ?: '—') ?></code></td>
                            <td><?= en_to_fa_digits((string)$it['quantity']) ?></td>
                            <td><?= format_irt((float)$it['unit_price']) ?></td>
                            <td><?= format_irt((float)$it['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ACTIONS CLUSTER FOR SHOP STAFF -->
    <div class="card no-print">
        <div class="card-header"><h2>عملیات سفارش</h2></div>
        <div class="card-body" style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php if ($canShip): ?>
                <button type="button" class="btn btn-primary" data-modal="#shipModal">
                    ثبت کد رهگیری و ارسال مرسوله
                </button>
            <?php endif; ?>

            <?php if ($canComplete): ?>
                <form method="post" action="/orders/<?= $id ?>/complete" class="inline-form" data-confirm="آیا تحویل مرسوله و تکمیل سفارش تایید می‌شود؟">
                    <?= csrf_field() ?>
                    <button class="btn btn-success">تکمیل نهایی سفارش</button>
                </form>
            <?php endif; ?>

            <?php if ($canCancel): ?>
                <button type="button" class="btn btn-danger" data-modal="#cancelModal">
                    لغو سفارش
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/routes_orders_modals.php';
    render_order_modals($id);

    layout_end();
});

