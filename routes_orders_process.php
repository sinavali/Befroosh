<?php
declare(strict_types=1);

/**
 * routes_orders_process.php
 * Order Detail View, Card-to-Card Verification, Shipping, Completion, and Cancellation
 */

// Order Detail View
route('GET', '/orders/(\d+)', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    release_expired_reservations();

    $id = (int)$id;
    $stmt = $pdo->prepare("
        SELECT o.*, u.nickname AS customer_name, u.phone AS customer_phone, u.national_code AS customer_national_code,
               s.name AS shop_name, s.phone AS shop_phone, s.address AS shop_address, s.card_number AS shop_card_number,
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
    $isShopStaff = in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$order['shop_id'] === (int)($user['shop_id'] ?? 0);
    $isPlatformAdmin = in_array($user['role'], ['superadmin', 'admin'], true);

    if (!$isCustomerOwner && !$isShopStaff && !$isPlatformAdmin) {
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
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:10px;">بارگذاری فیش واریزی کارت‌به‌کارت</h3>
                    <form method="post" action="/orders/<?= $id ?>/payment/card-to-card" enctype="multipart/form-data" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px; align-items:flex-end;">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin:0;">
                            <label>شماره پیگیری / شماره ارجاع فیش *</label>
                            <input class="input" type="text" name="payment_reference" required placeholder="کد پیگیری تراکنش بانکی">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>تصویر فیش واریزی (عکس یا PDF) *</label>
                            <input class="input" type="file" name="receipt" accept="image/*,application/pdf" required>
                        </div>
                        <button class="btn btn-primary" type="submit">ارسال فیش جهت بررسی فروشگاه</button>
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
                <a class="btn btn-primary btn-sm" href="https://tracking.post.ir/?id=<?= urlencode($order['tracking_code']) ?>" target="_blank" rel="noopener noreferrer">
                    رهگیری در سامانه پست ↗
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ORDER SUMMARY & ADDRESS -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px; margin-bottom:16px;">
        <!-- Buyer Info -->
        <div class="card">
            <div class="card-header"><h2>مشخصات خریدار و تحویل‌گیرنده</h2></div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">نام خریدار</div>
                        <div class="detail-value"><?= e($order['customer_name']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">تلفن همراه</div>
                        <div class="detail-value" dir="ltr"><?= format_phone($order['customer_phone']) ?></div>
                    </div>
                    <div class="detail-item" style="grid-column:1/-1;">
                        <div class="detail-label">آدرس تحویل</div>
                        <div class="detail-value">
                            <?= e($addr['state'] ?? '') ?>، <?= e($addr['city'] ?? '') ?> — <?= e($addr['address'] ?? '') ?>
                            <?php if (!empty($addr['postal_code'])): ?>
                                <span style="font-size:0.75rem; color:#6b7280;">(کد پستی: <?= e($addr['postal_code']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($order['description'])): ?>
                        <div class="detail-item" style="grid-column:1/-1;">
                            <div class="detail-label">توضیحات خریدار</div>
                            <div class="detail-value"><?= nl2br(e($order['description'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card">
            <div class="card-header"><h2>خلاصه مالی صورتحساب</h2></div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>جمع اقلام:</td>
                        <td style="text-align:left;"><strong><?= format_irr((float)($order['subtotal'] ?: $order['estimated_total'])) ?></strong></td>
                    </tr>
                    <tr>
                        <td>هزینه ارسال پیشتاز:</td>
                        <td style="text-align:left;"><?= format_irr((float)$order['shipping_cost']) ?></td>
                    </tr>
                    <tr>
                        <td>مالیات بر ارزش افزوده:</td>
                        <td style="text-align:left;"><?= format_irr((float)$order['tax_amount']) ?></td>
                    </tr>
                    <tr style="background:#f8fafc; font-size:1.1rem; font-weight:800; color:#1e3a8a;">
                        <td>مبلغ کل سفارش:</td>
                        <td style="text-align:left;"><?= format_irr((float)($order['final_total'] ?? $order['estimated_total'])) ?></td>
                    </tr>
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
                        <th>واحد</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع خط</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td><?= en_to_fa_digits((string)($idx + 1)) ?></td>
                            <td><strong><?= e($it['product_title']) ?></strong></td>
                            <td><code><?= e($it['product_sku'] ?: '—') ?></code></td>
                            <td><?= e($it['unit'] ?: 'عدد') ?></td>
                            <td><?= en_to_fa_digits((string)$it['quantity']) ?></td>
                            <td><?= format_irr((float)$it['unit_price']) ?></td>
                            <td><?= format_irr((float)$it['line_total']) ?></td>
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

    <!-- REJECT PAYMENT MODAL -->
    <div class="modal-backdrop" id="rejectPaymentModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $id ?>/payment/verify">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h3>رد فیش پرداخت</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>دلیل رد فیش * (به مشتری نمایش داده می‌شود)</label>
                        <textarea class="textarea" name="reason" required placeholder="مثال: فیش ناخوانا است یا مبلغ واریزی مغایرت دارد..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" type="submit">ثبت رد فیش</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SHIP ORDER MODAL -->
    <div class="modal-backdrop" id="shipModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $id ?>/ship">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>ارسال مرسوله و درج کد رهگیری پست</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>روش ارسال</label>
                        <input class="input" name="shipping_method" value="پست پیشتاز" required>
                    </div>
                    <div class="form-group">
                        <label>کد رهگیری پستی (بارکد ۲۴ رقمی پست) *</label>
                        <input class="input" name="tracking_code" dir="ltr" required placeholder="مثال: 123456789012345678901234">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">ثبت و تغییر وضعیت به ارسال شده</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CANCEL ORDER MODAL -->
    <div class="modal-backdrop" id="cancelModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $id ?>/cancel">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>لغو سفارش</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>دلیل لغو سفارش * (موجودی انبار به طور خودکار بازگردانده خواهد شد)</label>
                        <textarea class="textarea" name="reason" required placeholder="علت لغو سفارش را وارد نمایید..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" type="submit">تایید لغو سفارش</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

// Card-to-Card Receipt Upload by Customer
route('POST', '/orders/(\d+)/payment/card-to-card', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['status'] === 'canceled') {
        flash('error', 'سفارش معتبر نیست یا قبلاً لغو شده است.');
        redirect("/orders/$id");
    }

    if ($user['role'] === 'customer' && (int)$order['customer_id'] !== (int)$user['id']) {
        error_page(403, 'دسترسی غیرمجاز', 'شما مالک این سفارش نیستید.');
    }

    $reference = trim($_POST['payment_reference'] ?? '');
    if (!$reference) {
        flash('error', 'شماره پیگیری پرداخت بانکی الزامی است.');
        redirect("/orders/$id");
    }

    if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'بارگذاری فایل تصویر فیش الزامی است.');
        redirect("/orders/$id");
    }

    $file = $_FILES['receipt'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        flash('error', 'فرمت فایل فیش باید یکی از فرمت‌های تصویری یا PDF باشد.');
        redirect("/orders/$id");
    }

    if ($file['size'] > 6 * 1024 * 1024) {
        flash('error', 'حداکثر حجم مجاز برای تصویر فیش ۶ مگابایت است.');
        redirect("/orders/$id");
    }

    $fileName = 'receipt_' . $id . '_' . uniqid() . '.' . $ext;
    $targetPath = RECEIPT_UPLOAD_PATH . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        flash('error', 'خطا در ذخیره‌سازی فایل فیش واریزی.');
        redirect("/orders/$id");
    }

    $pdo->prepare("
        UPDATE orders 
        SET payment_reference = ?, payment_receipt_path = ?, payment_status = 'pending_verification',
            payment_reject_reason = NULL, seen_by_admin = 0, updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$reference, $fileName, $id]);

    flash('success', 'فیش پرداخت با موفقیت ارسال شد و در انتظار تایید فروشگاه قرار گرفت.');
    redirect("/orders/$id");
});

// Verify payment by Shop Staff: approve or reject
route('POST', '/orders/(\d+)/payment/verify', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        flash('error', 'سفارش یافت نشد.');
        redirect('/orders');
    }

    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$order['shop_id'] !== (int)($user['shop_id'] ?? 0)) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فروشگاه دسترسی ندارید.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $now = date('Y-m-d H:i:s');
        $grandTotal = (float)($order['final_total'] ?? $order['estimated_total']);
        $subtotal = (float)($order['subtotal'] ?: $grandTotal);
        $tax = (float)($order['tax_amount'] ?? 0);
        $shipping = (float)($order['shipping_cost'] ?? 0);

        $pdo->beginTransaction();
        try {
            // Update order status to paid
            $pdo->prepare("
                UPDATE orders 
                SET status = 'paid', payment_status = 'paid', paid_at = ?, seen_by_customer = 0, updated_at = datetime('now')
                WHERE id = ?
            ")->execute([$now, $id]);

            // Automated Double-Entry Ledger Entries
            $shopId = (int)$order['shop_id'];

            // 1. Debit Cash/Bank
            record_accounting_entry(
                $shopId, 'payment_received', $id, $grandTotal, 0, 'cash_bank',
                "واریز وجه کارت‌به‌کارت سفارش #{$order['uuid']} - پیگیری: {$order['payment_reference']}",
                (int)$user['id'], $now
            );

            // 2. Credit Sales Revenue
            record_accounting_entry(
                $shopId, 'sale_revenue', $id, 0, $subtotal, 'sales_revenue',
                "درآمد حاصل از فروش کالا - سفارش #{$order['uuid']}",
                (int)$user['id'], $now
            );

            // 3. Credit Tax Payable (if any)
            if ($tax > 0) {
                record_accounting_entry(
                    $shopId, 'tax_payable', $id, 0, $tax, 'tax_payable',
                    "مالیات بر ارزش افزوده سفارش #{$order['uuid']}",
                    (int)$user['id'], $now
                );
            }

            // 4. Credit Shipping Revenue (if any)
            if ($shipping > 0) {
                record_accounting_entry(
                    $shopId, 'shipping_revenue', $id, 0, $shipping, 'shipping_revenue',
                    "درآمد حمل و نقل و پست پیشتاز - سفارش #{$order['uuid']}",
                    (int)$user['id'], $now
                );
            }

            $pdo->commit();
            flash('success', 'فیش پرداخت تایید و اسناد حسابداری دوبل به صورت خودکار ثبت گردید.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'خطا در ثبت تایید پرداخت: ' . $e->getMessage());
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'فیش واریزی معتبر نیست.');
        $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'rejected', payment_reject_reason = ?, seen_by_customer = 0, updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$reason, $id]);

        flash('warning', 'فیش پرداخت رد شد و به مشتری اطلاع‌رسانی گردید.');
    }

    redirect("/orders/$id");
});

