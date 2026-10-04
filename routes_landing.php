<?php
declare(strict_types=1);

/**
 * routes_landing.php
 * Public Recruiting Landing Page, Platform Policies & Error Handlers
 */

// 1. Root Recruiting Landing Page
route('GET', '/', [], function () use ($pdo) {
    $user = current_user();

    // Fetch active subscription plans for pricing table
    $plans = [];
    try {
        $plans = $pdo->query("SELECT * FROM subscription_plans WHERE active = 1 ORDER BY sort_order ASC, price_monthly ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    layout_public_start('پلتفرم جامع فروشگاه‌ساز و مدیریت شعب آنلاین', null, $user);
    ?>
    <!-- HERO SECTION -->
    <div style="background:linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%); border-radius:24px; padding:56px 36px; color:#fff; text-align:center; margin-bottom:48px; box-shadow:0 10px 30px rgba(37,99,235,0.15);">
        <div style="max-width:820px; margin:0 auto;">
            <div style="display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.15); backdrop-filter:blur(8px); padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:bold; margin-bottom:20px; border:1px solid rgba(255,255,255,0.2);">
                ✨ ویژه فروشگاه‌های اینستاگرامی و کسب‌وکارهای دارای چند شعبه
            </div>
            <h1 style="font-size:2.3rem; font-weight:900; line-height:1.4; margin-bottom:18px; color:#ffffff;">
                کسب‌وکار اجتماعی خود را به یک <span style="color:#60a5fa;">فروشگاه آنلاین هوشمند</span> تبدیل کنید
            </h1>
            <p style="font-size:1.05rem; line-height:1.9; color:#cbd5e1; margin-bottom:32px;">
                بدون درگیری با مالیات و کارمزدهای سنگین درگاه‌های بانکی، سفارش‌های دایرکت را ساماندهی کنید. دریافت مستقیم وجه با کارت‌به‌کارت، بارگذاری فیش، سیستم خودکار رزرو، و مدیریت هماهنگ شعب و انبارها.
            </p>
            <div style="display:flex; justify-content:center; gap:14px; flex-wrap:wrap;">
                <a href="/register" class="btn btn-primary" style="background:#22c55e; border-color:#22c55e; font-size:1rem; padding:12px 28px; font-weight:800;">
                    <?= icon('store', 16) ?> راه‌اندازی فروشگاه (رایگان)
                </a>
                <a href="/shops" class="btn btn-outline" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.3); font-size:1rem; padding:12px 24px;">
                    <?= icon('search', 16) ?> مشاهده فروشگاه‌های نمونه
                </a>
            </div>
        </div>
    </div>

    <!-- VALUE PROPOSITIONS -->
    <div style="margin-bottom:56px;">
        <div style="text-align:center; margin-bottom:32px;">
            <h2 style="font-size:1.5rem; font-weight:800; color:#0f172a; margin-bottom:8px;">چرا فروشندگان ایرانی بفروش را انتخاب می‌کنند؟</h2>
            <p style="color:#64748b; font-size:0.95rem;">امکاناتی فراتر از یک ویترین معمولی، طراحی‌شده برای نیازهای بومی کسب‌وکارهای شبکه‌های اجتماعی</p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
            <div class="card" style="padding:24px; border-radius:16px; border-top:4px solid #3b82f6;">
                <div style="width:44px; height:44px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <?= icon('report', 20) ?>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin-bottom:10px;">واریز مستقیم کارت‌به‌کارت</h3>
                <p style="font-size:0.9rem; color:#475569; line-height:1.8;">
                    وجه فاکتور مستقیماً به شماره کارت رسمی خود شما واریز می‌شود. خریدار فیش واریز را بارگذاری می‌کند و شما پس از راستی‌آزمایی سفارش را تایید می‌کنید.
                </p>
            </div>

            <div class="card" style="padding:24px; border-radius:16px; border-top:4px solid #10b981;">
                <div style="width:44px; height:44px; border-radius:10px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <?= icon('store', 20) ?>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin-bottom:10px;">مدیریت جامع شعب و انبارها</h3>
                <p style="font-size:0.9rem; color:#475569; line-height:1.8;">
                    شعبه‌های متعدد کسب‌وکار خود را به صورت مستقل یا یکپارچه اداره کنید. تخصیص مدیر برای هر شعبه، کاتالوگ مجزا یا متمرکز و ارسال تجمیعی.
                </p>
            </div>

            <div class="card" style="padding:24px; border-radius:16px; border-top:4px solid #f59e0b;">
                <div style="width:44px; height:44px; border-radius:10px; background:#fffbeb; color:#d97706; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <?= icon('orders', 20) ?>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin-bottom:10px;">رزرو هوشمند موجودی</h3>
                <p style="font-size:0.9rem; color:#475569; line-height:1.8;">
                    کالاها پس از ثبت سفارش به صورت خودکار تا چند روز در وضعیت رزرو قرار می‌گیرند تا خریدار فرصت پرداخت داشته باشد، بدون اینکه موجودی دوبار فروخته شود.
                </p>
            </div>

            <div class="card" style="padding:24px; border-radius:16px; border-top:4px solid #8b5cf6;">
                <div style="width:44px; height:44px; border-radius:10px; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <?= icon('dashboard', 20) ?>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin-bottom:10px;">گزارش‌های شفاف سود و فروش</h3>
                <p style="font-size:0.9rem; color:#475569; line-height:1.8;">
                    داشبورد تفکیک‌شده برای صاحب کسب‌وکار و مدیران شعب. مشاهده کالاهای پرفروش، مانده حساب، بدهکاران و آمار مرسولات به تفکیک استان و شهر.
                </p>
            </div>
        </div>
    </div>

    <!-- PRICING TIERS -->
    <?php if (!empty($plans)): ?>
        <div id="pricing" style="margin-bottom:60px;">
            <div style="text-align:center; margin-bottom:32px;">
                <h2 style="font-size:1.5rem; font-weight:800; color:#0f172a; margin-bottom:8px;">تعرفه‌ها و پلن‌های عضویت</h2>
                <p style="color:#64748b; font-size:0.95rem;">پلن متناسب با حجم فروش کسب‌وکار خود را انتخاب کرده و هر زمان تمایل داشتید ارتقا دهید</p>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px;">
                <?php foreach ($plans as $p): 
                    $isPopular = ($p['code'] === 'pro');
                ?>
                    <div class="card" style="padding:24px; border-radius:16px; display:flex; flex-direction:column; justify-content:space-between; position:relative; <?= $isPopular ? 'border:2px solid #2563eb; box-shadow:0 10px 25px rgba(37,99,235,0.1);' : '' ?>">
                        <?php if ($isPopular): ?>
                            <span class="badge badge-blue" style="position:absolute; top:-12px; right:20px; font-weight:bold;">پیشنهاد ویژه</span>
                        <?php endif; ?>
                        <div>
                            <h3 style="font-size:1.2rem; font-weight:800; color:#0f172a; margin-bottom:6px;"><?= e($p['name']) ?></h3>
                            <div style="font-size:1.4rem; font-weight:900; color:#2563eb; margin-bottom:14px;">
                                <?= (float)$p['price_monthly'] > 0 ? format_irt((float)$p['price_monthly']) . ' <span style="font-size:0.8rem; font-weight:normal; color:#64748b;">/ ماهانه</span>' : 'رایگان' ?>
                            </div>
                            <ul style="list-style:none; padding:0; margin:0 0 20px 0; font-size:0.86rem; color:#475569; line-height:2.2;">
                                <li>✓ حداکثر <strong><?= (int)$p['max_branches'] === 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_branches']) ?></strong> شعبه</li>
                                <li>✓ حداکثر <strong><?= (int)$p['max_managers'] === 999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_managers']) ?></strong> مدیر شعبه</li>
                                <li>✓ حداکثر <strong><?= (int)$p['max_products'] === 9999 ? 'نامحدود' : en_to_fa_digits((string)$p['max_products']) ?></strong> کالا</li>
                                <li><?= !empty($p['allow_custom_domain']) ? '✓ اتصال دامنه اختصاصی' : '✕ دامنه اختصاصی' ?></li>
                                <li><?= !empty($p['allow_sms_alerts']) ? '✓ اطلاع‌رسانی پیامکی' : '✕ پیامک خودکار' ?></li>
                            </ul>
                        </div>
                        <a href="/register" class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?>" style="width:100%; text-align:center;">
                            انتخاب پلن
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- BOTTOM CTA -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:20px; padding:36px; text-align:center;">
        <h3 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin-bottom:10px;">آماده رشد فروشگاه خود هستید؟</h3>
        <p style="color:#64748b; font-size:0.95rem; margin-bottom:20px;">همین حالا در کمتر از ۱ دقیقه ثبت‌نام کنید و اولین شعبه آنلاین خود را ایجاد نمایید.</p>
        <a href="/register" class="btn btn-primary" style="padding:10px 28px; font-size:0.95rem;">ثبت‌نام رایگان کسب‌وکار</a>
    </div>
    <?php
    layout_public_end(null);
});

// 2. Platform Policies Page
route('GET', '/policies(?:\.php)?', [], function () {
    $user = current_user();
    layout_public_start('قوانین، شرایط و رویه‌های پلتفرم بفروش', null, $user);
    ?>
    <div class="card" style="max-width:900px; margin:0 auto; padding:36px; border-radius:18px;">
        <h1 style="font-size:1.6rem; font-weight:900; color:#0f172a; margin-bottom:8px;">قوانین و رویه‌های پلتفرم بفروش</h1>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:28px;">آخرین به‌روزرسانی: مهر ۱۴۰۵ | با استفاده از بفروش، شرایط زیر را می‌پذیرید.</p>

        <section style="margin-bottom:28px;">
            <h2 style="font-size:1.15rem; font-weight:800; color:#1e3a8a; margin-bottom:10px;">۱. تعهدات فروشندگان و صاحبان کسب‌وکار</h2>
            <p style="line-height:2; color:#334155; font-size:0.92rem;">
                فروشگاه‌ها موظف هستند اطلاعات کالاها، قیمت‌ها و موجودی شعب خود را به طور دقیق و به‌روز ثبت نمایند. پس از واریز خریدار و تایید رسید کارت‌به‌کارت، فروشنده متعهد به آماده‌سازی و ارسال مرسوله طبق زمان‌بندی اعلام‌شده است. مسئولیت حقوقی اصالت و کیفیت کالا بر عهده فروشنده مربوطه می‌باشد.
            </p>
        </section>

        <section style="margin-bottom:28px;">
            <h2 style="font-size:1.15rem; font-weight:800; color:#1e3a8a; margin-bottom:10px;">۲. راهنمای پرداخت کارت‌به‌کارت و بارگذاری رسید</h2>
            <p style="line-height:2; color:#334155; font-size:0.92rem;">
                خریداران گرامی موظف هستند مبلغ دقیق سفارش را صرفاً به شماره کارت‌های رسمی نمایش‌داده‌شده در فاکتور فروشگاه واریز نمایند. بارگذاری تصویر واضح فیش واریزی یا ثبت شماره پیگیری و ساعت واریز در فرم سفارش الزامی است. اقلام سفارش تا سقف مهلت رزرو (معمولاً ۴ روز کاری) برای مشتری محفوظ خواهد ماند.
            </p>
        </section>

        <section style="margin-bottom:28px;">
            <h2 style="font-size:1.15rem; font-weight:800; color:#1e3a8a; margin-bottom:10px;">۳. سیاست‌های شعب و لجستیک ارسال</h2>
            <p style="line-height:2; color:#334155; font-size:0.92rem;">
                در صورتی که کسب‌وکار دارای شعب مستقل باشد، هزینه ارسال و انبارداری به تفکیک هر شعبه محاسبه می‌شود، مگر اینکه شعبه‌ها در یک «گروه ارسال متمرکز» تعریف شده باشند که در آن صورت سفارش به صورت یکپارچه تجمیع و ارسال خواهد شد.
            </p>
        </section>

        <section style="margin-bottom:20px;">
            <h2 style="font-size:1.15rem; font-weight:800; color:#1e3a8a; margin-bottom:10px;">۴. حریم خصوصی کاربران</h2>
            <p style="line-height:2; color:#334155; font-size:0.92rem;">
                اطلاعات شخصی، نشانی پستی و شماره‌های تماس کاربران صرفاً جهت تحویل سفارش و پیگیری‌های پشتیبانی به کار رفته و به هیچ عنوان در اختیار مراجع تبلیغاتی ثالث قرار نخواهد گرفت.
            </p>
        </section>
    </div>
    <?php
    layout_public_end(null);
});

// 3. Centralized Error Page
route('GET', '/errors/([0-9]+)', [], function ($code) {
    $c = (int)$code;
    $titles = [
        400 => 'درخواست نامعتبر',
        401 => 'نیاز به ورود به حساب کاربری',
        403 => 'دسترسی غیرمجاز',
        404 => 'صفحه مورد نظر یافت نشد',
        500 => 'خطای داخلی سامانه'
    ];
    $title = $titles[$c] ?? 'خطا در بارگذاری صفحه';
    error_page($c, $title, 'آدرس مورد نظر با وضعیت ' . en_to_fa_digits((string)$c) . ' مواجه شده است.');
});
