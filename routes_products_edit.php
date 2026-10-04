<?php
declare(strict_types=1);

/**
 * routes_products_edit.php
 * Product Edit Route & Form
 */

route('GET|POST', '/products/(\d+)/edit', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        error_page(404, 'کالا یافت نشد', 'کالای مورد نظر برای ویرایش وجود ندارد.');
    }

    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$prod['shop_id'] !== (int)($user['shop_id'] ?? 0)) {
        error_page(403, 'دسترسی غیرمجاز', 'شما به کالاهای این فروشگاه دسترسی ندارید.');
    }

    $shop = get_shop((int)$prod['shop_id']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $title = trim($_POST['title'] ?? '');
        $price = max(0, clean_price_input($_POST['price'] ?? '0'));
        $costPrice = max(0, clean_price_input($_POST['cost_price'] ?? '0'));
        $minStockAlert = max(0, (float)fa_to_en_digits($_POST['min_stock_alert'] ?? '5'));
        $minOrderQty = max(1, (float)fa_to_en_digits($_POST['min_order_qty'] ?? '1'));
        $maxPerOrder = max(0, (float)fa_to_en_digits($_POST['max_per_order'] ?? '0'));
        $maxPerMonth = max(0, (float)fa_to_en_digits($_POST['max_per_month'] ?? '0'));
        $taxRate = max(0, (float)fa_to_en_digits($_POST['tax_rate'] ?? '0')) / 100.0;
        $sku = trim($_POST['sku'] ?? '') ?: null;
        $barcode = trim(fa_to_en_digits($_POST['barcode'] ?? '')) ?: null;
        $unit = trim($_POST['unit'] ?? 'عدد') ?: 'عدد';
        $weightGrams = max(0, (int)fa_to_en_digits($_POST['weight_grams'] ?? '0'));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $active = isset($_POST['active']) ? 1 : 0;
        $slug = make_utf8_slug($title);

        $imagePath = $prod['image_path'];
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $imgName = 'prod_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], PRODUCT_UPLOAD_PATH . '/' . $imgName)) {
                    $imagePath = 'products/' . $imgName;
                }
            }
        }

        if (!$title) {
            $error = 'عنوان کالا الزامی است.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET category_id = ?, barcode = ?, unit = ?, weight_grams = ?, title = ?, slug = ?,
                        description = ?, price = ?, cost_price = ?, min_stock_alert = ?, min_order_qty = ?,
                        max_per_order = ?, max_per_month = ?, tax_rate = ?, sku = ?, image_path = ?, active = ?,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([
                    $categoryId, $barcode, $unit, $weightGrams, $title, $slug,
                    $desc, $price, $costPrice, $minStockAlert, $minOrderQty,
                    $maxPerOrder, $maxPerMonth, $taxRate, $sku, $imagePath, $active, $id
                ]);

                flash('success', 'مشخصات محصول با موفقیت به‌روزرسانی شد.');
                redirect('/products');
            } catch (Throwable $e) {
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'کد SKU یا بارکد تکراری است.' : 'خطا در ویرایش کالا: ' . $e->getMessage();
            }
        }
    }

    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$prod['shop_id']]);
    $catList = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('ویرایش محصول ' . $prod['title'], $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>ویرایش محصول: <?= e($prod['title']) ?></h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? '—') ?></strong> | موجودی فعلی: <?= en_to_fa_digits((string)$prod['stock_quantity']) ?> <?= e($prod['unit'] ?: 'عدد') ?></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/s/<?= urlencode($shop['slug'] ?: 'central') ?>/p/<?= $id ?>" target="_blank">مشاهده ویترین</a>
            <a class="btn btn-outline" href="/products">بازگشت</a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><h2>اطلاعات پایه کالا</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>عنوان کالا *</label>
                        <input class="input" name="title" value="<?= e($prod['title']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>دسته‌بندی</label>
                        <select class="select" name="category_id">
                            <option value="">انتخاب نشده</option>
                            <?php foreach ($catList as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$prod['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>کد کالا (SKU)</label>
                        <input class="input" name="sku" dir="ltr" value="<?= e($prod['sku'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>کد بارکد</label>
                        <input class="input" name="barcode" dir="ltr" data-barcode-input value="<?= e($prod['barcode'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>واحد سنجش</label>
                        <input class="input" name="unit" value="<?= e($prod['unit'] ?? 'عدد') ?>">
                    </div>

                    <div class="form-group">
                        <label>وزن تقریبی (گرم)</label>
                        <input class="input" type="number" name="weight_grams" value="<?= e((string)$prod['weight_grams']) ?>">
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>تصویر کالا</label>
                    <?php if (!empty($prod['image_path'])): ?>
                        <div style="margin-bottom:6px;"><img src="/storage/<?= e($prod['image_path']) ?>" alt="" style="width:60px; height:60px; object-fit:cover; border-radius:6px;"></div>
                    <?php endif; ?>
                    <input class="input" type="file" name="image" accept="image/*">
                </div>

                <div class="form-group mt-2">
                    <label>توضیحات و مشخصات کالا (ویرایشگر متن پیشرفته)</label>
                    <textarea class="textarea" name="description" rows="4" data-rich-editor="true"><?= e($prod['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>قیمت‌گذاری و محدودیت‌های خرید</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>قیمت فروش (تومان) *</label>
                        <input class="input price-input" type="text" inputmode="numeric" name="price" value="<?= number_format((float)($prod['price'] ?? 0)) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>قیمت تمام‌شده / خرید (تومان)</label>
                        <input class="input price-input" type="text" inputmode="numeric" name="cost_price" value="<?= number_format((float)($prod['cost_price'] ?? 0)) ?>">
                    </div>

                    <div class="form-group">
                        <label>هشدار کسری موجودی</label>
                        <input class="input" type="number" name="min_stock_alert" value="<?= e((string)$prod['min_stock_alert']) ?>">
                    </div>

                    <div class="form-group">
                        <label>سقف خرید در هر سفارش (۰ = نامحدود)</label>
                        <input class="input" type="number" name="max_per_order" value="<?= e((string)($prod['max_per_order'] ?? 0)) ?>">
                    </div>

                    <div class="form-group">
                        <label>سقف خرید ماهانه هر کاربر (۰ = نامحدود)</label>
                        <input class="input" type="number" name="max_per_month" value="<?= e((string)($prod['max_per_month'] ?? 0)) ?>">
                    </div>

                    <div class="form-group">
                        <label>درصد مالیات بر ارزش افزوده (٪)</label>
                        <input class="input" type="number" name="tax_rate" step="0.1" value="<?= e((string)(($prod['tax_rate'] ?? 0) * 100)) ?>">
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; margin-top:20px;">
                        <label class="form-check">
                            <input type="checkbox" name="active" value="1" <?= $prod['active'] ? 'checked' : '' ?>>
                            <span>کالا فعال باشد</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ذخیره تغییرات</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

route('POST', '/products/(\d+)/delete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $id = (int)$id;

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prod) {
        if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)$prod['shop_id'] !== (int)($user['shop_id'] ?? 0)) {
            error_page(403, 'دسترسی غیرمجاز', 'شما به کالاهای این فروشگاه دسترسی ندارید.');
        }
        $pdo->prepare("UPDATE products SET deleted_at = datetime('now') WHERE id = ?")->execute([$id]);
        flash('success', 'کالا با موفقیت حذف گردید.');
    }
    redirect('/products');
});

