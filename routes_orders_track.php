<?php
declare(strict_types=1);

/**
 * routes_orders_track.php
 * Public & Customer Order Tracking with visual milestone stepper & postal tracking
 */

route('GET', '/(?:orders/)?track(?:\.php)?', [], function () use ($pdo) {
    $user = current_user();
    release_expired_reservations();

    $uuid = strtoupper(trim($_GET['uuid'] ?? $_GET['code'] ?? ''));
    $phone = trim($_GET['phone'] ?? '');
    $normPhone = normalize_phone($phone);

    $order = null;
    $items = [];
    $shop = null;
    $error = '';

    if ($uuid !== '') {
        $where = "WHERE UPPER(o.uuid) = ?";
        $params = [$uuid];

        if ($normPhone) {
            $where .= " AND u.phone = ?";
            $params[] = $normPhone;
        }

        $stmt = $pdo->prepare("
            SELECT o.*, u.nickname AS customer_name, u.phone AS customer_phone, s.name AS shop_name, s.phone AS shop_phone, s.slug AS shop_slug, s.address AS shop_address
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            LEFT JOIN shops s ON s.id = o.shop_id
            $where
        ");
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error = 'سفارشی با این شماره رهگیری' . ($phone ? ' و شماره موبایل' : '') . ' یافت نشد.';
        } else {
            $itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
            $itStmt->execute([$order['id']]);
            $items = $itStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    layout_start('پیگیری وضعیت سفارش', $user ?: ['role' => 'customer', 'nickname' => 'کاربر مهمان']);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>پیگیری وضعیت سفارش</h1>
                <div class="page-sub">رهگیری مرحله‌به‌مرحله سفارش و بررسی کد رهگیری مرسوله پستی</div>
            </div>
        </div>
    </div>

    <!-- TRACKING LOOKUP FORM -->
    <div class="card mb-3" style="max-width:680px; margin:0 auto 20px;">
        <div class="card-body">
            <form method="get" action="/orders/track" style="display:grid; grid-template-columns:1fr 1fr auto; gap:10px; align-items:flex-end;">
                <div class="form-group" style="margin:0;">
                    <label>شماره سفارش (کد ۸ رقمی) *</label>
                    <input class="input" type="text" name="uuid" value="<?= e($uuid) ?>" placeholder="مثلاً: A8B3C2D1" required style="text-transform:uppercase; font-family:monospace; font-weight:700;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>شماره موبایل ثبت‌نامی</label>
                    <input class="input" type="text" name="phone" value="<?= e($phone) ?>" placeholder="۰۹۱۲xxxxxxx">
                </div>
                <button class="btn btn-primary" type="submit"><?= icon('search', 14) ?> استعلام وضعیت</button>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="max-width:680px; margin:0 auto;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($order): ?>
        <?php
        $st = $order['status'];
        // Compute visual milestones
        $isSubmitted = in_array($st, ['submitted', 'placed', 'paid', 'finalised', 'shipped', 'completed'], true);
        $isPaid = in_array($st, ['paid', 'finalised', 'shipped', 'completed'], true) || $order['payment_status'] === 'paid';
        $isShipped = in_array($st, ['shipped', 'completed'], true);
        $isCompleted = ($st === 'completed');
        $isCanceled = ($st === 'canceled');
        ?>

        <div class="card" style="max-width:850px; margin:0 auto;">
            <!-- ORDER HEADER -->
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="font-size:1.15rem; margin-bottom:4px;">سفارش <code><?= e($order['uuid']) ?></code></h2>
                    <div style="font-size:0.8rem; color:var(--muted);">
                        ثبت شده در فروشگاه <strong><?= e($order['shop_name']) ?></strong> | تاریخ ثبت: <?= format_jalali($order['created_at']) ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <?= order_badge($order['status']) ?>
                    <a class="btn btn-outline btn-sm" href="/orders/<?= (int)$order['id'] ?>/print" target="_blank"><?= icon('print', 13) ?> فاکتور رسمی</a>
                </div>
            </div>

            <!-- CANCELED WARNING -->
            <?php if ($isCanceled): ?>
                <div class="alert alert-error" style="margin:16px;">
                    <strong>این سفارش لغو شده است.</strong>
                    <?php if (!empty($order['cancellation_reason'])): ?>
                        <div>دلیل لغو: <?= e($order['cancellation_reason']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($order['canceled_at'])): ?>
                        <div style="font-size:0.75rem; margin-top:4px;">تاریخ لغو: <?= format_jalali($order['canceled_at']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- VISUAL STEPPER TIMELINE -->
            <?php if (!$isCanceled): ?>
                <div style="padding:24px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; text-align:center; position:relative;">
                        <!-- Step 1: Submitted -->
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            <div style="width:36px; height:36px; border-radius:50%; background:<?= $isSubmitted ? '#10b981' : '#e2e8f0' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; margin-bottom:6px;">
                                ✓
                            </div>
                            <strong style="font-size:0.85rem; color:#1e293b;">ثبت سفارش</strong>
                            <span style="font-size:0.7rem; color:#64748b;"><?= format_jalali($order['created_at']) ?></span>
                        </div>

                        <!-- Step 2: Paid -->
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            <div style="width:36px; height:36px; border-radius:50%; background:<?= $isPaid ? '#10b981' : '#cbd5e1' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; margin-bottom:6px;">
                                <?= $isPaid ? '✓' : '۲' ?>
                            </div>
                            <strong style="font-size:0.85rem; color:<?= $isPaid ? '#1e293b' : '#94a3b8' ?>;">تایید پرداخت</strong>
                            <span style="font-size:0.7rem; color:#64748b;"><?= $order['paid_at'] ? format_jalali($order['paid_at']) : 'در انتظار تایید فیش' ?></span>
                        </div>

                        <!-- Step 3: Shipped -->
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            <div style="width:36px; height:36px; border-radius:50%; background:<?= $isShipped ? '#10b981' : '#cbd5e1' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; margin-bottom:6px;">
                                <?= $isShipped ? '✓' : '۳' ?>
                            </div>
                            <strong style="font-size:0.85rem; color:<?= $isShipped ? '#1e293b' : '#94a3b8' ?>;">تحویل به پست</strong>
                            <span style="font-size:0.7rem; color:#64748b;"><?= $order['shipped_at'] ? format_jalali($order['shipped_at']) : 'در حال آماده‌سازی' ?></span>
                        </div>

                        <!-- Step 4: Completed -->
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            <div style="width:36px; height:36px; border-radius:50%; background:<?= $isCompleted ? '#10b981' : '#cbd5e1' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; margin-bottom:6px;">
                                <?= $isCompleted ? '✓' : '۴' ?>
                            </div>
                            <strong style="font-size:0.85rem; color:<?= $isCompleted ? '#1e293b' : '#94a3b8' ?>;">تحویل نهایی</strong>
                            <span style="font-size:0.7rem; color:#64748b;"><?= $order['completed_at'] ? format_jalali($order['completed_at']) : 'تکمیل مرسوله' ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- POSTAL TRACKING BOX (IF SHIPPED) -->
            <?php if (!empty($order['tracking_code'])): ?>
                <div style="margin:16px; background:#eff6ff; border:1.5px dashed #3b82f6; border-radius:10px; padding:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:0.85rem; color:#1e40af; font-weight:700;">کد رهگیری پست پیشتاز مرسوله:</div>
                        <div style="font-size:1.3rem; font-weight:900; font-family:monospace; color:#1e3a8a; margin-top:4px;" dir="ltr">
                            <?= e($order['tracking_code']) ?>
                        </div>
                    </div>
                    <a class="btn btn-primary btn-sm" href="https://tracking.post.ir/?id=<?= urlencode($order['tracking_code']) ?>" target="_blank" rel="noopener noreferrer">
                        پیگیری در سامانه شرکت پست ↗
                    </a>
                </div>
            <?php endif; ?>

            <!-- ORDER SUMMARY DETAILS -->
            <div class="card-body">
                <div class="detail-grid mb-3">
                    <div class="detail-item">
                        <div class="detail-label">نام خریدار</div>
                        <div class="detail-value"><?= e($order['customer_name']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">موبایل خریدار</div>
                        <div class="detail-value" dir="ltr"><?= format_phone($order['customer_phone']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">روش ارسال</div>
                        <div class="detail-value"><?= e($order['shipping_method'] ?: 'پست پیشتاز') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">وضعیت پرداخت</div>
                        <div class="detail-value"><?= payment_badge($order['payment_status']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">مبلغ کل سفارش</div>
                        <div class="detail-value"><?= format_irr((float)($order['final_total'] ?? $order['estimated_total'])) ?></div>
                    </div>
                    <?php if (!empty($order['reservation_expires_at']) && $st === 'submitted'): ?>
                        <div class="detail-item" style="border-color:#f59e0b; background:#fffbeb;">
                            <div class="detail-label" style="color:#b45309;">مهلت واریز و رزرو انبار</div>
                            <div class="detail-value" style="color:#b45309;"><?= format_jalali($order['reservation_expires_at']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ITEMS TABLE -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ردیف</th>
                                <th>عنوان کالا</th>
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
                                    <td><?= en_to_fa_digits((string)$it['quantity']) ?> <?= e($it['unit'] ?: 'عدد') ?></td>
                                    <td><?= format_irr((float)$it['unit_price']) ?></td>
                                    <td><?= format_irr((float)$it['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    layout_end();
});
