<?php
declare(strict_types=1);

/**
 * routes_customer.php
 * Dedicated Customer Portal Hub (/my and /account)
 * Decoupled from Admin Dashboard, Apple HIG Light Mode
 */

route('GET', '/(?:my|account)(?:\.php)?', ['customer', 'business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
    $stmt->execute([$user['id']]);
    $totalOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status IN ('placed', 'submitted')");
    $stmt->execute([$user['id']]);
    $placedOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'shipped'");
    $stmt->execute([$user['id']]);
    $shippedOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $completedOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(final_total, estimated_total)), 0) FROM orders WHERE customer_id = ? AND status IN ('finalised','completed','paid')");
    $stmt->execute([$user['id']]);
    $totalSpend = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND status = 'open'");
    $stmt->execute([$user['id']]);
    $openTickets = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT o.*, s.name AS shop_name, s.slug AS shop_slug
        FROM orders o
        LEFT JOIN shops s ON s.id = o.shop_id
        WHERE o.customer_id = ?
        ORDER BY o.id DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT o.*, s.name AS shop_name, s.slug AS shop_slug
        FROM orders o
        LEFT JOIN shops s ON s.id = o.shop_id
        WHERE o.customer_id = ?
        ORDER BY o.id DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_customer_start('خلاصه حساب و میز کار', 'overview', $user);
    ?>
    <div class="page-header" style="margin-bottom:24px;">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('dashboard', 18) ?></div>
            <div>
                <h1>درود، <?= e($user['nickname']) ?></h1>
                <div class="page-sub">میز کار شخصی شما | خلاصه سفارشات، رهگیری مرسولات و پشتیبانی</div>
            </div>
        </div>

        <div class="action-cluster">
            <a class="btn btn-outline" href="/orders/track"><?= icon('search', 14) ?> پیگیری با کد پستی</a>
            <a class="btn btn-outline" href="/tickets/create"><?= icon('tickets', 14) ?> تیکت جدید</a>
            <a class="btn btn-primary" href="/shops"><?= icon('plus', 14) ?> مرور فروشگاه‌ها</a>
        </div>
    </div>

    <!-- APPLE LIGHT METRIC CARDS -->
    <div class="stats-grid mb-3">
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">کل سفارش‌ها</span>
                <span class="badge badge-muted">ثبت‌شده</span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $totalOrders) ?></div>
            <div class="metric-sub">سفارش‌های ثبت شده در حساب شما</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">در انتظار نهایی‌سازی</span>
                <span class="badge badge-amber">جاری</span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $placedOrders) ?></div>
            <div class="metric-sub">نیاز به تسویه یا تایید پیش‌فاکتور</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">مرسولات در مسیر ارسال</span>
                <span class="badge badge-blue">پست</span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $shippedOrders) ?></div>
            <div class="metric-sub">دارای بارکد ۲۴ رقمی پست پیشتاز</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">تحویل‌شده</span>
                <span class="badge badge-emerald">موفق</span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $completedOrders) ?></div>
            <div class="metric-sub">سفارش‌های نهایی و تحویل شده</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">مجموع خرید نهایی</span>
                <span class="badge badge-blue">تومان</span>
            </div>
            <div class="metric-value"><?= format_irr($totalSpend) ?></div>
            <div class="metric-sub">ارزش کل فاکتورهای شما</div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-label">تیکت‌های باز</span>
                <span class="badge badge-rose"><?= $openTickets > 0 ? 'پاسخ لازم' : 'عادی' ?></span>
            </div>
            <div class="metric-value"><?= en_to_fa_digits((string) $openTickets) ?></div>
            <div class="metric-sub">درخواست‌های فعال پشتیبانی</div>
        </div>
    </div>

    <!-- LAST ORDER SPOTLIGHT -->
    <div class="card mb-3">
        <div class="card-header">
            <h2>آخرین وضعیت سفارش شما</h2>
            <a class="btn btn-outline btn-sm" href="/orders">مشاهده همه سفارشات</a>
        </div>
        <div class="card-body">
            <?php if (!$lastOrder): ?>
                <?= empty_state('هنوز سفارشی ثبت نکرده‌اید', 'می‌توانید با مراجعه به ویترین فروشگاه‌ها، اولین خرید خود را آغاز نمایید.', 'orders') ?>
            <?php else: ?>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">شماره سفارش</div>
                        <div class="detail-value tabular-nums"><strong><?= e($lastOrder['uuid']) ?></strong></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">فروشگاه صادرکننده</div>
                        <div class="detail-value"><?= e($lastOrder['shop_name'] ?: 'مرکزی') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">وضعیت سفارش</div>
                        <div class="detail-value"><?= order_badge($lastOrder['status']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">وضعیت پرداخت</div>
                        <div class="detail-value"><?= payment_badge($lastOrder['payment_status']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">مبلغ کل فاکتور</div>
                        <div class="detail-value tabular-nums">
                            <?= format_irr((float) ($lastOrder['final_total'] ?? $lastOrder['estimated_total'])) ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">تاریخ ثبت</div>
                        <div class="detail-value date-cell tabular-nums"><?= format_jalali($lastOrder['created_at']) ?></div>
                    </div>
                </div>

                <div class="mt-3 flex gap-2" style="justify-content:flex-end;">
                    <?php if (!empty($lastOrder['tracking_code'])): ?>
                        <a class="btn btn-outline btn-sm" href="/orders/track?uuid=<?= urlencode($lastOrder['uuid']) ?>">
                            <?= icon('search', 13) ?> رهگیری مرسوله پستی
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-primary btn-sm" href="/orders/<?= (int) $lastOrder['id'] ?>">
                        مشاهده جزئیات سفارش
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT ORDERS TABLE -->
    <div class="card">
        <div class="card-header">
            <h2>سفارشات اخیر</h2>
            <a class="btn btn-outline btn-sm" href="/orders">همه سفارشات</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره سفارش</th>
                        <th>فروشگاه</th>
                        <th>وضعیت سفارش</th>
                        <th>وضعیت پرداخت</th>
                        <th>مبلغ کل</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr>
                            <td colspan="7"><?= empty_state('سفارشی یافت نشد') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><code class="tabular-nums" style="font-size:0.88rem; font-weight:700;"><?= e($o['uuid']) ?></code></td>
                                <td><?= e($o['shop_name'] ?: '—') ?></td>
                                <td><?= order_badge($o['status']) ?></td>
                                <td><?= payment_badge($o['payment_status']) ?></td>
                                <td><strong class="tabular-nums"><?= format_irr((float) ($o['final_total'] ?? $o['estimated_total'])) ?></strong></td>
                                <td class="date-cell tabular-nums"><?= format_jalali($o['created_at']) ?></td>
                                <td>
                                    <a class="btn btn-outline btn-sm" href="/orders/<?= (int) $o['id'] ?>">مشاهده</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    layout_customer_end();
});
