<?php
declare(strict_types=1);

/**
 * routes_subscription_plans.php
 * Subscription Plans Management for SuperAdmins and Plan Overview for Business Owners
 */

// Helper: Get active subscription plan for a shop
function get_shop_subscription_info(int $shopId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT s.subscription_expires_at, sp.*
            FROM shops s
            LEFT JOIN subscription_plans sp ON sp.id = s.subscription_plan_id
            WHERE s.id = ?
        ");
        $stmt->execute([$shopId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sub && !empty($sub['code'])) {
            return $sub;
        }
        // Fallback to Free plan
        $def = $pdo->query("SELECT * FROM subscription_plans WHERE code = 'free'")->fetch(PDO::FETCH_ASSOC);
        return $def ?: [
            'name' => 'رایگان', 'code' => 'free', 'price_monthly' => 0, 'price_yearly' => 0,
            'max_branches' => 1, 'max_managers' => 1, 'max_products' => 30,
            'allow_custom_domain' => 0, 'allow_sms_alerts' => 0, 'subscription_expires_at' => null
        ];
    } catch (Throwable $e) {
        return ['name' => 'رایگان', 'code' => 'free', 'max_branches' => 1, 'max_managers' => 1, 'max_products' => 30];
    }
}

// 1. SuperAdmin Plans Management View: /app/plans
route('GET', '/app/plans(?:\.php)?', ['superadmin', 'admin'], function () use ($pdo) {
    $user = require_roles(['superadmin', 'admin']);

    $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $shops = $pdo->query("SELECT s.id, s.name, s.slug, s.subscription_expires_at, sp.name AS plan_name, sp.code AS plan_code FROM shops s LEFT JOIN subscription_plans sp ON sp.id = s.subscription_plan_id WHERE s.active = 1 ORDER BY s.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت پلن‌های اشتراک و سطوح دسترسی', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('shield', 18) ?></div>
            <div>
                <h1>مدیریت پلن‌های اشتراک (Free, Plus, Pro, Ultimate)</h1>
                <div class="page-sub">تعیین محدودیت شعب، مدیران، محصولات و تخصیص پلن به کسب‌وکارها</div>
            </div>
        </div>
    </div>

    <!-- PLANS GRID -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-bottom:36px;">
        <?php foreach ($plans as $p): ?>
            <div class="card" style="padding:22px; border-radius:14px; position:relative; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h2 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0;"><?= e($p['name']) ?></h2>
                        <span class="badge <?= $p['code'] === 'ultimate' ? 'badge-purple' : ($p['code'] === 'pro' ? 'badge-blue' : 'badge-gray') ?>">
                            <code><?= e($p['code']) ?></code>
                        </span>
                    </div>
                    <?php if (isset($p['active']) && !$p['active']): ?>
                        <div style="margin-bottom:8px;"><span class="badge badge-rose" style="font-size:0.7rem;">غیرفعال برای فروش</span></div>
                    <?php endif; ?>

                    <div style="font-size:1.3rem; font-weight:900; color:#2563eb; margin-bottom:12px;">
                        <?= (float)$p['price_monthly'] > 0 ? format_irt((float)$p['price_monthly']) . ' <small style="font-size:0.75rem; color:#64748b;">ماهانه</small>' : 'رایگان' ?>
                    </div>

                    <ul style="list-style:none; padding:0; margin:0 0 16px 0; font-size:0.86rem; color:#475569; line-height:2.2;">
                        <li>حداکثر شعب: <strong><?= (int)$p['max_branches'] === 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_branches']) ?></strong></li>
                        <li>حداکثر مدیران: <strong><?= (int)$p['max_managers'] === 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_managers']) ?></strong></li>
                        <li>حداکثر کالاها: <strong><?= (int)$p['max_products'] === 9999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_products']) ?></strong></li>
                        <li>دامنه اختصاصی: <?= $p['allow_custom_domain'] ? '✓ فعال' : '✕ غیرفعال' ?></li>
                        <li>پیامک اطلاع‌رسانی: <?= $p['allow_sms_alerts'] ? '✓ فعال' : '✕ غیرفعال' ?></li>
                    </ul>
                </div>

                <?php if ($user['role'] === 'superadmin'): ?>
                    <details style="margin-top:10px; border-top:1px solid #f1f5f9; padding-top:10px;">
                        <summary style="cursor:pointer; font-size:0.82rem; color:#2563eb; font-weight:bold;">ویرایش تنظیمات این پلن</summary>
                        <form method="post" action="/app/plans/update" style="margin-top:12px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label style="font-size:0.75rem;">نام نمایشی:</label>
                                <input type="text" name="name" class="input" style="padding:4px 8px; font-size:0.82rem;" value="<?= e($p['name']) ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label style="font-size:0.75rem;">قیمت ماهانه (تومان):</label>
                                <input type="text" name="price_monthly" class="input price-input" style="padding:4px 8px; font-size:0.82rem;" value="<?= e((string)(int)$p['price_monthly']) ?>" required>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                                <div>
                                    <label style="font-size:0.75rem;">حداکثر شعب:</label>
                                    <input type="number" name="max_branches" class="input" style="padding:4px 8px; font-size:0.82rem;" value="<?= (int)$p['max_branches'] ?>" required>
                                </div>
                                <div>
                                    <label style="font-size:0.75rem;">حداکثر مدیران:</label>
                                    <input type="number" name="max_managers" class="input" style="padding:4px 8px; font-size:0.82rem;" value="<?= (int)$p['max_managers'] ?>" required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:0.75rem;">حداکثر کالاها:</label>
                                <input type="number" name="max_products" class="input" style="padding:4px 8px; font-size:0.82rem;" value="<?= (int)$p['max_products'] ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:0.75rem; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="active" value="1" <?= (!isset($p['active']) || $p['active']) ? 'checked' : '' ?>>
                                    فعال و قابل فروش
                                </label>
                            </div>
                            <button class="btn btn-primary btn-sm" style="width:100%;">ذخیره تغییرات پلن</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- BUSINESSES SUBSCRIPTION ASSIGNMENT TABLE -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="font-size:1.1rem; margin:0;">پلن فعال کسب‌وکارهای ثبت‌شده</h2>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کسب‌وکار</th>
                        <th>پلن جاری</th>
                        <th>تاریخ انقضا</th>
                        <?php if ($user['role'] === 'superadmin'): ?>
                            <th>تغییر / تمدید پلن</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $sh): ?>
                        <tr>
                            <td><?= (int)$sh['id'] ?></td>
                            <td><strong><?= e($sh['name']) ?></strong> <span style="font-size:0.78rem; color:#64748b;">(<?= e($sh['slug']) ?>)</span></td>
                            <td><span class="badge badge-blue"><?= e($sh['plan_name'] ?: 'رایگان') ?></span></td>
                            <td><?= !empty($sh['subscription_expires_at']) ? en_to_fa_digits(substr($sh['subscription_expires_at'], 0, 10)) : 'دائمی / بدون انقضا' ?></td>
                            <?php if ($user['role'] === 'superadmin'): ?>
                                <td>
                                    <form method="post" action="/app/plans/assign" style="display:inline-flex; gap:6px; align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="shop_id" value="<?= (int)$sh['id'] ?>">
                                        <select name="plan_id" class="select" style="padding:4px 8px; font-size:0.8rem;">
                                            <?php foreach ($plans as $pl): ?>
                                                <?php if (isset($pl['active']) && !$pl['active'] && $sh['plan_name'] !== $pl['name']) continue; ?>
                                                <option value="<?= (int)$pl['id'] ?>" <?= ($sh['plan_name'] === $pl['name']) ? 'selected' : '' ?>>
                                                    <?= e($pl['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline btn-sm">اعمال</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    layout_end();
});

// 2. Update Plan Action
route('POST', '/app/plans/update', ['superadmin'], function () use ($pdo) {
    require_roles(['superadmin']);
    verify_csrf_or_die();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $priceMonthly = clean_price_input($_POST['price_monthly'] ?? '0');
    $maxBranches = max(1, (int)($_POST['max_branches'] ?? 1));
    $maxManagers = max(1, (int)($_POST['max_managers'] ?? 1));
    $maxProducts = max(1, (int)($_POST['max_products'] ?? 10));
    $active = !empty($_POST['active']) ? 1 : 0;

    if ($planId > 0 && $name !== '') {
        $stmt = $pdo->prepare("
            UPDATE subscription_plans
            SET name = ?, price_monthly = ?, max_branches = ?, max_managers = ?, max_products = ?, active = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $priceMonthly, $maxBranches, $maxManagers, $maxProducts, $active, $planId]);
        flash('success', 'مشخصات پلن با موفقیت به‌روزرسانی شد.');
    }
    safe_redirect_back('/app/plans');
});

// 3. Assign Plan to Shop Action
route('POST', '/app/plans/assign', ['superadmin'], function () use ($pdo) {
    require_roles(['superadmin']);
    verify_csrf_or_die();

    $shopId = (int)($_POST['shop_id'] ?? 0);
    $planId = (int)($_POST['plan_id'] ?? 0);

    if ($shopId > 0 && $planId > 0) {
        $stmt = $pdo->prepare("UPDATE shops SET subscription_plan_id = ? WHERE id = ?");
        $stmt->execute([$planId, $shopId]);
        flash('success', 'پلن کسب‌وکار با موفقیت تغییر یافت.');
    }
    safe_redirect_back('/app/plans');
});

// 4. Business Owner Plan Overview: /app/plans/my-plan
route('GET', '/app/plans/my-plan(?:\.php)?', ['business_owner', 'shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $sub = get_shop_subscription_info($shopId);
    $branchCount = (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE business_id = {$shopId} AND active = 1")->fetchColumn();
    $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE shop_id = {$shopId} AND active = 1 AND deleted_at IS NULL")->fetchColumn();

    layout_start('پلن اشتراک و سهمیه‌های کسب‌وکار', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('shield', 18) ?></div>
            <div>
                <h1>پلن اشتراک کسب‌وکار: <?= e($shop['name']) ?></h1>
                <div class="page-sub">مشاهده محدودیت‌ها، سهمیه‌های استفاده‌شده و ارتقای حساب</div>
            </div>
        </div>
    </div>

    <div class="card" style="padding:28px; border-radius:16px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <span style="font-size:0.85rem; color:#64748b;">سطح اشتراک فعال:</span>
                <h2 style="font-size:1.6rem; font-weight:900; color:#2563eb; margin:4px 0 0 0;"><?= e($sub['name']) ?></h2>
            </div>
            <?php if ($sub['code'] === 'ultimate'): ?>
                <span class="badge badge-purple" style="font-size:0.9rem; padding:8px 14px;">👑 پلن نامحدود سازمانی (Ultimate)</span>
            <?php else: ?>
                <a href="/tickets/create?subject=درخواست ارتقای پلن اشتراک" class="btn btn-primary">
                    <?= icon('plus', 14) ?> ارتقای پلن اشتراک
                </a>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px;">
            <div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="font-size:0.82rem; color:#64748b; margin-bottom:6px;">شعب ایجاد شده:</div>
                <div style="font-size:1.3rem; font-weight:900; color:#0f172a;">
                    <?= en_to_fa_digits((string)$branchCount) ?> / <?= (int)$sub['max_branches'] === 999 ? 'نامحدود' : en_to_fa_digits((string)$sub['max_branches']) ?>
                </div>
            </div>
            <div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="font-size:0.82rem; color:#64748b; margin-bottom:6px;">کالاهای ثبت شده:</div>
                <div style="font-size:1.3rem; font-weight:900; color:#0f172a;">
                    <?= en_to_fa_digits((string)$prodCount) ?> / <?= (int)$sub['max_products'] === 9999 ? 'نامحدود' : en_to_fa_digits((string)$sub['max_products']) ?>
                </div>
            </div>
        </div>

        <?php if ($sub['code'] === 'ultimate'): ?>
            <div style="margin-top:20px; padding:14px 18px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; color:#6b21a8; font-size:0.88rem; line-height:1.8;">
                ✨ با توجه به فعال بودن پلن Ultimate، تیکت‌های پشتیبانی شما با اولویت آنی بررسی شده و امکان ارجاع مستقیم به سوپرادمین سامانه وجود دارد.
            </div>
        <?php endif; ?>
    </div>
    <?php
    layout_end();
});

// 5. Ticket Escalation to SuperAdmin Action
route('POST', '/app/tickets/escalate', ['business_owner', 'shop_owner', 'superadmin', 'admin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin', 'admin']);
    verify_csrf_or_die();

    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if ($ticketId > 0) {
        $stmt = $pdo->prepare("UPDATE tickets SET is_escalated_to_superadmin = 1, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$ticketId]);
        flash('success', 'تیکت با اولویت بالا به مدیریت ارشد سامانه (SuperAdmin) ارجاع داده شد.');
    }
    safe_redirect_back('/tickets');
});
