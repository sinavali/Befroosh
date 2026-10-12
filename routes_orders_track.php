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

    if (!$user || $user['role'] === 'customer') {
        layout_customer_start('پیگیری وضعیت سفارش', 'track', $user ?: ['role' => 'customer', 'nickname' => 'کاربر مهمان']);
    } else {
        layout_start('پیگیری وضعیت سفارش', $user);
    }
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

            <!-- 5-STAGE MILESTONE STEPPER -->
            <?php
            $isCanceled = ($order['status'] === 'canceled');
            $currentStep = 1;
            if (!$isCanceled) {
                if ($order['status'] === 'completed') {
                    $currentStep = 5;
                } elseif ($order['status'] === 'shipped' || !empty($order['tracking_code'])) {
                    $currentStep = 4;
                } elseif (in_array($order['status'], ['paid', 'finalised'], true)) {
                    $currentStep = 3;
                } elseif ($order['payment_status'] === 'paid') {
                    $currentStep = 2;
                } else {
                    $currentStep = ($order['payment_status'] === 'pending_verification') ? 2 : 1;
                }
            }
            $milestones = [
                1 => 'ثبت و تأیید سفارش',
                2 => 'تأیید فیش بانکی',
                3 => 'بسته‌بندی و صدور بارنامه',
                4 => 'تحویل به پست پیشتاز',
                5 => 'تحویل به گیرنده'
            ];
            ?>
            <div class="card mb-3 no-print" style="padding:20px 24px; margin:16px;">
                <div class="stepper-5">
                    <div class="stepper-track">
                        <div class="stepper-track-fill" style="width: <?= $isCanceled ? 0 : (($currentStep - 1) / 4 * 100) ?>%;"></div>
                    </div>
                    <?php foreach ($milestones as $stepNum => $stepLabel): ?>
                        <?php
                        $done = (!$isCanceled && ($currentStep > $stepNum || ($order['status'] === 'completed' && $stepNum === 5)));
                        $active = (!$isCanceled && $currentStep === $stepNum && $order['status'] !== 'completed');
                        ?>
                        <div class="stepper-item <?= $done ? 'done' : ($active ? 'active' : '') ?>">
                            <div class="stepper-circle">
                                <?= en_to_fa_digits((string)$stepNum) ?>
                            </div>
                            <div class="stepper-label">
                                <?= e($stepLabel) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- POSTAL TRACKING BOX (IF SHIPPED) -->
            <?php if (!empty($order['tracking_code'])): ?>
                <div class="barcode-tracking-box no-print" style="margin:16px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <strong style="color:#0071e3; font-size:0.95rem;">مرسوله به شرکت پست پیشتاز تحویل داده شد</strong>
                            <span class="badge badge-blue">۲۴ رقمی معتبر</span>
                        </div>
                        <div style="font-size:0.82rem; color:var(--muted); margin-bottom:6px;">
                            شماره بارنامه پستی جمهوری اسلامی ایران جهت استعلام وضعیت:
                        </div>
                        <div class="barcode-number" id="postalBarcodeVal">
                            <?= e($order['tracking_code']) ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText('<?= e($order['tracking_code']) ?>'); window.BefrooshStore.showToast('کد ۲۴ رقمی رهگیری کپی شد.');">
                            کپی بارکد
                        </button>
                        <a class="btn btn-primary btn-sm" href="https://tracking.post.ir/?id=<?= urlencode($order['tracking_code']) ?>" target="_blank" rel="noopener noreferrer">
                            استعلام در سامانه پست
                        </a>
                    </div>
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
                        <div class="detail-value tabular-nums"><?= format_irt((float)($order['final_total'] ?? $order['estimated_total'])) ?></div>
                    </div>
                    <?php if (!empty($order['reservation_expires_at']) && $st === 'submitted'): ?>
                        <div class="detail-item" style="border-color:rgba(255,149,0,0.3); background:var(--amber-bg);">
                            <div class="detail-label" style="color:#a05e00;">مهلت واریز و رزرو انبار</div>
                            <div class="detail-value tabular-nums" style="color:#a05e00;"><?= format_jalali($order['reservation_expires_at']) ?></div>
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
                                    <td>
                                        <a href="/b/<?= urlencode($order['shop_slug'] ?? 'central') ?>/p/<?= (int)$it['product_id'] ?>" target="_blank" style="color:var(--primary); font-weight:700;">
                                            <?= e($it['product_title']) ?>
                                        </a>
                                    </td>
                                    <td class="tabular-nums"><?= en_to_fa_digits((string)$it['quantity']) ?> <?= e($it['unit'] ?: 'عدد') ?></td>
                                    <td class="tabular-nums"><?= format_irt((float)$it['unit_price']) ?></td>
                                    <td class="tabular-nums"><?= format_irt((float)$it['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    if (!$user || $user['role'] === 'customer') {
        layout_customer_end();
    } else {
        layout_end();
    }
});
