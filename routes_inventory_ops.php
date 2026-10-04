<?php
declare(strict_types=1);

/**
 * routes_inventory_ops.php
 * Stock Intake (Inward Entry) and Stock Adjustments (Physical count variance & write-offs)
 */

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
        $unitCost = clean_price_input($_POST['unit_cost'] ?? '0');
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
                        <label>قیمت خرید واحد (تومان)</label>
                        <input class="input price-input" type="text" inputmode="numeric" name="unit_cost" id="unitCostInput" placeholder="قیمت خرید هر واحد">
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
