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

// Inward stock entry (ورود کالا به انبار / خرید)
route('GET|POST', '/inventory/inward', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $prodId = (int)($_POST['product_id'] ?? 0);
        $qty = (float)fa_to_en_digits($_POST['quantity'] ?? '0');
        $unitCost = (float)fa_to_en_digits($_POST['unit_cost'] ?? '0');
        $invoiceRef = trim($_POST['invoice_ref'] ?? '') ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if ($prodId <= 0 || $qty <= 0) {
            $error = 'لطفاً کالا و تعداد معتبر را وارد کنید.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND shop_id = ? AND deleted_at IS NULL");
                $stmt->execute([$prodId, $shopId]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod) throw new Exception('کالای انتخاب شده یافت نشد.');

                if ($unitCost > 0) {
                    $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?")->execute([$unitCost, $prodId]);
                } else {
                    $unitCost = (float)$prod['cost_price'];
                }

                record_inventory_tx($shopId, $prodId, 'inward', $qty, $unitCost, 'purchase_invoice', null, $notes . ($invoiceRef ? " (فاکتور: $invoiceRef)" : ''), (int)$user['id']);

                // Double-entry accounting: Debit Inventory Asset, Credit Cash/Bank
                $totalInwardCost = $qty * $unitCost;
                if ($totalInwardCost > 0) {
                    record_accounting_entry($shopId, 'manual_entry', null, $totalInwardCost, 0, 'inventory_asset', "ورود موجودی کالا «{$prod['title']}» تعداد $qty", (int)$user['id']);
                    record_accounting_entry($shopId, 'manual_entry', null, 0, $totalInwardCost, 'cash_bank', "پرداخت خرید کالا «{$prod['title']}» به ارزش " . format_irr($totalInwardCost), (int)$user['id']);
                }

                $pdo->commit();
                flash('success', "ورود تعداد $qty واحد از کالای «{$prod['title']}» با موفقیت در انبار ثبت گردید.");
                redirect('/inventory');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'خطا در ثبت ورود کالا: ' . $e->getMessage();
            }
        }
    }

    $products = $pdo->prepare("SELECT id, title, sku, cost_price, unit, stock_quantity FROM products WHERE shop_id = ? AND deleted_at IS NULL ORDER BY title ASC");
    $products->execute([$shopId]);
    $productList = $products->fetchAll(PDO::FETCH_ASSOC);

    layout_start('ثبت ورود کالا به انبار', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('plus', 18) ?></div>
            <div>
                <h1>ثبت ورود کالا به انبار (خرید / رسید انبار)</h1>
                <div class="page-sub">افزایش موجودی فیزیکی و ثبت سند بهای تمام‌شده کالا در حسابداری</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/inventory">بازگشت به انبار</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:680px">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>انتخاب کالا *</label>
                    <select class="select" name="product_id" id="prodSelect" required>
                        <option value="">-- انتخاب کالا --</option>
                        <?php foreach ($productList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-cost="<?= (float)$p['cost_price'] ?>" data-unit="<?= e($p['unit'] ?: 'عدد') ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['title']) ?> (موجودی فعلی: <?= en_to_fa_digits((string)(float)$p['stock_quantity']) ?> <?= e($p['unit'] ?: 'عدد') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>تعداد وارده به انبار *</label>
                        <input class="input" type="number" step="any" min="0.01" name="quantity" required placeholder="مثلاً 50">
                    </div>
                    <div class="form-group">
                        <label>قیمت خرید واحد (ریال)</label>
                        <input class="input" type="number" step="1" name="unit_cost" id="unitCostInput" placeholder="قیمت خرید هر واحد">
                    </div>
                </div>

                <div class="form-group">
                    <label>شماره فاکتور خرید / حواله تأمین‌کننده</label>
                    <input class="input" name="invoice_ref" placeholder="مثلاً فاکتور شماره ۱۴۰۳/۷۶۵">
                </div>

                <div class="form-group">
                    <label>یادداشت انبارداری</label>
                    <textarea class="textarea" name="notes" placeholder="توضیحات تکمیلی بابت محموله یا تأمین‌کننده..."></textarea>
                </div>

                <button class="btn btn-primary"><?= icon('check', 14) ?> ثبت رسید انبار و اعمال موجودی</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('prodSelect')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const cost = opt.getAttribute('data-cost');
        if (cost && document.getElementById('unitCostInput')) {
            document.getElementById('unitCostInput').value = cost;
        }
    });
    </script>
    <?php
    layout_end();
});

