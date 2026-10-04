<?php
declare(strict_types=1);

/**
 * routes_branches.php
 * Multi-Business Switching, Branch Listing, Shipping Groups & Manager Assignments
 */

// 1. Business Switching Action
route('POST', '/app/switch-business', ['business_owner', 'shop_owner', 'superadmin', 'admin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin', 'admin']);
    verify_csrf_or_die();

    $bizId = (int)($_POST['business_id'] ?? 0);
    if ($bizId > 0) {
        if ($user['role'] === 'superadmin' || $user['role'] === 'admin') {
            set_active_business($bizId);
        } else {
            $chk = $pdo->prepare("SELECT id FROM shops WHERE id = ? AND owner_id = ? AND active = 1");
            $chk->execute([$bizId, $user['id']]);
            if ($chk->fetchColumn()) {
                set_active_business($bizId);
                flash('success', 'کسب‌وکار فعال تغییر یافت.');
            }
        }
    }
    safe_redirect_back('/app/dashboard');
});

// 2. Branch Switching Action (Whole Business vs Specific Branch)
route('POST', '/app/switch-branch', ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'superadmin', 'admin'], function () {
    $user = require_roles(['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager', 'superadmin', 'admin']);
    verify_csrf_or_die();

    $branchId = (int)($_POST['branch_id'] ?? 0);
    set_active_branch($branchId > 0 ? $branchId : null);
    flash('success', $branchId > 0 ? 'دیدگاه شعبه فعال شد.' : 'دیدگاه سراسری کسب‌وکار فعال شد.');
    safe_redirect_back('/app/dashboard');
});

// 3. Branches List
route('GET', '/app/branches(?:\.php)?', ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $branches = get_user_assigned_branches($user, $shopId);

    layout_start('مدیریت شعب کسب‌وکار', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('store', 18) ?></div>
            <div>
                <h1>مدیریت شعب</h1>
                <div class="page-sub">کسب‌وکار: <strong><?= e($shop['name']) ?></strong> | تعریف شعب مستقل، تنظیمات کاتالوگ و لجستیک ارسال</div>
            </div>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-outline" href="/app/shipping-groups"><?= icon('orders', 14) ?> گروه‌های ارسال متمرکز</a>
            <?php if (in_array($user['role'], ['business_owner', 'shop_owner', 'superadmin'], true)): ?>
                <a class="btn btn-primary" href="/app/branches/create"><?= icon('plus', 14) ?> تعریف شعبه جدید</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($branches)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('شعبه‌ای برای این کسب‌وکار تعریف نشده است', 'می‌توانید با تعریف شعب مجزا، مدیران و موجودی انبار را به صورت مستقل تفکیک نمایید.', 'store') ?>
                <?php if (in_array($user['role'], ['business_owner', 'shop_owner', 'superadmin'], true)): ?>
                    <div style="text-align:center; margin-top:16px;">
                        <a class="btn btn-primary" href="/app/branches/create">تعریف اولین شعبه</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>نام شعبه</th>
                            <th>شناسه اینترنتی (اسلاگ)</th>
                            <th>نحوه کاتالوگ</th>
                            <th>هزینه ارسال پیش‌فرض</th>
                            <th>تلفن و نشانی</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $b): ?>
                            <tr>
                                <td>
                                    <strong><?= e($b['name']) ?></strong>
                                    <?php if ($b['is_main']): ?><span class="badge badge-blue">شعبه اصلی</span><?php endif; ?>
                                </td>
                                <td><code><?= e($b['slug']) ?></code></td>
                                <td>
                                    <?php if ($b['catalog_mode'] === 'business_only'): ?>
                                        <span class="badge badge-gray">صرفاً کالاهای کسب‌وکار</span>
                                    <?php elseif ($b['catalog_mode'] === 'branch_only'): ?>
                                        <span class="badge badge-amber">صرفاً کالاهای اختصاصی شعبه</span>
                                    <?php else: ?>
                                        <span class="badge badge-emerald">ترکیبی (کسب‌وکار + اختصاصی)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_irt((float)$b['default_shipping_cost']) ?></td>
                                <td style="font-size:0.82rem; color:#64748b;">
                                    <div><?= e($b['phone'] ?: '—') ?></div>
                                    <div style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($b['address'] ?: '—') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $b['active'] ? 'badge-emerald' : 'badge-gray' ?>">
                                        <?= $b['active'] ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="/b/<?= e($shop['slug']) ?>/<?= e($b['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="مشاهده ویترین">
                                            <?= icon('eye', 14) ?>
                                        </a>
                                        <a href="/app/branches/edit/<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm" title="ویرایش">
                                            <?= icon('edit', 14) ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php
    layout_end();
});

// 4. Shipping Groups Management
route('GET|POST', '/app/shipping-groups(?:\.php)?', ['business_owner', 'shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
        verify_csrf_or_die();
        $name = trim($_POST['name'] ?? '');
        $cost = clean_price_input($_POST['shipping_cost'] ?? 0);
        $threshold = clean_price_input($_POST['free_shipping_threshold'] ?? 0);
        $hub = trim($_POST['central_hub_address'] ?? '');

        if ($name) {
            $ins = $pdo->prepare("INSERT INTO shipping_groups (business_id, name, shipping_cost, free_shipping_threshold, central_hub_address, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
            $ins->execute([$shopId, $name, $cost, $threshold, $hub]);
            flash('success', "گروه ارسال متمرکز «{$name}» تعریف شد.");
            redirect('/app/shipping-groups');
        }
    }

    $groups = get_business_shipping_groups($shopId);
    layout_start('گروه‌های ارسال اشتراکی (لجستیک متمرکز)', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>گروه‌های ارسال اشتراکی کسب‌وکار</h1>
                <div class="page-sub">شعبی که در یک گروه ارسال قرار گیرند، سبد خرید و هزینه ارسال مشترک خواهند داشت.</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/app/branches">بازگشت به شعب</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
        <div class="card">
            <div class="card-header"><h2>تعریف گروه ارسال متمرکز جدید</h2></div>
            <form method="post" style="padding:20px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_group">
                <div class="form-group mb-2">
                    <label>عنوان گروه ارسال *</label>
                    <input class="input" name="name" required placeholder="مثال: ناوگان ارسال متمرکز تهران">
                </div>
                <div class="form-group mb-2">
                    <label>هزینه ارسال یکپارچه (تومان)</label>
                    <input class="input" name="shipping_cost" data-price-input value="45000">
                </div>
                <div class="form-group mb-2">
                    <label>حداقل خرید برای ارسال رایگان (تومان)</label>
                    <input class="input" name="free_shipping_threshold" data-price-input value="500000">
                </div>
                <div class="form-group mb-2">
                    <label>نشانی هاب تجمیع و ارسال مرسولات</label>
                    <textarea class="textarea" name="central_hub_address" rows="2" placeholder="مرکز توزیع مرکزی..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;"><?= icon('plus', 14) ?> ایجاد گروه ارسال</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h2>گروه‌های ارسال فعال</h2></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>نام گروه</th><th>هزینه ارسال</th><th>ارسال رایگان</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($groups)): ?>
                            <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">گروهی تعریف نشده است.</td></tr>
                        <?php else: ?>
                            <?php foreach ($groups as $g): ?>
                                <tr>
                                    <td><strong><?= e($g['name']) ?></strong></td>
                                    <td><?= format_irt((float)$g['shipping_cost']) ?></td>
                                    <td><?= (float)$g['free_shipping_threshold'] > 0 ? format_irt((float)$g['free_shipping_threshold']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    layout_end();
});

require_once __DIR__ . '/routes_branches_edit.php';
