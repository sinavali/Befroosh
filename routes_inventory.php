<?php
declare(strict_types=1);

// Inventory list & overview
route('GET', '/inventory(?:\.php)?', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $shop = current_shop();

    $search = trim($_GET['search'] ?? '');
    $lowStockOnly = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';
    $catId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $where = "WHERE p.deleted_at IS NULL";
    $params = [];

    if ($user['role'] !== 'superadmin' || empty($_GET['all_shops'])) {
        $where .= " AND p.shop_id = ?";
        $params[] = $shopId;
    }

    if ($lowStockOnly) {
        $where .= " AND p.stock_quantity <= p.min_stock_alert";
    }

    if ($catId) {
        $where .= " AND p.category_id = ?";
        $params[] = $catId;
    }

    if ($search !== '') {
        $where .= " AND (p.title LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // Counts & KPIs
    $kpiParams = ($user['role'] !== 'superadmin' || empty($_GET['all_shops'])) ? [$shopId] : [];
    $kpiWhere = ($user['role'] !== 'superadmin' || empty($_GET['all_shops'])) ? "WHERE shop_id = ? AND deleted_at IS NULL" : "WHERE deleted_at IS NULL";
    
    $stmtKpi = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_skus,
            COALESCE(SUM(stock_quantity), 0) AS total_units,
            COALESCE(SUM(stock_quantity * cost_price), 0) AS total_cost_value,
            COALESCE(SUM(stock_quantity * price), 0) AS total_retail_value,
            COALESCE(SUM(CASE WHEN stock_quantity <= min_stock_alert THEN 1 ELSE 0 END), 0) AS low_stock_count
        FROM products
        $kpiWhere
    ");
    $stmtKpi->execute($kpiParams);
    $kpis = $stmtKpi->fetch(PDO::FETCH_ASSOC);

    // Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $pag = paginate($totalRows, $perPage, $page);

    // Products fetch
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, s.name AS shop_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN shops s ON s.id = p.shop_id
        $where
        ORDER BY (p.stock_quantity <= p.min_stock_alert) DESC, p.stock_quantity ASC, p.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categories = $pdo->prepare("SELECT id, name FROM categories WHERE shop_id = ? ORDER BY sort_order ASC, name ASC");
    $categories->execute([$shopId]);
    $catList = $categories->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت انبار و موجودی', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('products', 18) ?></div>
            <div>
                <h1>مدیریت موجودی و انبارداری</h1>
                <div class="page-sub">فروشگاه: <strong><?= e($shop['name'] ?? 'همه فروشگاه‌ها') ?></strong> | کنترل لحظه‌ای کاردکس و موجودی انبار</div>
            </div>
        </div>
        <div class="action-cluster">
            <a class="btn btn-outline" href="/inventory/count-sheet" target="_blank"><?= icon('print', 14) ?> چاپ برگه انبارگردانی</a>
            <a class="btn btn-outline" href="/inventory/transactions"><?= icon('report', 14) ?> گردش انبار (کاردکس)</a>
            <a class="btn btn-primary" href="/inventory/inward"><?= icon('plus', 14) ?> ثبت ورود کالا به انبار</a>
            <a class="btn btn-outline" href="/inventory/adjustment"><?= icon('edit', 14) ?> تعدیل موجودی</a>
        </div>
    </div>

    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('products', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$kpis['total_skus']) ?></div>
                <div class="stat-label">تنوع اقلام کالا (SKU)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon emerald"><?= icon('check', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$kpis['total_units']) ?></div>
                <div class="stat-label">کل موجودی فیزیکی (واحد)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('report', 18) ?></div>
            <div>
                <div class="stat-value"><?= format_irr((float)$kpis['total_cost_value']) ?></div>
                <div class="stat-label">ارزش موجودی بر اساس بهای تمام‌شده</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon <?= (int)$kpis['low_stock_count'] > 0 ? 'rose' : 'gray' ?>"><?= icon('clock', 18) ?></div>
            <div>
                <div class="stat-value"><?= en_to_fa_digits((string)$kpis['low_stock_count']) ?></div>
                <div class="stat-label">اقلام با کسری موجودی / در مرز هشدار</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="filter-bar">
                <input class="input" type="text" name="search" placeholder="جستجو بر اساس عنوان، بارکد یا شناسه کالا..." value="<?= e($search) ?>" style="flex:1; min-width:220px;">
                <select class="select" name="category_id">
                    <option value="">همه دسته‌بندی‌ها</option>
                    <?php foreach ($catList as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-check" style="margin:0 10px;">
                    <input type="checkbox" name="low_stock" value="1" <?= $lowStockOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>فقط کالاهای رو به اتمام</span>
                </label>
                <button class="btn btn-primary btn-sm"><?= icon('filter', 13) ?> اعمال فیلتر</button>
                <?php if ($search || $lowStockOnly || $catId): ?>
                    <a class="btn btn-outline btn-sm" href="/inventory">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>تصویر</th>
                        <th>کد کالا (SKU)</th>
                        <th>بارکد</th>
                        <th>نام کالا</th>
                        <th>دسته‌بندی</th>
                        <th>واحد سنجش</th>
                        <th>قیمت خرید</th>
                        <th>قیمت فروش</th>
                        <th>موجودی فعلی</th>
                        <th>حداقل هشدار</th>
                        <th>عملیات انبار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items): ?>
                        <tr><td colspan="11"><?= empty_state('هیچ کالایی یافت نشد', 'می‌توانید کالاها را از بخش مدیریت محصولات ثبت نمایید.') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $p): ?>
                            <?php $isLow = (float)$p['stock_quantity'] <= (float)$p['min_stock_alert']; ?>
                            <tr style="<?= $isLow ? 'background:#fff7ed;' : '' ?>">
                                <td>
                                    <?php if (!empty($p['image_path'])): ?>
                                        <img src="/storage/<?= e($p['image_path']) ?>" class="thumb" alt="<?= e($p['title']) ?>">
                                    <?php else: ?>
                                        <div class="thumb thumb-placeholder"><?= icon('products', 14) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($p['sku'] ?: '—') ?></code></td>
                                <td><code style="background:#f3f4f6; padding:2px 6px; border-radius:4px;"><?= e($p['barcode'] ?: '—') ?></code></td>
                                <td>
                                    <strong><?= e($p['title']) ?></strong>
                                    <?php if ($isLow): ?>
                                        <span class="badge badge-rose" style="margin-right:6px;">کسری موجودی</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($p['category_name'] ?: 'عمومی') ?></td>
                                <td><?= e($p['unit'] ?: 'عدد') ?></td>
                                <td><?= format_irr((float)$p['cost_price']) ?></td>
                                <td><?= format_irr((float)$p['price']) ?></td>
                                <td>
                                    <strong style="font-size:1.05rem; color:<?= $isLow ? '#dc2626' : '#15803d' ?>;">
                                        <?= en_to_fa_digits((string)(float)$p['stock_quantity']) ?>
                                    </strong>
                                    <?= e($p['unit'] ?: 'عدد') ?>
                                </td>
                                <td><?= en_to_fa_digits((string)(float)$p['min_stock_alert']) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a class="btn btn-outline btn-sm" href="/inventory/inward?product_id=<?= (int)$p['id'] ?>" title="ورود کالا به انبار">+ ورود</a>
                                        <a class="btn btn-outline btn-sm" href="/inventory/adjustment?product_id=<?= (int)$p['id'] ?>" title="تعدیل موجودی">تعدیل</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/inventory', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

require_once __DIR__ . '/routes_inventory_ops.php';
require_once __DIR__ . '/routes_inventory_tx.php';

