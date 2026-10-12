<?php
declare(strict_types=1);

/**
 * routes_subscription_plans.php
 * SuperAdmin Platform Panel (/admin/plans & /app/plans)
 * - 4-Tier Subscription Matrix (Free, Plus, Pro, Ultimate)
 * - Business Quota Usage Meters (Branches, Managers, Products)
 * - High-Priority Ticket Escalation Queue (Ultimate VIP priority handling)
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
            'name' => 'طرح پایه رایگان', 'code' => 'free', 'price_monthly' => 0, 'price_yearly' => 0,
            'max_branches' => 1, 'max_managers' => 1, 'max_products' => 50,
            'allow_custom_domain' => 0, 'allow_sms_alerts' => 0, 'subscription_expires_at' => null
        ];
    } catch (Throwable $e) {
        return ['name' => 'طرح پایه رایگان', 'code' => 'free', 'max_branches' => 1, 'max_managers' => 1, 'max_products' => 50];
    }
}

// 1. SuperAdmin Platform Panel View: /admin/plans & /app/plans
route('GET', '/(?:admin|app)/plans(?:\.php)?', ['superadmin', 'admin'], function () use ($pdo) {
    $user = require_roles(['superadmin', 'admin']);

    $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $shops = $pdo->query("
        SELECT s.id, s.name, s.slug, s.subscription_expires_at, sp.name AS plan_name, sp.code AS plan_code,
               sp.max_branches, sp.max_managers, sp.max_products
        FROM shops s 
        LEFT JOIN subscription_plans sp ON sp.id = s.subscription_plan_id 
        WHERE s.active = 1 
        ORDER BY s.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Prepare quota metrics for each shop
    $shopsWithQuotas = [];
    foreach ($shops as $sh) {
        $sId = (int)$sh['id'];
        $bCount = (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE business_id = {$sId} AND active = 1")->fetchColumn();
        $pCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE shop_id = {$sId} AND active = 1 AND deleted_at IS NULL")->fetchColumn();
        $mCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE shop_id = {$sId} AND role IN ('branch_manager', 'manager', 'shop_manager')")->fetchColumn();

        $maxB = (int)($sh['max_branches'] ?? 1);
        $maxP = (int)($sh['max_products'] ?? 50);
        $maxM = (int)($sh['max_managers'] ?? 1);

        $sh['branch_count'] = $bCount;
        $sh['prod_count'] = $pCount;
        $sh['manager_count'] = $mCount;
        $sh['branch_pct'] = $maxB >= 999 ? 100 : min(100, (int)round(($bCount / max(1, $maxB)) * 100));
        $sh['prod_pct'] = $maxP >= 9999 ? 100 : min(100, (int)round(($pCount / max(1, $maxP)) * 100));
        $sh['manager_pct'] = $maxM >= 999 ? 100 : min(100, (int)round(($mCount / max(1, $maxM)) * 100));

        $shopsWithQuotas[] = $sh;
    }

    // High-Priority Ticket Escalation Queue
    $stmtEscalated = $pdo->query("
        SELECT t.*, s.name AS shop_name, s.slug AS shop_slug, u.nickname AS customer_name, u.username AS customer_username,
               sp.name AS plan_name, sp.code AS plan_code
        FROM tickets t
        LEFT JOIN shops s ON s.id = t.shop_id
        LEFT JOIN subscription_plans sp ON sp.id = s.subscription_plan_id
        LEFT JOIN users u ON u.id = t.customer_id
        WHERE t.is_escalated_to_superadmin = 1 OR t.is_elevated_to_superadmin = 1
        ORDER BY CASE WHEN t.status = 'open' THEN 0 ELSE 1 END, t.updated_at DESC
    ");
    $escalatedTickets = $stmtEscalated->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت پلن‌های اشتراک و سهمیه‌های سراسری', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('shield', 18) ?></div>
            <div>
                <h1>پنل پلتفرم و مدیریت پلن‌های اشتراک (Platform SuperAdmin Panel)</h1>
                <div class="page-sub">تعیین محدودیت‌های ۴ سطحی، پایش سهمیه کسب‌وکارها و صف ارجاع فوری تیکت‌ها</div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/dashboard"><?= icon('dashboard', 14) ?> بازگشت به داشبورد</a>
            <a class="btn btn-outline" href="/tickets"><?= icon('tickets', 14) ?> کلیه تیکت‌ها</a>
        </div>
    </div>

    <!-- 1. 4-TIER SUBSCRIPTION PLANS MATRIX -->
    <div style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="font-size:1.15rem; margin:0; font-weight:800; color:#0f172a;">ماتریس سطوح اشتراک پلتفرم (Free, Plus, Pro, Ultimate)</h2>
            <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">تعرفه ماهانه، سهمیه شعب و انبارها، و مجوز ارجاع VIP تیکت به سوپرادمین</div>
        </div>
        <span class="badge badge-emerald">۴ سطح پلن فعال</span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-bottom:36px;">
        <?php foreach ($plans as $p): 
            $isUltimate = ($p['code'] === 'ultimate');
            $isPro = ($p['code'] === 'pro');
            $isPlus = ($p['code'] === 'plus');
        ?>
            <div class="card" style="padding:24px; border-radius:16px; position:relative; display:flex; flex-direction:column; justify-content:space-between; <?= $isUltimate ? 'border: 2px solid #c084fc; box-shadow: 0 4px 20px rgba(168,85,247,0.12); background:#fff;' : '' ?>">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="font-size:1.2rem; font-weight:800; color:#0f172a; margin:0;"><?= e($p['name']) ?></h3>
                        <span class="badge <?= $isUltimate ? 'badge-purple' : ($isPro ? 'badge-blue' : ($isPlus ? 'badge-amber' : 'badge-gray')) ?>" style="font-weight:700;">
                            <code><?= e($p['code']) ?></code>
                        </span>
                    </div>

                    <?php if (isset($p['active']) && !$p['active']): ?>
                        <div style="margin-bottom:8px;"><span class="badge badge-rose" style="font-size:0.7rem;">غیرفعال برای فروش</span></div>
                    <?php endif; ?>

                    <div style="font-size:1.45rem; font-weight:900; color:<?= $isUltimate ? '#7c3aed' : '#2563eb' ?>; margin-bottom:16px; font-variant-numeric:tabular-nums;">
                        <?= (float)$p['price_monthly'] > 0 ? format_irr((float)$p['price_monthly']) . ' <small style="font-size:0.76rem; color:#64748b; font-weight:500;">ماهانه</small>' : 'رایگان' ?>
                    </div>

                    <ul style="list-style:none; padding:0; margin:0 0 20px 0; font-size:0.86rem; color:#475569; line-height:2.3;">
                        <li style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                            <span>حداکثر تعداد شعب:</span>
                            <strong style="color:#0f172a;"><?= (int)$p['max_branches'] >= 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_branches']) . ' شعبه' ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                            <span>حداکثر تعداد مدیران:</span>
                            <strong style="color:#0f172a;"><?= (int)$p['max_managers'] >= 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_managers']) . ' نفر' ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                            <span>سقف کاتالوگ محصولات:</span>
                            <strong style="color:#0f172a;"><?= (int)$p['max_products'] >= 9999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_products']) . ' کالا' ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                            <span>دامنه اختصاصی:</span>
                            <span style="color:<?= $p['allow_custom_domain'] ? '#059669' : '#94a3b8' ?>; font-weight:600;">
                                <?= $p['allow_custom_domain'] ? 'فعال' : 'غیرفعال' ?>
                            </span>
                        </li>
                        <li style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                            <span>پیامک خودکار به خریدار:</span>
                            <span style="color:<?= $p['allow_sms_alerts'] ? '#059669' : '#94a3b8' ?>; font-weight:600;">
                                <?= $p['allow_sms_alerts'] ? 'فعال' : 'غیرفعال' ?>
                            </span>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding-top:2px;">
                            <span>ارجاع تیکت به سوپرادمین:</span>
                            <span style="color:<?= !empty($p['has_ticket_escalation']) ? '#7c3aed' : '#94a3b8' ?>; font-weight:700;">
                                <?= !empty($p['has_ticket_escalation']) ? 'VIP آنی' : 'غیرفعال' ?>
                            </span>
                        </li>
                    </ul>
                </div>

                <?php if ($user['role'] === 'superadmin'): ?>
                    <details style="margin-top:12px; border-top:1px solid #f1f5f9; padding-top:12px;">
                        <summary style="cursor:pointer; font-size:0.82rem; color:var(--primary); font-weight:bold;">ویرایش تنظیمات این پلن</summary>
                        <form method="post" action="/admin/plans/update" style="margin-top:12px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label style="font-size:0.75rem;">نام نمایشی:</label>
                                <input type="text" name="name" class="input" style="padding:5px 8px; font-size:0.82rem;" value="<?= e($p['name']) ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label style="font-size:0.75rem;">قیمت ماهانه (تومان):</label>
                                <input type="text" name="price_monthly" class="input price-input" style="padding:5px 8px; font-size:0.82rem;" value="<?= e((string)(int)$p['price_monthly']) ?>" required>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                                <div>
                                    <label style="font-size:0.75rem;">حداکثر شعب:</label>
                                    <input type="number" name="max_branches" class="input" style="padding:5px 8px; font-size:0.82rem;" value="<?= (int)$p['max_branches'] ?>" required>
                                </div>
                                <div>
                                    <label style="font-size:0.75rem;">حداکثر مدیران:</label>
                                    <input type="number" name="max_managers" class="input" style="padding:5px 8px; font-size:0.82rem;" value="<?= (int)$p['max_managers'] ?>" required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:0.75rem;">حداکثر کالاها:</label>
                                <input type="number" name="max_products" class="input" style="padding:5px 8px; font-size:0.82rem;" value="<?= (int)$p['max_products'] ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label style="font-size:0.75rem; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="active" value="1" <?= (!isset($p['active']) || $p['active']) ? 'checked' : '' ?>>
                                    فعال و قابل فروش در سامانه
                                </label>
                            </div>
                            <button class="btn btn-primary btn-sm" style="width:100%;">ذخیره تغییرات پلن</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 2. HIGH-PRIORITY TICKET ESCALATION QUEUE -->
    <div class="card mb-3" style="border: 1px solid #e9d5ff; box-shadow: 0 4px 16px rgba(168,85,247,0.06);">
        <div class="card-header" style="background:#faf5ff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f3e8ff;">
            <div>
                <h2 style="font-size:1.15rem; margin:0; color:#6b21a8; display:flex; align-items:center; gap:8px;">
                    صف ارجاع تیکت‌های با اولویت آنی (High-Priority Escalation Queue)
                </h2>
                <div style="font-size:0.8rem; color:#7e22ce; margin-top:2px;">
                    تیکت‌های ارجاع‌داده‌شده مستقیم از سوی مشتریان سازمانی پلن Ultimate و موارد نیازمند اقدام فوری سوپرادمین
                </div>
            </div>
            <span class="badge badge-purple" style="font-weight:700; font-size:0.82rem;">
                <?= en_to_fa_digits((string)count($escalatedTickets)) ?> تیکت ارجاع‌شده
            </span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:80px;">شماره</th>
                        <th>اولویت و پلن</th>
                        <th>کسب‌وکار</th>
                        <th>مشتری / کاربر</th>
                        <th>موضوع تیکت</th>
                        <th>وضعیت</th>
                        <th>آخرین بروزرسانی</th>
                        <th>اقدام</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$escalatedTickets): ?>
                        <tr><td colspan="8"><?= empty_state('هیچ تیکت ارجاع‌شده‌ای در انتظار رسیدگی سوپرادمین وجود ندارد') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($escalatedTickets as $t): ?>
                            <tr style="<?= $t['status'] === 'open' ? 'background:#fffbfb;' : '' ?>">
                                <td>
                                    <strong style="font-family:'JetBrains Mono', monospace; color:#6b21a8;">
                                        #T-<?= en_to_fa_digits((string)$t['id']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-purple" style="font-size:0.75rem; font-weight:700;">
                                        VIP Escalated
                                    </span>
                                </td>
                                <td>
                                    <strong><?= e($t['shop_name'] ?? 'مرکزی') ?></strong>
                                    <div style="font-size:0.75rem; color:#64748b;">(<?= e($t['shop_slug'] ?? 'central') ?>)</div>
                                </td>
                                <td>
                                    <?= e($t['customer_name'] ?: $t['customer_username'] ?: '—') ?>
                                </td>
                                <td>
                                    <strong><?= e($t['subject']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($t['status'] === 'open'): ?>
                                        <span class="badge badge-amber">در انتظار رسیدگی</span>
                                    <?php else: ?>
                                        <span class="badge badge-emerald">بسته شده</span>
                                    <?php endif; ?>
                                </td>
                                <td class="date-cell" style="font-size:0.82rem;"><?= format_jalali($t['updated_at']) ?></td>
                                <td>
                                    <a class="btn btn-primary btn-sm" href="/tickets/<?= (int)$t['id'] ?>" style="background:#7c3aed; border-color:#7c3aed;">
                                        <?= icon('send', 12) ?> پاسخ فوری
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. BUSINESS QUOTA USAGE METERS & PLAN ASSIGNMENT -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="font-size:1.15rem; margin:0;">پایش سهمیه‌های مصرفی کسب‌وکارها (Quota Usage Meters)</h2>
                <div style="font-size:0.8rem; color:var(--muted); margin-top:2px;">بررسی زنده میزان مصرف شعب، کاتالوگ محصولات و حساب‌های مدیریتی به همراه تخصیص پلن</div>
            </div>
            <span class="badge badge-gray"><?= en_to_fa_digits((string)count($shopsWithQuotas)) ?> کسب‌وکار</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px;">شناسه</th>
                        <th>نام کسب‌وکار</th>
                        <th>سطح پلن</th>
                        <th style="min-width:180px;">سهمیه شعب (Branches)</th>
                        <th style="min-width:180px;">سهمیه مدیران (Managers)</th>
                        <th style="min-width:180px;">سهمیه کالاها (Products)</th>
                        <th>انقضا</th>
                        <?php if ($user['role'] === 'superadmin'): ?>
                            <th>تغییر / تخصیص پلن</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shopsWithQuotas as $sh): 
                        $bUnlimited = (int)($sh['max_branches'] ?? 1) >= 999;
                        $mUnlimited = (int)($sh['max_managers'] ?? 1) >= 999;
                        $pUnlimited = (int)($sh['max_products'] ?? 50) >= 9999;
                    ?>
                        <tr>
                            <td><?= (int)$sh['id'] ?></td>
                            <td>
                                <strong><?= e($sh['name']) ?></strong>
                                <div style="font-size:0.78rem; color:#64748b; font-family:'JetBrains Mono', monospace;">(<?= e($sh['slug']) ?>)</div>
                            </td>
                            <td>
                                <span class="badge <?= $sh['plan_code'] === 'ultimate' ? 'badge-purple' : ($sh['plan_code'] === 'pro' ? 'badge-blue' : ($sh['plan_code'] === 'plus' ? 'badge-amber' : 'badge-gray')) ?>" style="font-weight:700;">
                                    <?= e($sh['plan_name'] ?: 'طرح پایه رایگان') ?>
                                </span>
                            </td>

                            <!-- Branches Meter -->
                            <td>
                                <div class="quota-meter-wrap">
                                    <div class="quota-meter-label">
                                        <span><?= en_to_fa_digits((string)$sh['branch_count']) ?> از <?= $bUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['max_branches']) ?></span>
                                        <span><?= $bUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['branch_pct']) . '٪' ?></span>
                                    </div>
                                    <div class="quota-meter-bar">
                                        <div class="quota-meter-fill" style="width:<?= $bUnlimited ? 100 : $sh['branch_pct'] ?>%; background:<?= $bUnlimited ? '#7c3aed' : ($sh['branch_pct'] > 85 ? '#dc2626' : ($sh['branch_pct'] > 60 ? '#f59e0b' : '#2563eb')) ?>;"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Managers Meter -->
                            <td>
                                <div class="quota-meter-wrap">
                                    <div class="quota-meter-label">
                                        <span><?= en_to_fa_digits((string)$sh['manager_count']) ?> از <?= $mUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['max_managers']) ?></span>
                                        <span><?= $mUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['manager_pct']) . '٪' ?></span>
                                    </div>
                                    <div class="quota-meter-bar">
                                        <div class="quota-meter-fill" style="width:<?= $mUnlimited ? 100 : $sh['manager_pct'] ?>%; background:<?= $mUnlimited ? '#7c3aed' : ($sh['manager_pct'] > 85 ? '#dc2626' : ($sh['manager_pct'] > 60 ? '#f59e0b' : '#059669')) ?>;"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Products Meter -->
                            <td>
                                <div class="quota-meter-wrap">
                                    <div class="quota-meter-label">
                                        <span><?= en_to_fa_digits((string)$sh['prod_count']) ?> از <?= $pUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['max_products']) ?></span>
                                        <span><?= $pUnlimited ? 'نامحدود' : en_to_fa_digits((string)$sh['prod_pct']) . '٪' ?></span>
                                    </div>
                                    <div class="quota-meter-bar">
                                        <div class="quota-meter-fill" style="width:<?= $pUnlimited ? 100 : $sh['prod_pct'] ?>%; background:<?= $pUnlimited ? '#7c3aed' : ($sh['prod_pct'] > 85 ? '#dc2626' : ($sh['prod_pct'] > 60 ? '#f59e0b' : '#2563eb')) ?>;"></div>
                                    </div>
                                </div>
                            </td>

                            <td style="font-size:0.82rem; color:#475569;">
                                <?= !empty($sh['subscription_expires_at']) ? en_to_fa_digits(substr($sh['subscription_expires_at'], 0, 10)) : 'دائمی / بدون انقضا' ?>
                            </td>

                            <?php if ($user['role'] === 'superadmin'): ?>
                                <td>
                                    <form method="post" action="/admin/plans/assign" style="display:inline-flex; gap:6px; align-items:center;">
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
route('POST', '/(?:admin|app)/plans/update', ['superadmin'], function () use ($pdo) {
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
    safe_redirect_back('/admin/plans');
});

// 3. Assign Plan to Shop Action
route('POST', '/(?:admin|app)/plans/assign', ['superadmin'], function () use ($pdo) {
    require_roles(['superadmin']);
    verify_csrf_or_die();

    $shopId = (int)($_POST['shop_id'] ?? 0);
    $planId = (int)($_POST['plan_id'] ?? 0);

    if ($shopId > 0 && $planId > 0) {
        $stmt = $pdo->prepare("UPDATE shops SET subscription_plan_id = ? WHERE id = ?");
        $stmt->execute([$planId, $shopId]);
        flash('success', 'پلن کسب‌وکار با موفقیت تغییر یافت.');
    }
    safe_redirect_back('/admin/plans');
});

// 4. Business Owner Plan Overview: /app/plans/my-plan & /admin/plans/my-plan
route('GET', '/(?:admin|app)/plans/my-plan(?:\.php)?', ['business_owner', 'shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $sub = get_shop_subscription_info($shopId);
    $branchCount = (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE business_id = {$shopId} AND active = 1")->fetchColumn();
    $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE shop_id = {$shopId} AND active = 1 AND deleted_at IS NULL")->fetchColumn();
    $managerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE shop_id = {$shopId} AND role IN ('branch_manager', 'manager', 'shop_manager')")->fetchColumn();

    $maxB = (int)($sub['max_branches'] ?? 1);
    $maxP = (int)($sub['max_products'] ?? 50);
    $maxM = (int)($sub['max_managers'] ?? 1);

    $bUnlimited = ($maxB >= 999);
    $pUnlimited = ($maxP >= 9999);
    $mUnlimited = ($maxM >= 999);

    $branchPct = $bUnlimited ? 0 : min(100, (int)round(($branchCount / max(1, $maxB)) * 100));
    $prodPct = $pUnlimited ? 0 : min(100, (int)round(($prodCount / max(1, $maxP)) * 100));
    $managerPct = $mUnlimited ? 0 : min(100, (int)round(($managerCount / max(1, $maxM)) * 100));

    layout_start('پلن اشتراک و سهمیه‌های کسب‌وکار', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('shield', 18) ?></div>
            <div>
                <h1>پلن اشتراک کسب‌وکار: <?= e($shop['name']) ?></h1>
                <div class="page-sub">مشاهده محدودیت‌ها، سهمیه‌های استفاده‌شده و ارتقای حساب کاربری</div>
            </div>
        </div>
        <div class="action-cluster">
            <?php if ($user['role'] === 'superadmin'): ?>
                <a class="btn btn-outline" href="/admin/plans"><?= icon('shield', 14) ?> مدیریت پلن‌های پلتفرم</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="/dashboard"><?= icon('dashboard', 14) ?> بازگشت به داشبورد</a>
        </div>
    </div>

    <!-- Active Subscription Overview -->
    <div class="card" style="padding:28px; border-radius:16px; margin-bottom:24px; background:#ffffff; border:1px solid #e5e5ea;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
            <div>
                <span style="font-size:0.85rem; color:#86868b; font-weight:500;">سطح اشتراک فعال کسب‌وکار:</span>
                <div style="display:flex; align-items:center; gap:12px; margin-top:4px;">
                    <h2 style="font-size:1.6rem; font-weight:900; color:#1d1d1f; margin:0;"><?= e($sub['name']) ?></h2>
                    <span class="badge <?= $sub['code'] === 'ultimate' ? 'badge-purple' : ($sub['code'] === 'pro' ? 'badge-blue' : ($sub['code'] === 'plus' ? 'badge-amber' : 'badge-gray')) ?>" style="font-weight:700;">
                        <code><?= e($sub['code']) ?></code>
                    </span>
                </div>
            </div>
            <div>
                <?php if ($sub['code'] === 'ultimate'): ?>
                    <span class="badge badge-purple" style="font-size:0.9rem; padding:8px 16px;">پلن نامحدود سازمانی (VIP آنی)</span>
                <?php else: ?>
                    <a href="/tickets/create?subject=درخواست ارتقای پلن اشتراک" class="btn btn-primary" style="padding:10px 20px; font-weight:700;">
                        ارتقای پلن اشتراک
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quota Progress Meters Grid -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px;">
            <!-- 1. Branches Quota -->
            <div style="background:#f5f5f7; padding:20px; border-radius:12px; border:1px solid #e5e5ea;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-size:0.85rem; font-weight:700; color:#1d1d1f;">سهمیه شعب و انبارها</span>
                    <span style="font-size:0.8rem; color:#86868b;" class="tabular-nums">
                        <?= en_to_fa_digits((string)$branchCount) ?> / <?= $bUnlimited ? 'نامحدود' : en_to_fa_digits((string)$maxB) ?>
                    </span>
                </div>
                <div class="quota-meter-bar">
                    <div class="quota-meter-fill" style="width:<?= $bUnlimited ? 100 : $branchPct ?>%; background:<?= $bUnlimited ? '#5856d6' : ($branchPct > 85 ? '#ff3b30' : ($branchPct > 60 ? '#ff9500' : '#0071e3')) ?>;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.78rem; color:#86868b;" class="tabular-nums">
                    <span><?= $bUnlimited ? 'بدون محدودیت' : 'درصد مصرف: ' . en_to_fa_digits((string)$branchPct) . '٪' ?></span>
                    <span><?= $bUnlimited ? 'نامحدود' : 'باقیمانده: ' . en_to_fa_digits((string)max(0, $maxB - $branchCount)) . ' شعبه' ?></span>
                </div>
            </div>

            <!-- 2. Products Quota -->
            <div style="background:#f5f5f7; padding:20px; border-radius:12px; border:1px solid #e5e5ea;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-size:0.85rem; font-weight:700; color:#1d1d1f;">سهمیه کاتالوگ محصولات</span>
                    <span style="font-size:0.8rem; color:#86868b;" class="tabular-nums">
                        <?= en_to_fa_digits((string)$prodCount) ?> / <?= $pUnlimited ? 'نامحدود' : en_to_fa_digits((string)$maxP) ?>
                    </span>
                </div>
                <div class="quota-meter-bar">
                    <div class="quota-meter-fill" style="width:<?= $pUnlimited ? 100 : $prodPct ?>%; background:<?= $pUnlimited ? '#5856d6' : ($prodPct > 85 ? '#ff3b30' : ($prodPct > 60 ? '#ff9500' : '#0071e3')) ?>;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.78rem; color:#86868b;" class="tabular-nums">
                    <span><?= $pUnlimited ? 'بدون محدودیت' : 'درصد مصرف: ' . en_to_fa_digits((string)$prodPct) . '٪' ?></span>
                    <span><?= $pUnlimited ? 'نامحدود' : 'باقیمانده: ' . en_to_fa_digits((string)max(0, $maxP - $prodCount)) . ' کالا' ?></span>
                </div>
            </div>

            <!-- 3. Managers Quota -->
            <div style="background:#f5f5f7; padding:20px; border-radius:12px; border:1px solid #e5e5ea;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-size:0.85rem; font-weight:700; color:#1d1d1f;">سهمیه حساب‌های مدیران</span>
                    <span style="font-size:0.8rem; color:#86868b;" class="tabular-nums">
                        <?= en_to_fa_digits((string)$managerCount) ?> / <?= $mUnlimited ? 'نامحدود' : en_to_fa_digits((string)$maxM) ?>
                    </span>
                </div>
                <div class="quota-meter-bar">
                    <div class="quota-meter-fill" style="width:<?= $mUnlimited ? 100 : $managerPct ?>%; background:<?= $mUnlimited ? '#5856d6' : ($managerPct > 85 ? '#ff3b30' : ($managerPct > 60 ? '#ff9500' : '#0071e3')) ?>;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.78rem; color:#86868b;" class="tabular-nums">
                    <span><?= $mUnlimited ? 'بدون محدودیت' : 'درصد مصرف: ' . en_to_fa_digits((string)$managerPct) . '٪' ?></span>
                    <span><?= $mUnlimited ? 'نامحدود' : 'باقیمانده: ' . en_to_fa_digits((string)max(0, $maxM - $managerCount)) . ' کاربر' ?></span>
                </div>
            </div>
        </div>

        <!-- Plan Features List -->
        <div style="margin-top:24px; padding-top:20px; border-top:1px solid #e5e5ea; display:flex; flex-wrap:wrap; gap:24px; font-size:0.85rem; color:#48484a;">
            <div>
                دامنه اختصاصی: <strong style="color:<?= $sub['allow_custom_domain'] ? '#34c759' : '#86868b' ?>;"><?= $sub['allow_custom_domain'] ? 'فعال' : 'غیرفعال' ?></strong>
            </div>
            <div>
                پیامک خودکار به خریدار: <strong style="color:<?= $sub['allow_sms_alerts'] ? '#34c759' : '#86868b' ?>;"><?= $sub['allow_sms_alerts'] ? 'فعال' : 'غیرفعال' ?></strong>
            </div>
            <div>
                ارجاع تیکت به سوپرادمین: <strong style="color:<?= !empty($sub['has_ticket_escalation']) ? '#5856d6' : '#86868b' ?>;"><?= !empty($sub['has_ticket_escalation']) ? 'VIP آنی فعال' : 'غیرفعال' ?></strong>
            </div>
        </div>

        <?php if ($sub['code'] === 'ultimate'): ?>
            <div style="margin-top:20px; padding:16px 20px; background:#f0effc; border:1px solid rgba(88,86,214,0.15); border-radius:12px; color:#5856d6; font-size:0.88rem; line-height:1.8;">
                <strong>اولویت ویژه سازمانی:</strong> با توجه به فعال بودن پلن Ultimate، تیکت‌های پشتیبانی کسب‌وکار شما با اولویت آنی بررسی شده و امکان ارجاع مستقیم به سوپرادمین سامانه برای حل فوری مشکلات در دسترس است.
            </div>
        <?php endif; ?>
    </div>
    <?php
    layout_end();
});

// 5. Ticket Escalation to SuperAdmin Action
route('POST', '/(?:admin|app)/tickets/escalate', ['business_owner', 'shop_owner', 'superadmin', 'admin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin', 'admin']);
    verify_csrf_or_die();

    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if ($ticketId > 0) {
        $stmtT = $pdo->prepare("
            SELECT t.shop_id, sp.has_ticket_escalation, sp.code 
            FROM tickets t 
            LEFT JOIN shops s ON s.id = t.shop_id 
            LEFT JOIN subscription_plans sp ON sp.id = s.subscription_plan_id 
            WHERE t.id = ?
        ");
        $stmtT->execute([$ticketId]);
        $tData = $stmtT->fetch(PDO::FETCH_ASSOC);

        if ($user['role'] !== 'superadmin' && empty($tData['has_ticket_escalation'])) {
            flash('error', 'ارجاع تیکت به سوپرادمین منحصراً در پلن سازمانی (Ultimate) مجاز می‌باشد.');
            safe_redirect_back('/tickets');
            return;
        }

        $stmt = $pdo->prepare("UPDATE tickets SET is_escalated_to_superadmin = 1, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$ticketId]);
        flash('success', 'تیکت با اولویت بالا به مدیریت ارشد سامانه (SuperAdmin) ارجاع داده شد.');
    }
    safe_redirect_back('/tickets');
});
