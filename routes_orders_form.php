<?php
declare(strict_types=1);

/**
 * routes_orders_form.php
 * View renderer for multi-step order checkout form
 */

function render_order_create_form(
    array $user,
    array $shop,
    array $customer,
    array $addresses,
    array $activeCards,
    array $products,
    array $prefilledItems,
    string $error = ''
): void {
    $shopId = (int)$shop['id'];
    $customerId = (int)$customer['id'];
    $productData = array_map(fn($p) => [
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'price' => (float)$p['price'],
        'unit' => $p['unit'] ?: 'عدد',
        'stock' => (float)$p['stock_quantity'],
        'max_order' => (float)$p['max_per_order'],
        'max_month' => (float)$p['max_per_month']
    ], $products);

    layout_start('ثبت سفارش جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('orders', 18) ?></div>
            <div>
                <h1>ثبت سفارش — <?= e($shop['name']) ?></h1>
                <div class="page-sub">
                    مشتری: <strong><?= e($customer['nickname']) ?></strong> (<?= format_phone($customer['phone']) ?>)
                </div>
            </div>
        </div>
        <a class="btn btn-outline" href="/cart">بازگشت به سبد خرید</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($addresses)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('آدرسی برای تحویل ثبت نشده است', 'برای تکمیل سفارش، ابتدا باید حداقل یک نشانی معتبر ثبت فرمایید.', 'location') ?>
                <div class="mt-3" style="text-align:center;">
                    <a class="btn btn-primary" href="/account/addresses/create">افزودن نشانی جدید</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <form method="post" id="order-form">
            <?= csrf_field() ?>
            <input type="hidden" name="shop_id" value="<?= $shopId ?>">
            <input type="hidden" name="customer_id" value="<?= $customerId ?>">

            <!-- STEP 1: DELIVERY ADDRESS -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>۱. اطلاعات و آدرس تحویل سفارش</h2>
                    <a class="btn btn-outline btn-sm" href="/account/addresses/create" target="_blank">+ آدرس جدید</a>
                </div>
                <div class="card-body">
                    <div class="form-group mb-2">
                        <label>آدرس تحویل گیرنده *</label>
                        <select class="select" name="address_id" required>
                            <?php foreach ($addresses as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= $a['is_default'] ? 'selected' : '' ?>>
                                    <?= e($a['state']) ?>، <?= e($a['city']) ?> — <?= e($a['address']) ?><?= $a['is_default'] ? ' (پیش‌فرض)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>توضیحات و هماهنگی ارسال (اختیاری)</label>
                        <textarea class="textarea" name="description" placeholder="نکات ضروری در مورد تحویل مرسوله..."></textarea>
                    </div>
                </div>
            </div>

            <!-- STEP 2: ORDER ITEMS -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>۲. اقلام سفارش و تعداد</h2>
                    <button type="button" class="btn btn-outline btn-sm" id="add-line"><?= icon('plus', 13) ?> افزودن سطر کالا</button>
                </div>
                <div class="card-body">
                    <div id="order-lines" style="display:flex; flex-direction:column; gap:10px;"></div>

                    <div class="order-total-box mt-3" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:12px;">
                        <div style="display:flex; justify-content:space-between; width:100%;">
                            <span>جمع اقلام:</span>
                            <span id="order-subtotal"><?= format_irr(0) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; width:100%; font-size:0.85rem; color:#64748b; margin-top:4px;">
                            <span>هزینه ارسال پیشتاز:</span>
                            <span id="order-shipping" data-cost="<?= (float)$shop['default_shipping_cost'] ?>" data-free="<?= (float)($shop['free_shipping_threshold'] ?? 0) ?>">
                                <?= format_irr((float)$shop['default_shipping_cost']) ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; width:100%; font-size:1.05rem; font-weight:800; color:#1e3a8a; margin-top:8px; border-top:1px solid #e2e8f0; padding-top:6px;">
                            <span>مبلغ کل برآوردی:</span>
                            <span id="order-total"><?= format_irr(0) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: PAYMENT METHOD & SUBMIT -->
            <div class="card">
                <div class="card-header">
                    <h2>۳. اطلاعات پرداخت کارت‌به‌کارت</h2>
                </div>
                <div class="card-body">
                    <div style="margin-bottom:12px; font-size:0.85rem; color:#475569; line-height:1.6;">
                        روش پرداخت این فروشگاه <strong>کارت‌به‌کارت</strong> می‌باشد. پس از ثبت نهایی، اقلام به مدت <strong><?= en_to_fa_digits((string)($shop['reservation_days'] ?: 4)) ?> روز</strong> در انبار رزرو می‌ماند و می‌توانید فیش واریزی خود را بارگذاری فرمایید.
                    </div>

                    <?php if (!empty($activeCards)): ?>
                        <div class="form-group mb-2">
                            <label>شماره کارت جهت واریز:</label>
                            <select class="select" name="payment_card_id">
                                <?php foreach ($activeCards as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        <?= format_card_number($c['card_number']) ?> — به نام <?= e($c['card_holder']) ?> (<?= e($c['bank_name'] ?: 'بانک') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> تایید و ثبت نهایی سفارش</button>
                </div>
            </div>
        </form>

        <script id="products-data" type="application/json"><?= json_encode($productData, JSON_UNESCAPED_UNICODE) ?></script>
        <script id="items-data" type="application/json"><?= json_encode($prefilledItems, JSON_UNESCAPED_UNICODE) ?></script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const products = JSON.parse(document.getElementById('products-data').textContent || '[]');
            const prefilled = JSON.parse(document.getElementById('items-data').textContent || '[]');
            const linesContainer = document.getElementById('order-lines');
            const addBtn = document.getElementById('add-line');
            const subtotalEl = document.getElementById('order-subtotal');
            const shippingEl = document.getElementById('order-shipping');
            const totalEl = document.getElementById('order-total');

            const defaultShipping = parseFloat(shippingEl.getAttribute('data-cost') || 0);
            const freeThreshold = parseFloat(shippingEl.getAttribute('data-free') || 0);

            function formatIrr(num) {
                return new Intl.NumberFormat('fa-IR').format(Math.round(num)) + ' ریال';
            }

            function recalc() {
                let subtotal = 0;
                document.querySelectorAll('.order-row').forEach(row => {
                    const sel = row.querySelector('.product-select');
                    const qtyInput = row.querySelector('.qty-input');
                    const pId = parseInt(sel.value);
                    const qty = parseFloat(qtyInput.value) || 0;
                    const prod = products.find(p => p.id === pId);
                    if (prod && qty > 0) {
                        const lineTotal = prod.price * qty;
                        subtotal += lineTotal;
                        row.querySelector('.line-total').textContent = formatIrr(lineTotal);
                    } else {
                        row.querySelector('.line-total').textContent = formatIrr(0);
                    }
                });

                const shipping = (freeThreshold > 0 && subtotal >= freeThreshold) ? 0 : defaultShipping;
                const total = subtotal + shipping;

                subtotalEl.textContent = formatIrr(subtotal);
                shippingEl.textContent = formatIrr(shipping);
                totalEl.textContent = formatIrr(total);
            }

            function addRow(productId = 0, quantity = 1) {
                const row = document.createElement('div');
                row.className = 'order-row';
                row.style.cssText = 'display:grid; grid-template-columns: 2fr 100px 140px 40px; gap:8px; align-items:center; background:#fff; border:1px solid #e2e8f0; padding:8px 12px; border-radius:6px;';

                let options = '<option value="">-- انتخاب کالا --</option>';
                products.forEach(p => {
                    const sel = (p.id === productId) ? 'selected' : '';
                    options += `<option value="${p.id}" ${sel}>${p.title} (${formatIrr(p.price)})</option>`;
                });

                row.innerHTML = `
                    <div>
                        <select class="select product-select" name="product_id[]" required style="width:100%;">
                            ${options}
                        </select>
                    </div>
                    <div>
                        <input class="input qty-input" type="number" name="quantity[]" min="1" step="any" value="${quantity}" required style="width:100%;">
                    </div>
                    <div style="font-size:0.85rem; font-weight:600; text-align:left; direction:ltr;" class="line-total">
                        ${formatIrr(0)}
                    </div>
                    <div>
                        <button type="button" class="btn btn-ghost btn-sm remove-row" style="color:#ef4444;" title="حذف">&times;</button>
                    </div>
                `;

                row.querySelector('.product-select').addEventListener('change', recalc);
                row.querySelector('.qty-input').addEventListener('input', recalc);
                row.querySelector('.remove-row').addEventListener('click', function() {
                    row.remove();
                    recalc();
                });

                linesContainer.appendChild(row);
                recalc();
            }

            if (addBtn) {
                addBtn.addEventListener('click', () => addRow());
            }

            if (prefilled.length > 0) {
                prefilled.forEach(item => addRow(item.product_id, item.quantity));
            } else {
                addRow();
            }
        });
        </script>
    <?php endif; ?>
    <?php
    layout_end();
}
