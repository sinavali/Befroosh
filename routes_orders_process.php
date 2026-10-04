<?php
declare(strict_types=1);

// Card-to-Card Receipt Upload by Customer
route('POST', '/(?:app/)?orders/(\d+)/payment/card-to-card', ['customer', 'business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
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

    $reference = trim($_POST['receipt_description'] ?? $_POST['payment_reference'] ?? '');
    $hasFile = !empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK;

    if (!$hasFile && $reference === '') {
        flash('error', 'ثبت حداقل یکی از دو مورد (تصویر فیش یا شماره پیگیری و توضیحات) الزامی است.');
        redirect("/orders/$id");
    }

    $fileName = null;
    if ($hasFile) {
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
    }

    $pdo->prepare("
        UPDATE orders 
        SET payment_reference = ?, payment_receipt_path = COALESCE(?, payment_receipt_path),
            receipt_description = ?, receipt_image_path = COALESCE(?, receipt_image_path),
            payment_status = 'pending_verification', receipt_status = 'pending',
            payment_reject_reason = NULL, receipt_rejection_reason = NULL,
            seen_by_admin = 0, updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$reference ?: 'ثبت‌شده توسط مشتری', $fileName, $reference, $fileName, $id]);

    flash('success', 'اطلاعات پرداخت با موفقیت ثبت شد و در صف بررسی مدیران قرار گرفت.');
    redirect("/orders/$id");
});

// Verify payment by Shop Staff: approve or reject
route('POST', '/(?:app/)?orders/(\d+)/payment/verify', ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        flash('error', 'سفارش یافت نشد.');
        redirect('/orders');
    }

    if (!can_manage_shop($user, (int)$order['shop_id'])) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به مدیریت این فروشگاه یا سفارش دسترسی ندارید.');
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
            // Update order status to paid and approve receipt
            $pdo->prepare("
                UPDATE orders 
                SET status = 'paid', payment_status = 'paid', paid_at = ?, receipt_status = 'approved', receipt_verified_by = ?, receipt_verified_at = ?, seen_by_customer = 0, updated_at = datetime('now')
                WHERE id = ?
            ")->execute([$now, (int)$user['id'], $now, $id]);

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
            SET payment_status = 'rejected', receipt_status = 'rejected', receipt_rejection_reason = ?, payment_reject_reason = ?, receipt_verified_by = ?, receipt_verified_at = datetime('now'), seen_by_customer = 0, updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$reason, $reason, (int)$user['id'], $id]);

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
    if ($user['role'] !== 'customer' && !can_manage_shop($user, (int)$order['shop_id'])) {
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

    $stmt = $pdo->prepare("SELECT shop_id, customer_id, payment_receipt_path, receipt_image_path FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    $receiptPath = $order['receipt_image_path'] ?? $order['payment_receipt_path'] ?? null;
    if (!$order || empty($receiptPath)) {
        error_page(404, 'فایل یافت نشد', 'فیش پرداخت برای این سفارش ثبت نشده است.');
    }

    if ($user['role'] === 'customer' && (int)$order['customer_id'] !== (int)$user['id']) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فایل دسترسی ندارید.');
    }
    if ($user['role'] !== 'customer' && !can_manage_shop($user, (int)$order['shop_id'])) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به این فایل دسترسی ندارید.');
    }

    $filePath = RECEIPT_UPLOAD_PATH . '/' . basename($receiptPath);
    if (!file_exists($filePath)) {
        error_page(404, 'فایل یافت نشد', 'فایل فیش از روی سرور حذف شده است.');
    }

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
});