// Mark order shipped with postal tracking code
route('POST', '/orders/(\d+)/ship', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) redirect('/orders');

    $tracking = trim($_POST['tracking_code'] ?? '');
    $shippingMethod = trim($_POST['shipping_method'] ?? 'پست پیشتاز');

    if (!$tracking) {
        flash('error', 'کد رهگیری پستی الزامی است.');
        redirect("/orders/$id");
    }

    $pdo->prepare("
        UPDATE orders 
        SET status = 'shipped', tracking_code = ?, shipping_method = ?, shipped_at = datetime('now'), seen_by_customer = 0, updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$tracking, $shippingMethod, $id]);

    flash('success', 'مرسوله با موفقیت به وضعیت «ارسال شده» تغییر یافت.');
    redirect("/orders/$id");
});

// Complete order
route('POST', '/orders/(\d+)/complete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $id = (int)$id;

    $pdo->prepare("
        UPDATE orders 
        SET status = 'completed', completed_at = datetime('now'), seen_by_customer = 0, updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$id]);

    flash('success', 'سفارش تکمیل گردید.');
    redirect("/orders/$id");
});

// Cancel order and restore inventory
route('POST', '/orders/(\d+)/cancel', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['status'] === 'canceled') {
        flash('error', 'سفارش یافت نشد یا قبلاً لغو شده است.');
        redirect('/orders');
    }

    // Ownership checks
    if ($user['role'] === 'customer' && (int)$order['customer_id'] !== (int)$user['id']) {
        error_page(403, 'دسترسی غیرمجاز', 'شما دسترسی به این سفارش ندارید.');
    }
    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$order['shop_id'] !== (int)($user['shop_id'] ?? 0)) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فروشگاه دسترسی ندارید.');
    }

    $reason = trim($_POST['reason'] ?? 'لغو توسط کاربر یا مدیر فروشگاه');

    $pdo->beginTransaction();
    try {
        // Restore inventory for all line items
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items->execute([$id]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
            record_inventory_tx(
                (int)$order['shop_id'],
                (int)$it['product_id'],
                'return_in',
                (float)$it['quantity'],
                (float)$it['unit_cost_price'],
                'order_cancel',
                $id,
                "برگشت به انبار بابت لغو سفارش #{$order['uuid']} ({$reason})",
                (int)$user['id']
            );
        }

        $pdo->prepare("
            UPDATE orders 
            SET status = 'canceled', cancellation_reason = ?, canceled_at = datetime('now'),
                payment_status = 'rejected', updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$reason, $id]);

        mark_other_unseen('orders', $id, $user['role'] === 'customer' ? 'customer' : 'admin');

        $pdo->commit();
        flash('success', 'سفارش لغو شد و موجودی انبار به طور خودکار بازگردانده گردید.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'خطا در لغو سفارش: ' . $e->getMessage());
    }

    redirect("/orders/$id");
});

// Secure receipt image streamer
route('GET', '/orders/(\d+)/receipt', ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT shop_id, customer_id, payment_receipt_path FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['payment_receipt_path'])) {
        error_page(404, 'فایل یافت نشد', 'فیش پرداخت برای این سفارش ثبت نشده است.');
    }

    if ($user['role'] === 'customer' && (int)$order['customer_id'] !== (int)$user['id']) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فایل دسترسی ندارید.');
    }
    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$order['shop_id'] !== (int)($user['shop_id'] ?? 0)) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فایل دسترسی ندارید.');
    }

    $filePath = RECEIPT_UPLOAD_PATH . '/' . basename($order['payment_receipt_path']);
    if (!file_exists($filePath)) {
        error_page(404, 'فایل یافت نشد', 'فایل فیش از روی سرور حذف شده است.');
    }

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
});