// Stock Adjustment (تعدیل موجودی انبار)
route('GET|POST', '/inventory/adjustment', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();
        $prodId = (int)($_POST['product_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $qty = (float)fa_to_en_digits($_POST['quantity'] ?? '0');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if ($prodId <= 0 || $qty <= 0 || !in_array($type, ['adjustment_plus', 'adjustment_minus', 'write_off', 'return_in'], true)) {
            $error = 'نوع تعدیل، کالا و مقدار معتبر را مشخص کنید.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND shop_id = ? AND deleted_at IS NULL");
                $stmt->execute([$prodId, $shopId]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod) throw new Exception('کالای مورد نظر پیدا نشد.');

                record_inventory_tx($shopId, $prodId, $type, $qty, (float)$prod['cost_price'], 'manual_adjustment', null, $notes, (int)$user['id']);

                $typeLabel = inv_tx_type_fa($type);
                $adjValue = $qty * (float)$prod['cost_price'];

                // Record accounting impact
                if ($adjValue > 0) {
                    if (in_array($type, ['adjustment_plus', 'return_in'], true)) {
                        record_accounting_entry($shopId, 'adjustment', null, $adjValue, 0, 'inventory_asset', "تعدیل افزایشی موجودی «{$prod['title']}»", (int)$user['id']);
                        record_accounting_entry($shopId, 'adjustment', null, 0, $adjValue, 'adjustment', "درآمد / سود تعدیل انبارداری «{$prod['title']}»", (int)$user['id']);
                    } else {
                        record_accounting_entry($shopId, 'adjustment', null, $adjValue, 0, 'cogs', "هزینه کسری انبار و ضایعات «{$prod['title']}»", (int)$user['id']);
                        record_accounting_entry($shopId, 'adjustment', null, 0, $adjValue, 'inventory_asset', "کاهش دارایی انبار بابت $typeLabel «{$prod['title']}»", (int)$user['id']);
                    }
                }

                $pdo->commit();
                flash('success', "سند تعدیل موجودی ({$typeLabel}) برای «{$prod['title']}» با موفقیت ثبت شد.");
                redirect('/inventory');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'خطا در ثبت تعدیل: ' . $e->getMessage();
            }
        }
    }

    $products = $pdo->prepare("SELECT id, title, unit, stock_quantity FROM products WHERE shop_id = ? AND deleted_at IS NULL ORDER BY title ASC");
    $products->execute([$shopId]);
    $productList = $products->fetchAll(PDO::FETCH_ASSOC);

    layout_start('تعدیل موجودی انبار', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('edit', 18) ?></div>
            <div>
                <h1>تعدیل موجودی انبار (کسری / مازاد / ضایعات)</h1>
                <div class="page-sub">ثبت اصلاحات انبارگردانی، ضایعات و مرجوعی‌ها با ثبت اتوماتیک در اسناد مالی</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/inventory">بازگشت به انبار</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:680px">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>کالا *</label>
                    <select class="select" name="product_id" required>
                        <option value="">-- انتخاب کالا --</option>
                        <?php foreach ($productList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['title']) ?> (موجودی فعلی: <?= en_to_fa_digits((string)(float)$p['stock_quantity']) ?> <?= e($p['unit'] ?: 'عدد') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>نوع عملیات تعدیل *</label>
                        <select class="select" name="type" required>
                            <option value="adjustment_minus">کسری انبارگردانی (کاهش موجودی)</option>
                            <option value="adjustment_plus">مازاد انبارگردانی (افزایش موجودی)</option>
                            <option value="write_off">ضایعات و خرابی (کاهش موجودی)</option>
                            <option value="return_in">مرجوعی به انبار (افزایش موجودی)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>مقدار تعدیل *</label>
                        <input class="input" type="number" step="any" min="0.01" name="quantity" required placeholder="تعداد واحد مورد نظر">
                    </div>
                </div>

                <div class="form-group">
                    <label>دلیل و توضیحات تعدیل *</label>
                    <textarea class="textarea" name="notes" required placeholder="علت مغایرت انبار یا گزارش ضایعات و کارشناس بازرسی..."></textarea>
                </div>

                <button class="btn btn-primary"><?= icon('check', 14) ?> اعمال تعدیل موجودی</button>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

// Cardex & Stock Movement Transactions
route('GET', '/inventory/transactions', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;

    $where = "WHERE it.shop_id = ?";
    $params = [$shopId];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_transactions it $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT it.*, p.title AS product_title, p.sku AS product_sku, p.unit AS product_unit, u.nickname AS user_name
        FROM inventory_transactions it
        JOIN products p ON p.id = it.product_id
        LEFT JOIN users u ON u.id = it.created_by_id
        $where
        ORDER BY it.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('گردش انبار و کاردکس کالاها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>کاردکس انبار و سوابق ورود و خروج</h1>
                <div class="page-sub">ردگیری کلیه تراکنش‌های انبار شامل خرید، سفارش مشتری، تعدیل و ضایعات</div>
            </div>
        </div>
        <a class="btn btn-outline" href="/inventory">بازگشت به انبار</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>تاریخ و زمان</th>
                        <th>نام کالا</th>
                        <th>نوع عملیات</th>
                        <th>تعداد</th>
                        <th>بهای تمام‌شده واحد</th>
                        <th>مبلغ کل</th>
                        <th>کاربر ثبت‌کننده</th>
                        <th>توضیحات و مرجع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9"><?= empty_state('هیچ تراکنش انباری ثبت نشده است') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php $isPlus = in_array($r['type'], ['inward', 'adjustment_plus', 'return_in'], true); ?>
                            <tr>
                                <td><?= en_to_fa_digits((string)$r['id']) ?></td>
                                <td class="date-cell"><?= format_jalali($r['created_at']) ?></td>
                                <td><strong><?= e($r['product_title']) ?></strong> (<?= e($r['product_sku'] ?: '—') ?>)</td>
                                <td>
                                    <span class="badge badge-<?= $isPlus ? 'emerald' : 'rose' ?>">
                                        <?= inv_tx_type_fa($r['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:<?= $isPlus ? '#15803d' : '#dc2626' ?>;">
                                        <?= $isPlus ? '+' : '-' ?><?= en_to_fa_digits((string)(float)$r['quantity']) ?>
                                    </strong>
                                    <?= e($r['product_unit'] ?: 'عدد') ?>
                                </td>
                                <td><?= format_irr((float)$r['unit_cost']) ?></td>
                                <td><?= format_irr((float)$r['unit_cost'] * (float)$r['quantity']) ?></td>
                                <td><?= e($r['user_name'] ?: 'سیستم') ?></td>
                                <td><?= e($r['notes'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/inventory/transactions', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

// Printable Inventory Count Sheet (برگه چاپی انبارگردانی)
route('GET', '/inventory/count-sheet', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    $shopId = active_shop_id();
    $shop = current_shop();

    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.shop_id = ? AND p.deleted_at IS NULL
        ORDER BY c.name ASC, p.title ASC
    ");
    $stmt->execute([$shopId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>برگه انبارگردانی - <?= e($shop['name'] ?? 'فروشگاه') ?></title>
        <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
        <style>
            @page { size: A4; margin: 15mm; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #111; font-size: 11pt; background: #fff; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
            .header h1 { font-size: 16pt; margin-bottom: 4px; }
            .meta { display: flex; justify-content: space-between; font-size: 10pt; color: #333; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #333; padding: 6px 8px; text-align: right; }
            th { background: #f3f4f6; font-weight: bold; font-size: 10pt; }
            .count-col { width: 90px; }
            .sig-box { margin-top: 40px; display: flex; justify-content: space-between; padding: 0 40px; }
            .no-print-btn { padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 10pt; margin-bottom: 16px; }
            @media print { .no-print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="no-print-btn" onclick="window.print()">چاپ برگه انبارگردانی (Ctrl+P)</button>
        <div class="header">
            <h1>برگه رسمی شمارش موجودی و انبارگردانی</h1>
            <div><?= e($shop['name'] ?? 'فروشگاه') ?></div>
        </div>
        <div class="meta">
            <div>تاریخ تنظیم: <?= en_to_fa_digits(jalali_today(true)) ?></div>
            <div>مسئول شمارش انبار: <?= e($user['nickname']) ?></div>
            <div>تعداد اقلام: <?= en_to_fa_digits((string)count($products)) ?> قلم کالا</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:30px;">ردیف</th>
                    <th>کد کالا (SKU)</th>
                    <th>بارکد</th>
                    <th>نام کالا</th>
                    <th>دسته‌بندی</th>
                    <th>واحد</th>
                    <th>موجودی سیستمی</th>
                    <th class="count-col">شمارش اول</th>
                    <th class="count-col">شمارش دوم (مغایرت)</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($products as $p): ?>
                    <tr>
                        <td><?= en_to_fa_digits((string)$i++) ?></td>
                        <td><?= e($p['sku'] ?: '—') ?></td>
                        <td><?= e($p['barcode'] ?: '—') ?></td>
                        <td><strong><?= e($p['title']) ?></strong></td>
                        <td><?= e($p['category_name'] ?: 'عمومی') ?></td>
                        <td><?= e($p['unit'] ?: 'عدد') ?></td>
                        <td><?= en_to_fa_digits((string)(float)$p['stock_quantity']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sig-box">
            <div>امضای انباردار:<br><br>____________________</div>
            <div>امضای سرپرست انبارگردانی:<br><br>____________________</div>
            <div>امضای مدیریت فروشگاه:<br><br>____________________</div>
        </div>
    </body>
    </html>
    <?php
});
