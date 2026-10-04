<?php
declare(strict_types=1);

/**
 * routes_products.php
 * Product & Category Management for Shop Owners, Shop Managers, and Platform Admins
 * Supports barcode scanning, purchase caps (max_per_order, max_per_month), and categories
 */

// Helper to determine active shop for current user
function get_current_management_shop(array $user): array
{
    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true)) {
        $shopId = (int)($user['shop_id'] ?? 1);
    } else {
        $shopId = active_shop_id();
    }
    $shop = get_shop($shopId);
    return [$shopId, $shop ?: ['id' => $shopId, 'name' => 'فروشگاه']];
}

// -------------------------------------------------------------
// 1. PRODUCTS LIST
// -------------------------------------------------------------
route('GET', '/products(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $search = trim($_GET['search'] ?? '');
    $active = $_GET['active'] ?? '';
    $catId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    [$sort, $dir] = get_sort(['id', 'title', 'price', 'stock_quantity', 'created_at'], 'id');

    $where = "WHERE p.deleted_at IS NULL";
    $params = [];

    if (!is_platform_admin($user) || empty($_GET['all_shops'])) {
        $where .= " AND p.shop_id = ?";
        $params[] = $shopId;
    }

    if ($search !== '') {
        $where .= " AND (p.title LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.description LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($active !== '') {
        $where .= " AND p.active = ?";
        $params[] = (int)$active;
    }

    if ($catId > 0) {
        $where .= " AND p.category_id = ?";
        $params[] = $catId;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, s.name AS shop_name, s.slug AS shop_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN shops s ON s.id = p.shop_id
        $where
        ORDER BY p.$sort $dir
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $catStmt->execute([$shopId]);
    $catList = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت محصولات', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('products', 18) ?></div>
            <div>
                <h1>مدیریت محصولات</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? 'همه') ?></strong> | محصولات قابل سفارش، موجودی و بارکد</div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/categories"><?= icon('filter', 14) ?> دسته‌بندی‌ها</a>
            <a class="btn btn-primary" href="/products/create"><?= icon('plus', 14) ?> افزودن محصول جدید</a>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجو با عنوان، SKU، بارکد..." value="<?= e($search) ?>" data-barcode-input style="min-width:240px;">

                <select class="select" name="category_id">
                    <option value="">همه دسته‌بندی‌ها</option>
                    <?php foreach ($catList as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select" name="active">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>غیرفعال</option>
                </select>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <button type="button" class="btn btn-outline" onclick="BefrooshScanner.openCamera(function(code){ document.querySelector('[data-barcode-input]').value = code; document.forms[0].submit(); })">
                    اسکن بارکد با دوربین
                </button>
                <?php if ($search !== '' || $active !== '' || $catId > 0): ?>
                    <a class="btn btn-ghost" href="/products">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- PRODUCTS TABLE -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">تصویر</th>
                        <th><?= sort_link('/products', 'عنوان کالا', 'title', $sort, $dir) ?></th>
                        <th>دسته‌بندی</th>
                        <th>بارکد / SKU</th>
                        <th><?= sort_link('/products', 'قیمت فروش', 'price', $sort, $dir) ?></th>
                        <th><?= sort_link('/products', 'موجودی', 'stock_quantity', $sort, $dir) ?></th>
                        <th>سقف خرید</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9"><?= empty_state('هیچ کالایی یافت نشد') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image_path']) && file_exists(STORAGE_PATH . '/' . $p['image_path'])): ?>
                                    <img src="/storage/<?= e($p['image_path']) ?>" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">
                                <?php else: ?>
                                    <div style="width:36px; height:36px; background:#f3f4f6; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:0.65rem;">عکس</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($p['title']) ?></strong>
                                <div style="font-size:0.75rem; color:#6b7280;">واحد: <?= e($p['unit'] ?: 'عدد') ?></div>
                            </td>
                            <td><?= e($p['category_name'] ?: '—') ?></td>
                            <td>
                                <code><?= e($p['sku'] ?: '—') ?></code>
                                <?php if (!empty($p['barcode'])): ?>
                                    <div style="font-size:0.75rem; color:#6b7280; font-family:monospace;"><?= e($p['barcode']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= format_irr((float)$p['price']) ?></td>
                            <td>
                                <?php if ((float)$p['stock_quantity'] <= 0): ?>
                                    <span class="badge badge-rose">اتمام موجودی</span>
                                <?php elseif ((float)$p['stock_quantity'] <= (float)$p['min_stock_alert']): ?>
                                    <span class="badge badge-amber"><?= en_to_fa_digits((string)$p['stock_quantity']) ?> (هشدار کسری)</span>
                                <?php else: ?>
                                    <span class="badge badge-emerald"><?= en_to_fa_digits((string)$p['stock_quantity']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem;">
                                <?php if (!empty($p['max_per_order']) && $p['max_per_order'] > 0): ?>
                                    هر سفارش: <?= en_to_fa_digits((string)$p['max_per_order']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($p['max_per_month']) && $p['max_per_month'] > 0): ?>
                                    ماهانه: <?= en_to_fa_digits((string)$p['max_per_month']) ?>
                                <?php endif; ?>
                                <?php if (empty($p['max_per_order']) && empty($p['max_per_month'])): ?>
                                    <span style="color:#9ca3af;">نامحدود</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $p['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?>
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <a class="btn btn-outline btn-sm" href="/s/<?= urlencode($p['shop_slug'] ?: 'central') ?>/p/<?= (int)$p['id'] ?>" target="_blank" title="مشاهده صفحه کالا"><?= icon('eye', 13) ?></a>
                                    <a class="btn btn-outline btn-sm" href="/products/<?= (int)$p['id'] ?>/edit"><?= icon('edit', 13) ?> ویرایش</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/products', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

// -------------------------------------------------------------
// 2. PRODUCT CREATE
// -------------------------------------------------------------
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
                    <label>توضیحات و مشخصات کالا</label>
                    <textarea class="textarea" name="description" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
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

// -------------------------------------------------------------
// 3. PRODUCT EDIT
// -------------------------------------------------------------
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
        $price = max(0, (float)fa_to_en_digits($_POST['price'] ?? '0'));
        $costPrice = max(0, (float)fa_to_en_digits($_POST['cost_price'] ?? '0'));
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
                    <label>توضیحات و مشخصات کالا</label>
                    <textarea class="textarea" name="description" rows="4"><?= e($prod['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>قیمت‌گذاری و محدودیت‌های خرید</h2></div>
            <div class="card-body">
                <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                    <div class="form-group">
                        <label>قیمت فروش (ریال) *</label>
                        <input class="input" type="number" name="price" value="<?= e((string)$prod['price']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>قیمت تمام‌شده / خرید (ریال)</label>
                        <input class="input" type="number" name="cost_price" value="<?= e((string)$prod['cost_price']) ?>">
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

// -------------------------------------------------------------
// 4. CATEGORIES CRUD
// -------------------------------------------------------------
route('GET', '/categories(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    [$shopId, $shop] = get_current_management_shop($user);

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$shopId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت دسته‌بندی‌ها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('filter', 18) ?></div>
            <div>
                <h1>دسته‌بندی‌های محصولات</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name']) ?></strong></div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/products">بازگشت به محصولات</a>
            <button class="btn btn-primary" data-modal="#newCategoryModal"><?= icon('plus', 14) ?> دسته‌بندی جدید</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام دسته‌بندی</th>
                        <th>ترتیب نمایش</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5"><?= empty_state('دسته‌بندی ثبت نشده است') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>#<?= en_to_fa_digits((string)$cat['id']) ?></td>
                            <td><strong><?= e($cat['name']) ?></strong></td>
                            <td><?= en_to_fa_digits((string)$cat['sort_order']) ?></td>
                            <td><?= $cat['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?></td>
                            <td>
                                <form method="post" action="/categories/<?= (int)$cat['id'] ?>/delete" class="inline-form" data-confirm="این دسته‌بندی حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm"><?= icon('trash', 13) ?> حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- NEW CATEGORY MODAL -->
    <div class="modal-backdrop" id="newCategoryModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/categories/create">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>افزودن دسته‌بندی جدید</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>نام دسته‌بندی *</label>
                        <input class="input" name="name" required placeholder="مثال: پوشاک مردانه">
                    </div>
                    <div class="form-group">
                        <label>ترتیب نمایش</label>
                        <input class="input" type="number" name="sort_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> ایجاد دسته‌بندی</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

route('POST', '/categories/create', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    [$shopId] = get_current_management_shop($user);

    $name = trim($_POST['name'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $slug = make_utf8_slug($name);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO categories (shop_id, name, slug, sort_order, active, created_at) VALUES (?, ?, ?, ?, 1, datetime('now'))");
        $stmt->execute([$shopId, $name, $slug, $sortOrder]);
        flash('success', 'دسته‌بندی با موفقیت ایجاد شد.');
    }
    redirect('/categories');
});

route('POST', '/categories/(\d+)/delete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    [$shopId] = get_current_management_shop($user);
    $id = (int)$id;

    $pdo->prepare("DELETE FROM categories WHERE id = ? AND shop_id = ?")->execute([$id, $shopId]);
    flash('success', 'دسته‌بندی حذف شد.');
    redirect('/categories');
});
