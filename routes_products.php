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

require_once __DIR__ . '/routes_products_create.php';
require_once __DIR__ . '/routes_products_edit.php';
require_once __DIR__ . '/routes_categories.php';

