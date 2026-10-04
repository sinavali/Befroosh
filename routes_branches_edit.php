<?php
declare(strict_types=1);

/**
 * routes_branches_edit.php
 * Create and Edit Branch Forms, Validation, and Catalog Mode Configuration
 */

// 1. Create Branch
route('GET|POST', '/app/branches/create', ['business_owner', 'shop_owner', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    // Plan check for max branches
    $planStmt = $pdo->prepare("SELECT p.max_branches FROM shops s JOIN subscription_plans p ON p.id = s.subscription_plan_id WHERE s.id = ?");
    $planStmt->execute([$shopId]);
    $maxBranches = (int)$planStmt->fetchColumn();

    $currentBranchCount = (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE business_id = {$shopId} AND active = 1")->fetchColumn();
    if ($currentBranchCount >= $maxBranches) {
        flash('error', "سقف مجاز تعداد شعب در طرح اشتراک شما ({$maxBranches} شعبه) تکمیل شده است. لطفاً طرح اشتراک خود را ارتقا دهید.");
        redirect('/app/branches');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: make_utf8_slug($name);
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $catalogMode = $_POST['catalog_mode'] ?? 'both';
        $shippingCost = clean_price_input($_POST['default_shipping_cost'] ?? 0);
        $freeShipping = clean_price_input($_POST['free_shipping_threshold'] ?? 0);
        $shippingGroupId = !empty($_POST['shipping_group_id']) ? (int)$_POST['shipping_group_id'] : null;

        if (!$name) {
            $error = 'نام شعبه الزامی است.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM branches WHERE business_id = ? AND slug = ?");
            $chk->execute([$shopId, $slug]);
            if ($chk->fetchColumn()) {
                $error = 'شناسه اینترنتی (اسلاگ) این شعبه قبلاً ثبت شده است.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO branches (business_id, name, slug, phone, address, catalog_mode, shipping_group_id, default_shipping_cost, free_shipping_threshold, active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
                ");
                $ins->execute([$shopId, $name, $slug, $phone, $address, $catalogMode, $shippingGroupId, $shippingCost, $freeShipping]);
                flash('success', "شعبه «{$name}» با موفقیت ایجاد شد.");
                redirect('/app/branches');
            }
        }
    }

    $shippingGroups = get_business_shipping_groups($shopId);
    layout_start('تعریف شعبه جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>تعریف شعبه جدید</h1>
                <div class="page-sub">افزودن شعبه جدید برای کسب‌وکار <?= e($shop['name']) ?></div>
            </div>
        </div>
        <a class="btn btn-outline" href="/app/branches">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="card" style="background:#fef2f2; color:#991b1b; padding:12px 16px; margin-bottom:16px; font-weight:bold;"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:800px;">
        <form method="post">
            <?= csrf_field() ?>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                    <div class="form-group">
                        <label>نام شعبه *</label>
                        <input class="input" name="name" required placeholder="مثال: شعبه تجریش">
                    </div>
                    <div class="form-group">
                        <label>شناسه اینترنتی یکتا (اسلاگ)</label>
                        <input class="input" name="slug" dir="ltr" placeholder="tajrish">
                    </div>
                    <div class="form-group">
                        <label>نحوه مدیریت کاتالوگ کالاهای شعبه</label>
                        <select class="select" name="catalog_mode">
                            <option value="both">کالاهای کل کسب‌وکار + کالاهای اختصاصی شعبه</option>
                            <option value="business_only">صرفاً کالاهای تعریف‌شده در سطح کل کسب‌وکار</option>
                            <option value="branch_only">صرفاً کالاهای اختصاصی تعریف‌شده توسط این شعبه</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>گروه ارسال متمرکز (اشتراکی)</label>
                        <select class="select" name="shipping_group_id">
                            <option value="">ارسال مستقل (لجستیک خود شعبه)</option>
                            <?php foreach ($shippingGroups as $sg): ?>
                                <option value="<?= (int)$sg['id'] ?>"><?= e($sg['name']) ?> (<?= format_irt((float)$sg['shipping_cost']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>هزینه ارسال پیش‌فرض شعبه (تومان)</label>
                        <input class="input" name="default_shipping_cost" data-price-input value="0">
                    </div>
                    <div class="form-group">
                        <label>حداقل خرید برای ارسال رایگان (تومان)</label>
                        <input class="input" name="free_shipping_threshold" data-price-input value="0">
                    </div>
                    <div class="form-group">
                        <label>شماره تماس شعبه</label>
                        <input class="input" name="phone" dir="ltr" placeholder="021...">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>نشانی فیزیکی شعبه</label>
                        <textarea class="textarea" name="address" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ایجاد شعبه</button>
            </div>
        </form>
    </div>
    <?php
    layout_end();
});

// 2. Edit Branch
route('GET|POST', '/app/branches/edit/(\d+)', ['business_owner', 'shop_owner', 'superadmin'], function ($branchId) use ($pdo) {
    $user = require_roles(['business_owner', 'shop_owner', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);
    $branchId = (int)$branchId;

    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND business_id = ?");
    $stmt->execute([$branchId, $shopId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) error_page(404, 'یافت نشد', 'شعبه مورد نظر یافت نشد.');

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: $branch['slug'];
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $catalogMode = $_POST['catalog_mode'] ?? 'both';
        $shippingCost = clean_price_input($_POST['default_shipping_cost'] ?? 0);
        $freeShipping = clean_price_input($_POST['free_shipping_threshold'] ?? 0);
        $shippingGroupId = !empty($_POST['shipping_group_id']) ? (int)$_POST['shipping_group_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$name) {
            $error = 'نام شعبه الزامی است.';
        } else {
            $upd = $pdo->prepare("
                UPDATE branches 
                SET name = ?, slug = ?, phone = ?, address = ?, catalog_mode = ?, shipping_group_id = ?, 
                    default_shipping_cost = ?, free_shipping_threshold = ?, active = ?, updated_at = datetime('now')
                WHERE id = ? AND business_id = ?
            ");
            $upd->execute([$name, $slug, $phone, $address, $catalogMode, $shippingGroupId, $shippingCost, $freeShipping, $active, $branchId, $shopId]);

            // Disable invalid branch-specific items if switched to business_only
            if ($catalogMode === 'business_only') {
                $pdo->prepare("UPDATE products SET active = 0 WHERE branch_id = ? AND is_business_product = 0")->execute([$branchId]);
            }

            flash('success', 'مشخصات شعبه با موفقیت به‌روزرسانی شد.');
            redirect('/app/branches');
        }
    }

    $shippingGroups = get_business_shipping_groups($shopId);
    layout_start('ویرایش شعبه ' . $branch['name'], $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>ویرایش شعبه <?= e($branch['name']) ?></h1>
                <div class="page-sub">تغییر اطلاعات، نحوه کاتالوگ و لجستیک ارسال</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/app/branches">بازگشت</a>
    </div>

    <?php if ($error): ?><div class="card" style="background:#fef2f2; color:#991b1b; padding:12px 16px; margin-bottom:16px; font-weight:bold;"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:800px;">
        <form method="post">
            <?= csrf_field() ?>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                    <div class="form-group">
                        <label>نام شعبه *</label>
                        <input class="input" name="name" value="<?= e($branch['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>شناسه اینترنتی یکتا (اسلاگ)</label>
                        <input class="input" name="slug" dir="ltr" value="<?= e($branch['slug']) ?>">
                    </div>
                    <div class="form-group">
                        <label>نحوه مدیریت کاتالوگ کالاهای شعبه</label>
                        <select class="select" name="catalog_mode">
                            <option value="both" <?= $branch['catalog_mode'] === 'both' ? 'selected' : '' ?>>کالاهای کل کسب‌وکار + کالاهای اختصاصی شعبه</option>
                            <option value="business_only" <?= $branch['catalog_mode'] === 'business_only' ? 'selected' : '' ?>>صرفاً کالاهای تعریف‌شده در سطح کل کسب‌وکار</option>
                            <option value="branch_only" <?= $branch['catalog_mode'] === 'branch_only' ? 'selected' : '' ?>>صرفاً کالاهای اختصاصی تعریف‌شده توسط این شعبه</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>گروه ارسال متمرکز (اشتراکی)</label>
                        <select class="select" name="shipping_group_id">
                            <option value="">ارسال مستقل (لجستیک خود شعبه)</option>
                            <?php foreach ($shippingGroups as $sg): ?>
                                <option value="<?= (int)$sg['id'] ?>" <?= (int)$branch['shipping_group_id'] === (int)$sg['id'] ? 'selected' : '' ?>><?= e($sg['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>هزینه ارسال پیش‌فرض شعبه (تومان)</label>
                        <input class="input" name="default_shipping_cost" data-price-input value="<?= (int)$branch['default_shipping_cost'] ?>">
                    </div>
                    <div class="form-group">
                        <label>حداقل خرید برای ارسال رایگان (تومان)</label>
                        <input class="input" name="free_shipping_threshold" data-price-input value="<?= (int)$branch['free_shipping_threshold'] ?>">
                    </div>
                    <div class="form-group">
                        <label>شماره تماس شعبه</label>
                        <input class="input" name="phone" dir="ltr" value="<?= e($branch['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:8px; margin-top:24px;">
                        <input type="checkbox" name="active" value="1" id="br_active" <?= $branch['active'] ? 'checked' : '' ?>>
                        <label for="br_active" style="cursor:pointer; font-weight:bold;">شعبه فعال باشد</label>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>نشانی فیزیکی شعبه</label>
                        <textarea class="textarea" name="address" rows="2"><?= e($branch['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تغییرات</button>
            </div>
        </form>
    </div>
    <?php
    layout_end();
});
