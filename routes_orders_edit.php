<?php
declare(strict_types=1);

/**
 * routes_orders_edit.php
 * Order details editing for managers/admins
 */

route('GET|POST', '/orders/(\d+)/edit', ['superadmin', 'admin', 'business_owner', 'shop_owner', 'shop_manager', 'branch_manager', 'manager'], function ($id) use ($pdo) {
    $user = require_roles(['superadmin', 'admin', 'business_owner', 'shop_owner', 'shop_manager', 'branch_manager', 'manager']);
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_page(404, 'سفارش یافت نشد', 'سفارش مورد نظر وجود ندارد.');
    }

    $isPlatformAdmin = in_array($user['role'], ['superadmin', 'admin'], true);
    $isShopStaff = can_manage_shop($user, (int)$order['shop_id']);

    if (!$isShopStaff && !$isPlatformAdmin) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به ویرایش این سفارش دسترسی ندارید.');
    }

    $addr = json_unsnapshot($order['address_snapshot'] ?? '{}');
    $cust = json_unsnapshot($order['customer_snapshot'] ?? '{}');
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        // Update basic info
        $newDesc = trim($_POST['description'] ?? '');
        $newTracking = trim($_POST['tracking_code'] ?? '');
        
        // Update customer snapshot
        $cust['nickname'] = trim($_POST['cust_nickname'] ?? $cust['nickname'] ?? '');
        $cust['phone'] = trim($_POST['cust_phone'] ?? $cust['phone'] ?? '');

        // Update address snapshot
        $addr['state'] = trim($_POST['addr_state'] ?? $addr['state'] ?? '');
        $addr['city'] = trim($_POST['addr_city'] ?? $addr['city'] ?? '');
        $addr['address'] = trim($_POST['addr_address'] ?? $addr['address'] ?? '');
        $addr['postal_code'] = trim($_POST['addr_postal'] ?? $addr['postal_code'] ?? '');

        if (!$cust['nickname'] || !$cust['phone'] || !$addr['state'] || !$addr['city'] || !$addr['address']) {
            $error = 'تمامی فیلدهای ستاره‌دار الزامی هستند.';
        } else {
            $pdo->prepare("
                UPDATE orders 
                SET description = ?, tracking_code = ?, customer_snapshot = ?, address_snapshot = ?, updated_at = datetime('now')
                WHERE id = ?
            ")->execute([$newDesc, $newTracking, json_snapshot($cust), json_snapshot($addr), $id]);

            flash('success', 'جزئیات سفارش با موفقیت ویرایش شد.');
            redirect('/orders/' . $id);
        }
    }

    layout_start('ویرایش سفارش ' . $order['uuid'], $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>ویرایش سفارش #<?= e($order['uuid']) ?></h1>
                <div class="page-sub">تغییر آدرس، اطلاعات گیرنده و توضیحات سفارش</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/orders/<?= $id ?>">بازگشت به سفارش</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات گیرنده</h2></div>
            <div class="card-body form-grid">
                <div class="form-group">
                    <label>نام گیرنده *</label>
                    <input class="input" name="cust_nickname" value="<?= e($cust['nickname'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>موبایل گیرنده *</label>
                    <input class="input" name="cust_phone" value="<?= e($cust['phone'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>آدرس تحویل</h2></div>
            <div class="card-body form-grid">
                <div class="form-group">
                    <label>استان *</label>
                    <input class="input" name="addr_state" value="<?= e($addr['state'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>شهر *</label>
                    <input class="input" name="addr_city" value="<?= e($addr['city'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>نشانی پستی *</label>
                    <input class="input" name="addr_address" value="<?= e($addr['address'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>کد پستی</label>
                    <input class="input" name="addr_postal" value="<?= e($addr['postal_code'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات مرسوله</h2></div>
            <div class="card-body form-grid">
                <div class="form-group">
                    <label>کد رهگیری پستی</label>
                    <input class="input" name="tracking_code" value="<?= e($order['tracking_code'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>توضیحات سفارش</label>
                    <textarea class="textarea" name="description" rows="3"><?= e($order['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" style="width:100%;"><?= icon('check', 14) ?> ذخیره تغییرات سفارش</button>
    </form>
    <?php
    layout_end();
});
