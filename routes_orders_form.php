<?php
declare(strict_types=1);

/**
 * routes_orders_form.php
 * View renderer for multi-step order checkout form
 * Implements Apple-inspired Neutral Precision, 96h Urgency Banner, Iranian Bank Card Mockup & Drag-and-Drop Dropzone
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
    $reservationDays = (int)($shop['reservation_days'] ?: 4);
    $reservationHours = $reservationDays * 24;

    $productData = array_map(fn($p) => [
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'price' => (float)$p['price'],
        'unit' => $p['unit'] ?: 'عدد',
        'stock' => (float)$p['stock_quantity'],
        'max_order' => (float)$p['max_per_order'],
        'max_month' => (float)$p['max_per_month'],
        'image_path' => $p['image_path'] ?? null
    ], $products);

    layout_start('تکمیل سفارش و پرداخت — ' . $shop['name'], $user);
    ?>
    <div style="max-width:960px; margin:0 auto;">
        <!-- PAGE HEADER -->
        <div class="page-header" style="margin-bottom:20px;">
            <div class="page-title-wrap">
                <div class="page-icon"><?= icon('orders', 20) ?></div>
                <div>
                    <h1>تسویه حساب و پرداخت سفارش</h1>
                    <div class="page-sub">
                        فروشگاه: <strong><?= e($shop['name']) ?></strong> | تحویل‌گیرنده: <strong><?= e($customer['nickname']) ?></strong> (<span dir="ltr"><?= format_phone($customer['phone']) ?></span>)
                    </div>
                </div>
            </div>
            <a class="btn btn-outline btn-sm" href="/cart">بازگشت به سبد خرید</a>
        </div>

        <?php if ($error): ?>
            <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:14px 18px; border-radius:var(--radius); margin-bottom:20px; font-weight:700;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- 4-DAY INVENTORY RESERVATION URGENCY BANNER -->
        <div class="reservation-banner reservation-urgency-banner">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <strong style="color:#92400e; font-size:0.95rem;">رزرو اختصاصی کالا به مدت <?= en_to_fa_digits((string)$reservationHours) ?> ساعت (<?= en_to_fa_digits((string)$reservationDays) ?> روز)</strong>
                </div>
                <div style="font-size:0.84rem; color:#78350f; margin-top:4px;">
                    موجودی اقلام پس از ثبت سفارش در انبار قفل شده و تا پایان مهلت برای شما محفوظ می‌ماند.
                </div>
                <div class="reservation-progress">
                    <div id="reservationProgressBar" class="reservation-progress-bar" style="width: 100%;"></div>
                </div>
            </div>
            <div class="reservation-timer-pill" id="reservationTimerClock">
                <span>مهلت باقیمانده:</span>
                <span id="countdownDisplay" class="tabular-nums font-mono"><?= en_to_fa_digits((string)$reservationHours) ?>:۰۰:۰۰</span>
            </div>
        </div>

        <?php if (empty($addresses)): ?>
            <div class="card">
                <div class="card-body">
                    <?= empty_state('آدرسی برای تحویل ثبت نشده است', 'برای تکمیل و ارسال سفارش، لطفاً نشانی پستی تحویل‌گیرنده را ثبت نمایید.', 'location') ?>
                    <div class="mt-3" style="text-align:center;">
                        <a class="btn btn-primary" href="/profile/addresses/create">ثبت نشانی تحویل جدید</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form method="post" id="checkout-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="shop_id" value="<?= $shopId ?>">
                <input type="hidden" name="customer_id" value="<?= $customerId ?>">

                <!-- STEP 1: ADDRESS SELECTION -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h2>۱. انتخاب آدرس و هماهنگی تحویل مرسوله</h2>
                        <a class="btn btn-outline btn-sm" href="/profile/addresses/create" target="_blank">+ نشانی جدید</a>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:12px; margin-bottom:16px;">
                            <?php foreach ($addresses as $idx => $a): ?>
                                <label style="display:flex; gap:12px; align-items:flex-start; padding:14px; border:1px solid <?= $a['is_default'] ? 'var(--primary)' : 'var(--border)' ?>; border-radius:var(--radius-sm); background:<?= $a['is_default'] ? '#f0f7ff' : '#fff' ?>; cursor:pointer; transition:all 0.15s ease;">
                                    <input type="radio" name="address_id" value="<?= (int)$a['id'] ?>" <?= ($idx === 0 || $a['is_default']) ? 'checked' : '' ?> style="margin-top:4px;" required>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                            <strong style="color:var(--ink); font-size:0.9rem;"><?= e($a['state']) ?>، <?= e($a['city']) ?></strong>
                                            <?php if ($a['is_default']): ?>
                                                <span class="badge badge-blue" style="font-size:0.7rem;">پیش‌فرض</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:0.84rem; color:var(--slate); line-height:1.6; margin-bottom:4px;">
                                            <?= e($a['address']) ?>
                                        </div>
                                        <?php if (!empty($a['postal_code'])): ?>
                                            <div style="font-size:0.78rem; color:var(--muted); font-family:monospace;">
                                                کد پستی: <?= en_to_fa_digits($a['postal_code']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label>توضیحات و هماهنگی ارسال (اختیاری):</label>
                            <input class="input" type="text" name="description" placeholder="ساعت حضور، شماره تماس دوم یا هماهنگی ویژه تحویل...">
                        </div>
                    </div>
                </div>

                <!-- STEP 2: ORDER ITEMS REVIEW -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h2>۲. بازبینی اقلام و سرفصل‌های فاکتور</h2>
                        <button type="button" class="btn btn-outline btn-sm" id="add-line"><?= icon('plus', 13) ?> افزودن سطر کالا</button>
                    </div>
                    <div class="card-body">
                        <div id="order-lines" style="display:flex; flex-direction:column; gap:10px;"></div>

                        <!-- Calculation Summary Strip -->
                        <div style="background:var(--surface-muted); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; margin-top:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:0.92rem;">
                                <span style="color:var(--slate);">جمع مبلغ اقلام:</span>
                                <span id="order-subtotal" class="tabular-nums" style="font-weight:700; color:var(--ink);">۰ تومان</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:0.88rem; color:var(--muted);">
                                <span>هزینه پست پیشتاز سراسری:</span>
                                <span id="order-shipping" class="tabular-nums" data-cost="<?= (float)$shop['default_shipping_cost'] ?>" data-free="<?= (float)($shop['free_shipping_threshold'] ?? 0) ?>">
                                    <?= format_irt((float)$shop['default_shipping_cost']) ?>
                                </span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:12px;">
                                <span style="font-weight:800; font-size:1.05rem; color:var(--ink);">مبلغ قابل پرداخت:</span>
                                <span id="order-total" class="tabular-nums" style="font-size:1.35rem; font-weight:900; color:var(--emerald);">۰ تومان</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: CARD-TO-CARD PAYMENT DETAILS & BANK CARD MOCKUP -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h2>۳. اطلاعات پرداخت کارت‌به‌کارت</h2>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px; align-items:start;">
                            <!-- Bank Card Mockup Graphic -->
                            <?php
                            $primaryCard = !empty($activeCards) ? $activeCards[0] : [
                                'card_number' => $shop['card_number'] ?: '۶۰۳۷۹۹۷۵۰۰۰۰۰۰۰۰',
                                'card_holder' => $shop['card_holder'] ?: $shop['name'],
                                'bank_name' => $shop['bank_name'] ?: 'بانک ملی ایران',
                                'shaba_number' => $shop['shaba_number'] ?? null
                            ];
                            ?>
                            <div style="display:flex; flex-direction:column; align-items:center;">
                                <div class="bank-card-mockup" id="bankCardContainer">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div class="bank-card-chip"></div>
                                        <div style="font-size:0.9rem; font-weight:800; color:var(--ink);" id="bankNameDisplay">
                                            <?= e($primaryCard['bank_name'] ?: 'بانک عضو شتاب') ?>
                                        </div>
                                    </div>

                                    <div class="bank-card-number font-mono" id="cardNumberDisplay">
                                        <?= format_card_number($primaryCard['card_number']) ?>
                                    </div>

                                    <div class="bank-card-details">
                                        <div>
                                            <div style="font-size:0.72rem; color:var(--muted); margin-bottom:2px;">صاحب حساب:</div>
                                            <div class="bank-card-holder" id="cardHolderDisplay"><?= e($primaryCard['card_holder']) ?></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline" id="copyCardBtn" onclick="window.copyCardNumber()" style="background:#f2f2f7; color:var(--ink); border:1px solid var(--border-subtle); font-size:0.78rem;">
                                            کپی شماره کارت
                                        </button>
                                    </div>
                                </div>

                                <div style="display:<?= !empty($primaryCard['shaba_number']) ? 'flex' : 'none' ?>; align-items:center; gap:8px; margin-top:10px; font-size:0.8rem; color:var(--muted);" id="shabaWrap">
                                    <span>شماره شبا:</span>
                                    <code class="font-mono" id="shabaDisplay"><?= e($primaryCard['shaba_number'] ?? '') ?></code>
                                    <button type="button" class="btn btn-ghost btn-sm" id="copyShabaBtn" onclick="window.copyShabaNumber()" style="padding:2px 6px;">کپی</button>
                                </div>
                            </div>

                            <!-- Card Selector & Instructions -->
                            <div>
                                <?php if (count($activeCards) > 1): ?>
                                    <div class="form-group">
                                        <label>انتخاب شماره کارت مقصد:</label>
                                        <select class="select" name="payment_card_id" id="cardSelect" onchange="window.switchBankCard(this)">
                                            <?php foreach ($activeCards as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>" data-number="<?= e($c['card_number']) ?>" data-holder="<?= e($c['card_holder']) ?>" data-bank="<?= e($c['bank_name'] ?: 'بانک') ?>" data-shaba="<?= e($c['shaba_number'] ?? '') ?>">
                                                    <?= format_card_number($c['card_number']) ?> — <?= e($c['card_holder']) ?> (<?= e($c['bank_name'] ?: 'بانک') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php elseif (!empty($activeCards)): ?>
                                    <input type="hidden" name="payment_card_id" value="<?= (int)$activeCards[0]['id'] ?>">
                                <?php endif; ?>

                                <div style="background:var(--surface-muted); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px; font-size:0.84rem; color:var(--slate); line-height:1.8;">
                                    <strong style="color:var(--ink);">راهنمای واریز وجه:</strong>
                                    <ol style="margin-right:18px; margin-top:6px;">
                                        <li>مبلغ کل سفارش را با اپلیکیشن یا همراه بانک خود به شماره کارت فوق انتقال دهید.</li>
                                        <li>شماره پیگیری / کد ارجاع یا تصویر رسید تراکنش را در مرحله بعد بارگذاری فرمایید.</li>
                                        <li>در صورت تمایل، می‌توانید ثبت فاکتور را انجام داده و فیش را تا ۴ روز آینده بارگذاری کنید.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: RECEIPT SLIP UPLOAD DROPZONE -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h2>۴. بارگذاری فیش واریز یا شماره پیگیری بانکی</h2>
                        <span class="badge badge-muted">اختیاری (امکان بارگذاری تا ۴ روز پس از ثبت)</span>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; align-items:start;">
                            <!-- Dropzone File Upload -->
                            <div>
                                <label style="display:block; font-size:0.84rem; font-weight:700; color:var(--slate); margin-bottom:8px;">تصویر فیش یا رسید انتقال (عکس یا PDF):</label>
                                <div class="receipt-dropzone" id="receiptDropzone" onclick="document.getElementById('receiptFileInput').click()">
                                    <div style="font-weight:700; color:var(--ink); font-size:0.9rem; margin-bottom:4px;">تصویر فیش را اینجا رها کنید یا کلیک نمایید</div>
                                    <div style="font-size:0.78rem; color:var(--muted);">فرمت‌های مجاز: JPG, PNG, WEBP, PDF (حداکثر ۶ مگابایت)</div>
                                    <input type="file" name="receipt" id="receiptFileInput" accept="image/*,application/pdf" style="display:none;" onchange="window.previewReceipt(this)">
                                    <img id="receiptPreviewImg" class="dropzone-preview" alt="پیش‌نمایش رسید">
                                </div>
                            </div>

                            <!-- Reference Number Input -->
                            <div>
                                <div class="form-group">
                                    <label>شماره پیگیری / ارجاع / توضیحات تراکنش:</label>
                                    <input class="input font-mono" type="text" name="receipt_description" placeholder="مثال: ۱۲۳۴۵۶۷۸۹۰">
                                    <div style="font-size:0.78rem; color:var(--muted); margin-top:4px;">
                                        ثبت شماره ارجاع جهت تطبیق سریع‌تر توسط حسابداری فروشگاه توصیه می‌گردد.
                                    </div>
                                </div>

                                <div style="padding:12px 14px; background:#edf5ff; border:1px solid #d0e2ff; border-radius:var(--radius-sm); font-size:0.8rem; color:#0051a8; line-height:1.7;">
                                    <strong>اطلاعیه پیگیری:</strong> پس از ثبت نهایی، وضعیت سفارش در پنل شما قابل پیگیری بوده و به محض تأیید رسید توسط فروشگاه، فرآیند بسته‌بندی و صدور بارنامه پستی آغاز می‌گردد.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center;">
                        <a href="/cart" class="btn btn-outline">انصراف</a>
                        <button class="btn btn-primary btn-lg" type="submit" style="gap:8px;">
                            <?= icon('check', 16) ?> تأیید نهایی و ثبت سفارش
                        </button>
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
                    return new Intl.NumberFormat('fa-IR').format(Math.round(num)) + ' تومان';
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
                    row.style.cssText = 'display:grid; grid-template-columns: 2fr 100px 140px 40px; gap:8px; align-items:center; background:#fff; border:1px solid #e2e8f0; padding:10px 12px; border-radius:var(--radius-sm);';

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
                        <div style="font-size:0.9rem; font-weight:700; text-align:left; color:var(--ink);" class="line-total tabular-nums">
                            ${formatIrr(0)}
                        </div>
                        <div>
                            <button type="button" class="btn btn-ghost btn-sm remove-row" style="color:var(--crimson); font-size:1.1rem; padding:4px 8px;" title="حذف">&times;</button>
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
                    const localCart = window.BefrooshStore ? window.BefrooshStore.getCart().filter(i => !i.shop_id || Number(i.shop_id) === <?= $shopId ?>) : [];
                    if (localCart.length > 0) {
                        localCart.forEach(item => addRow(item.id, item.qty || 1));
                    } else {
                        addRow();
                    }
                }

                // 96-Hour Urgency Countdown Clock (Pure Tabular Persian Numerals)
                let totalSecs = <?= $reservationHours ?> * 3600;
                const clockEl = document.getElementById('countdownDisplay');
                const barEl = document.getElementById('reservationProgressBar');
                const toFaDigits = str => String(str).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
                const pad = n => (n < 10 ? '0' : '') + n;

                setInterval(function(){
                    if (totalSecs > 0) {
                        totalSecs--;
                        const hrs = Math.floor(totalSecs / 3600);
                        const mins = Math.floor((totalSecs % 3600) / 60);
                        const secs = totalSecs % 60;
                        if (clockEl) {
                            clockEl.textContent = toFaDigits(pad(hrs)) + ':' + toFaDigits(pad(mins)) + ':' + toFaDigits(pad(secs));
                        }
                        if (barEl) {
                            barEl.style.width = Math.max(5, (totalSecs / (<?= $reservationHours ?> * 3600)) * 100) + '%';
                        }
                    }
                }, 1000);
            });

            // Bank card copy button with Persian & English digits normalization
            window.copyCardNumber = function() {
                const numEl = document.getElementById('cardNumberDisplay');
                if (!numEl) return;
                const faDigits = '۰۱۲۳۴۵۶۷۸۹';
                const rawNum = numEl.textContent.trim().replace(/[۰-۹]/g, d => faDigits.indexOf(d)).replace(/[^0-9]/g, '');
                if (!rawNum) return;
                navigator.clipboard.writeText(rawNum).then(function(){
                    const btn = document.getElementById('copyCardBtn');
                    if (btn) {
                        const original = btn.textContent;
                        btn.textContent = 'کپی شد';
                        setTimeout(() => btn.textContent = original, 2000);
                    }
                    if (window.BefrooshStore) {
                        window.BefrooshStore.showToast('شماره ۱۶ رقمی کارت بانکی کپی شد.');
                    }
                });
            };

            // Dynamic Sheba IBAN copy
            window.copyShabaNumber = function() {
                const shabaEl = document.getElementById('shabaDisplay');
                if (!shabaEl || !shabaEl.textContent.trim()) return;
                navigator.clipboard.writeText(shabaEl.textContent.trim()).then(function(){
                    const btn = document.getElementById('copyShabaBtn');
                    if (btn) {
                        const original = btn.textContent;
                        btn.textContent = 'کپی شد';
                        setTimeout(() => btn.textContent = original, 2000);
                    }
                    if (window.BefrooshStore) {
                        window.BefrooshStore.showToast('شماره شبا کپی شد.');
                    }
                });
            };

            // Switch Bank Card in mockup
            window.switchBankCard = function(selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                if (!opt) return;
                const raw = opt.getAttribute('data-number') || '';
                const holder = opt.getAttribute('data-holder') || '';
                const bank = opt.getAttribute('data-bank') || '';
                const shaba = opt.getAttribute('data-shaba') || '';

                const faDigits = '۰۱۲۳۴۵۶۷۸۹';
                const normalized = raw.replace(/[۰-۹]/g, d => faDigits.indexOf(d)).replace(/[^0-9]/g, '');
                const formatted = normalized.replace(/(\d{4})/g, '$1 - ').replace(/ - $/, '');

                document.getElementById('cardNumberDisplay').textContent = formatted || raw;
                document.getElementById('cardHolderDisplay').textContent = holder;
                document.getElementById('bankNameDisplay').textContent = bank;

                const shabaEl = document.getElementById('shabaDisplay');
                const shabaWrap = document.getElementById('shabaWrap');
                if (shabaEl && shabaWrap) {
                    shabaEl.textContent = shaba;
                    shabaWrap.style.display = shaba ? 'flex' : 'none';
                }
            };

            // Receipt Slip preview with PDF and image handling
            window.previewReceipt = function(input) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const preview = document.getElementById('receiptPreviewImg');
                    if (!preview) return;

                    if (file.type === 'application/pdf') {
                        preview.src = '';
                        preview.style.display = 'none';
                        let pdfNote = document.getElementById('pdfNotice');
                        if (!pdfNote) {
                            pdfNote = document.createElement('div');
                            pdfNote.id = 'pdfNotice';
                            pdfNote.style.cssText = 'margin-top:10px; padding:8px 12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; font-size:0.82rem; color:#1e40af;';
                            preview.parentNode.appendChild(pdfNote);
                        }
                        pdfNote.textContent = 'فایل رسید انتخابی: ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                        pdfNote.style.display = 'block';
                    } else {
                        const pdfNote = document.getElementById('pdfNotice');
                        if (pdfNote) pdfNote.style.display = 'none';

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                }
            };

            // Drag and drop events for dropzone
            (function(){
                const dz = document.getElementById('receiptDropzone');
                if (!dz) return;
                ['dragenter', 'dragover'].forEach(name => {
                    dz.addEventListener(name, e => { e.preventDefault(); dz.classList.add('dragover'); }, false);
                });
                ['dragleave', 'drop'].forEach(name => {
                    dz.addEventListener(name, e => { e.preventDefault(); dz.classList.remove('dragover'); }, false);
                });
                dz.addEventListener('drop', e => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files.length > 0) {
                        const fileInput = document.getElementById('receiptFileInput');
                        fileInput.files = files;
                        window.previewReceipt(fileInput);
                    }
                }, false);
            })();
            </script>
        <?php endif; ?>
    </div>
    <?php
    layout_end();
}
