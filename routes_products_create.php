<?php
declare(strict_types=1);

/**
 * routes_products_create.php
 * Product Creation Route & Form
 */

route('GET|POST', '/products/create', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $title = trim($_POST['title'] ?? '');
        $price = max(0, (float)fa_to_en_digits($_POST['price'] ?? '0'));
        $costPrice = max(0, (float)fa_to_en_digits($_POST['cost_price'] ?? '0'));
        $stockQty = max(0, (float)fa_to_en_digits($_POST['stock_quantity'] ?? '0'));
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

        $imagePath = null;
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
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        shop_id, category_id, barcode, unit, weight_grams, title, slug, description,
                        price, cost_price, stock_quantity, min_stock_alert, min_order_qty,
                        max_per_order, max_per_month, tax_rate, sku, image_path, active, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now')
                    )
                ");
                $stmt->execute([
                    $shopId, $categoryId, $barcode, $unit, $weightGrams, $title, $slug, $desc,
                    $price, $costPrice, $stockQty, $minStockAlert, $minOrderQty,
                    $maxPerOrder, $maxPerMonth, $taxRate, $sku, $imagePath, $active
                ]);
                $newId = (int)$pdo->lastInsertId();

                if ($stockQty > 0) {
                    record_inventory_tx($shopId, $newId, 'inward', $stockQty, $costPrice, 'initial_stock', null, 'موجودی اولیه هنگام ثبت کالا', (int)$user['id']);
                }

                $pdo->commit();
                flash('success', 'محصول جدید با موفقیت ایجاد شد.');
                redirect('/products');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = str_contains($e->getMessage(), 'UNIQUE') ? 'کد SKU یا بارکد تکراری است.' : 'خطا در ثبت کالا: ' . $e->getMessage();
            }
        }
    }

    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$shopId]);
    $catList = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('افزودن محصول جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>افزودن محصول جدید</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name']) ?></strong> | مشخصات، قیمت، بارکد و محدودیت‌های خرید</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/products">بازگشت</a>
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
                        <input class="input" name="title" value="<?= e($_POST['title'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>دسته‌بندی</label>
                        <select class="select" name="category_id">
                            <option value="">انتخاب نشده</option>
                            <?php foreach ($catList as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>کد کالا (SKU)</label>
                        <input class="input" name="sku" dir="ltr" placeholder="مثال: PRD-1001" value="<?= e($_POST['sku'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>کد بارکد (EAN/Code128)</label>
                        <input class="input" name="barcode" dir="ltr" data-barcode-input placeholder="اسکن با بارکدخوان یا دستی" value="<?= e($_POST['barcode'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>واحد سنجش</label>
                        <input class="input" name="unit" value="<?= e($_POST['unit'] ?? 'عدد') ?>">
                    </div>

                    <div class="form-group">
                        <label>وزن تقریبی (گرم)</label>
                        <input class="input" type="number" name="weight_grams" value="<?= e($_POST['weight_grams'] ?? '0') ?>">
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>تصویر کالا</label>
                    <input class="input" type="file" name="image" accept="image/*">
                </div>

                <div class="form-group mt-2">
                    <label>توضیحات و مشخصات کالا (ویرایشگر متن پیشرفته)</label>
                    <textarea class="textarea" name="description" rows="4" data-rich-editor="true"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>قیمت‌گذاری، موجودی و محدودیت‌های خرید</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>قیمت فروش (ریال) *</label>
                        <input class="input" type="number" name="price" value="<?= e($_POST['price'] ?? '0') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>قیمت تمام‌شده / خرید (ریال)</label>
                        <input class="input" type="number" name="cost_price" value="<?= e($_POST['cost_price'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>موجودی اولیه انبار</label>
                        <input class="input" type="number" name="stock_quantity" value="<?= e($_POST['stock_quantity'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>هشدار کسری موجودی</label>
                        <input class="input" type="number" name="min_stock_alert" value="<?= e($_POST['min_stock_alert'] ?? '5') ?>">
                    </div>

                    <div class="form-group">
                        <label>سقف خرید در هر سفارش (۰ = نامحدود)</label>
                        <input class="input" type="number" name="max_per_order" value="<?= e($_POST['max_per_order'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>سقف خرید ماهانه هر کاربر (۰ = نامحدود)</label>
                        <input class="input" type="number" name="max_per_month" value="<?= e($_POST['max_per_month'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>درصد مالیات بر ارزش افزوده (٪)</label>
                        <input class="input" type="number" name="tax_rate" step="0.1" value="<?= e($_POST['tax_rate'] ?? '0') ?>">
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; margin-top:20px;">
                        <label class="form-check">
                            <input type="checkbox" name="active" value="1" checked>
                            <span>کالا فعال و آماده سفارش باشد</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ذخیره محصول</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});
