<?php
declare(strict_types=1);

/**
 * setup_seed.php
 * Comprehensive demo data seeder for Befroosh Platform
 */

function seed_platform_demo(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $now = date('Y-m-d H:i:s');
        $pwd = password_hash('123456', PASSWORD_DEFAULT);
        $pwdAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $pwdOwner = password_hash('owner123', PASSWORD_DEFAULT);
        $pwdManager = password_hash('manager123', PASSWORD_DEFAULT);

        // 1. Seed Shops
        $insShop = $pdo->prepare("
            INSERT INTO shops (id, name, slug, phone, email, address, national_id, economic_code, card_number, card_holder, bank_name, shaba_number, card_to_card_enabled, reservation_days, payment_methods, tax_rate, default_shipping_cost, free_shipping_threshold, policies_html, about_html, faq_json, active, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");

        $policiesShop1 = '<h3>قوانین و رویه‌های خرید و ارسال فروشگاه مرکزی</h3>
<p>کلیه سفارشات ظرف حداکثر ۲۴ ساعت کاری پس از تایید واریز کارت‌به‌کارت آماده‌سازی و به شرکت ملی پست تحویل داده می‌شوند.</p>
<h4>روش‌های پرداخت</h4>
<p>در این فروشگاه پرداخت تنها از طریق کارت‌به‌کارت به شماره حساب‌های رسمی مندرج در صفحه پرداخت امکان‌پذیر است. پس از واریز، بارگذاری تصویر خوانای فیش واریزی در بخش پیگیری سفارش الزامی است.</p>
<h4>مهلت رزرو موجودی</h4>
<p>موجودی کالاها به مدت ۴ روز پس از ثبت اولیه سفارش برای شما رزرو خواهد ماند و پس از انقضا به صورت خودکار لغو می‌گردد.</p>
<h4>ضمانت بازگشت کالا</h4>
<p>امکان مرجوعی کالا تا ۷ روز پس از دریافت در صورت عدم پلمپ کالا و تایید پشتیبانی وجود دارد.</p>';

        $aboutShop1 = '<h3>درباره فروشگاه مرکزی بَفروش</h3>
<p>فروشگاه مرکزی بفروش از سال ۱۳۹۸ به عنوان پیشگام در ارائه ملزومات اداری، تحریر، و لوازم الکترونیکی با کیفیت در ایران فعالیت می‌کند.</p>
<p>هدف ما ارائه شفاف‌ترین تجربه خرید با تضمین اصالت و بهترین قیمت رقابتی است. تیم پشتیبانی ما همه روزه آماده پاسخگویی به سوالات شماست.</p>';

        $faqShop1 = json_encode([
            ['q' => 'چگونه هزینه سفارش را پرداخت کنم؟', 'a' => 'پس از ثبت سفارش، مبلغ فاکتور را به یکی از شماره کارت‌های فروشگاه واریز نموده و فیش را در سامانه بارگذاری نمایید.'],
            ['q' => 'چقدر زمان برای پرداخت فیش دارم؟', 'a' => 'موجودی کالای شما تا ۴ روز پس از ثبت سفارش رزرو می‌ماند.'],
            ['q' => 'کد رهگیری پستی را از کجا دریافت کنم؟', 'a' => 'پس از ارسال مرسوله، کد رهگیری ۲۴ رقمی پست در پنل کاربری و بخش پیگیری عمومی سفارش ثبت می‌شود.']
        ], JSON_UNESCAPED_UNICODE);

        $insShop->execute([
            1, 'فروشگاه مرکزی بَفروش', 'central', '02188888888', 'info@befroosh.ir', 'تهران، خیابان آزادی، پلاک ۱',
            '10101234567', '411122334455', '6037997123456789', 'فروشگاه مرکزی بَفروش', 'بانک ملی ایران', 'IR120170000000101234567890',
            1, 4, '["card_to_card"]', 0.10, 250000, 2500000, $policiesShop1, $aboutShop1, $faqShop1,
            'فروشگاه اصلی و مرجع انواع کالاهای دیجیتال و لوازم اداری با گارانتی اصالت کالا.', $now, $now
        ]);

        $policiesShop2 = '<h3>قوانین و رویه‌های بوتیک ترنج</h3>
<p>تمامی پوشاک و اکسسوری‌ها پیش از بسته‌بندی توسط تیم کنترل کیفی بازبینی می‌شوند.</p>
<h4>شرایط تعویض سایز</h4>
<p>در صورت نیاز به تعویض سایز تا ۴۸ ساعت پس از دریافت مرسوله با پشتیبانی تماس حاصل فرمایید.</p>';

        $aboutShop2 = '<h3>درباره بوتیک و مد ترنج</h3>
<p>ترنج ارائه‌دهنده جدیدترین ترندهای پوشاک فصلی، کفش و اکسسوری‌های مرغوب با دوخت صنعتی و الیاف طبیعی است.</p>';

        $faqShop2 = json_encode([
            ['q' => 'آیا امکان پرو وجود دارد؟', 'a' => 'در خرید آنلاین، جدول اندازه‌گیری دقیق در صفحه هر محصول درج شده است.'],
            ['q' => 'ارسال با چه روشی انجام می‌شود؟', 'a' => 'ارسال کلیه مرسولات با پست پیشتاز سراسری انجام می‌شود.']
        ], JSON_UNESCAPED_UNICODE);

        $insShop->execute([
            2, 'بوتیک و مد ترنج', 'toranj', '02122223333', 'support@toranj-mode.ir', 'تهران، میدان ونک، مجتمع ونک، واحد ۱۰۴',
            '10109876543', '411188776655', '5022291087654321', 'سارا رهنما (ترنج)', 'بانک پاسارگاد', 'IR980570000000208765432100',
            1, 3, '["card_to_card"]', 0.05, 200000, 1500000, $policiesShop2, $aboutShop2, $faqShop2,
            'عرضه‌کننده جدیدترین مدل‌های پوشاک فصلی، کفش و اکسسوری زنانه و مردانه با کیفیت عالی.', $now, $now
        ]);

        // 2. Seed Bank Cards
        $insCard = $pdo->prepare("
            INSERT INTO shop_bank_cards (shop_id, card_number, card_holder, bank_name, shaba_number, active, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, '1406/12/29', ?, ?)
        ");
        $insCard->execute([1, '6037997123456789', 'فروشگاه مرکزی بَفروش', 'بانک ملی ایران', 'IR120170000000101234567890', $now, $now]);
        $insCard->execute([1, '5892101234567890', 'فروشگاه مرکزی (حساب دوم)', 'بانک سپه', 'IR850150000000301234567890', $now, $now]);
        $insCard->execute([2, '5022291087654321', 'سارا رهنما (ترنج)', 'بانک پاسارگاد', 'IR980570000000208765432100', $now, $now]);

        // 3. Seed Categories
        $insCat = $pdo->prepare("INSERT INTO categories (shop_id, name, slug, sort_order, active, created_at) VALUES (?, ?, ?, ?, 1, ?)");
        $cats1 = [['کالای دیجیتال', 'digital', 1], ['لوازم اداری و مصرفی', 'office', 2], ['کاغذ و بایگانی', 'paper-archive', 3]];
        foreach ($cats1 as $c) { $insCat->execute([1, $c[0], $c[1], $c[2], $now]); }
        $cats2 = [['پوشاک مردانه', 'men-clothing', 1], ['پوشاک زنانه', 'women-clothing', 2], ['کیف و کفش', 'shoes-bags', 3]];
        foreach ($cats2 as $c) { $insCat->execute([2, $c[0], $c[1], $c[2], $now]); }

        // 4. Seed Users
        $insUser = $pdo->prepare("
            INSERT INTO users (username, password, nickname, first_name, last_name, role, shop_id, phone, national_code, notes, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");

        // Superadmins
        $insUser->execute(['superadmin', $pwd, 'مدیر ارشد سامانه', 'مدیر', 'ارشد', 'superadmin', null, '9120000001', '0010000001', 'دسترسی ریشه سامانه', $now, $now]);
        $insUser->execute(['superadmin2', $pwd, 'ناظر کل سامانه', 'ناظر', 'کل', 'superadmin', null, '9120000002', '0010000002', 'نظارت کلی', $now, $now]);
        $insUser->execute(['admin', $pwdAdmin, 'مدیر کل بَفروش', 'مدیر', 'کل', 'superadmin', null, '9120000003', '0010000003', 'حساب پیش‌فرض مدیریت پلتفرم', $now, $now]);

        // Shop 1 Staff (admin1 is shop_owner, admin2 is shop_manager)
        $insUser->execute(['admin1', $pwd, 'مهدی توکلی (مالک مرکزی)', 'مهدی', 'توکلی', 'shop_owner', 1, '9122000001', '0020000001', 'مالک فروشگاه مرکزی', $now, $now]);
        $owner1Id = (int)$pdo->lastInsertId();
        $insUser->execute(['admin2', $pwd, 'نسترن رحیمی (مدیر مرکزی)', 'نسترن', 'رحیمی', 'shop_manager', 1, '9122000002', '0020000002', 'مدیر عملیات مرکزی', $now, $now]);
        $insUser->execute(['owner_tech', $pwdOwner, 'کاوه احمدی (مالک دوم)', 'کاوه', 'احمدی', 'shop_owner', 1, '9123000001', '0020000003', 'حساب تست مالک', $now, $now]);
        $insUser->execute(['manager_tech', $pwdManager, 'پگاه سالاری (مدیر سفارشات)', 'پگاه', 'سالاری', 'shop_manager', 1, '9123000002', '0020000004', 'حساب تست مدیر', $now, $now]);
        $insUser->execute(['admin5', $pwd, 'حمید طاهری (انباردار)', 'حمید', 'طاهری', 'shop_manager', 1, '9122000005', '0020000005', 'مسئول انبار', $now, $now]);

        // Shop 2 Staff (admin3 is shop_owner, admin4 is shop_manager)
        $insUser->execute(['admin3', $pwd, 'سارا رهنما (مالک ترنج)', 'سارا', 'رهنما', 'shop_owner', 2, '9122000003', '0020000006', 'مالک بوتیک ترنج', $now, $now]);
        $owner2Id = (int)$pdo->lastInsertId();
        $insUser->execute(['admin4', $pwd, 'بابک نظری (مدیر ترنج)', 'بابک', 'نظری', 'shop_manager', 2, '9122000004', '0020000007', 'مدیر بوتیک ترنج', $now, $now]);
        $insUser->execute(['owner_toranj', $pwdOwner, 'مینا ترنجی (مالک ۲)', 'مینا', 'ترنجی', 'shop_owner', 2, '9123000003', '0020000008', 'حساب تست مالک ترنج', $now, $now]);

        // Update shops owner_id
        $pdo->prepare("UPDATE shops SET owner_id = ? WHERE id = 1")->execute([$owner1Id]);
        $pdo->prepare("UPDATE shops SET owner_id = ? WHERE id = 2")->execute([$owner2Id]);

        // Customers
        $customerNames = [
            ['امیر', 'رضایی'], ['مهدی', 'کریمی'], ['سارا', 'محمدی'], ['نگار', 'حسینی'],
            ['رضا', 'موسوی'], ['زهرا', 'جعفری'], ['حسین', 'صادقی'], ['مریم', 'نوری'],
            ['علی', 'قاسمی'], ['نرگس', 'شریفی'], ['فاطمه', 'عباسی'], ['محمد', 'فرهادی'],
            ['الهام', 'نجفی'], ['پویا', 'طاهری'], ['شیرین', 'صفری'], ['آرش', 'ملکی']
        ];

        $customerIds = [];
        foreach ($customerNames as $idx => $cn) {
            $num = $idx + 1;
            $uName = 'customer' . $num;
            $uPwd = ($num === 1) ? password_hash('customer123', PASSWORD_DEFAULT) : $pwd;
            $phone = '912100' . sprintf('%04d', $num);
            $natCode = '003' . sprintf('%07d', $num);
            $insUser->execute([$uName, $uPwd, $cn[0] . ' ' . $cn[1], $cn[0], $cn[1], 'customer', null, $phone, $natCode, 'مشتری دائمی', $now, $now]);
            $customerIds[] = (int)$pdo->lastInsertId();
        }

        // 5. Seed Customer Addresses
        $insAddr = $pdo->prepare("
            INSERT INTO addresses (user_id, state, city, address, postal_code, description, is_default, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($customerIds as $cId) {
            $insAddr->execute([$cId, 'تهران', 'تهران', 'خیابان ولیعصر، بالاتر از میدان ونک، کوچه لاله، پلاک ۲۴، واحد ۳', '1987654321', 'تحویل به نگهبانی مجتمع', 1, $now, $now]);
            $insAddr->execute([$cId, 'البرز', 'کرج', 'بلوار طالقانی شمالی، خیابان بهار، پلاک ۱۲', '3145678901', 'زنگ طبقه دوم', 0, $now, $now]);
        }

        // 6. Seed Products
        $insProd = $pdo->prepare("
            INSERT INTO products (shop_id, category_id, title, slug, description, price, cost_price, stock_quantity, reserved_quantity, min_stock_alert, min_order_qty, sku, barcode, unit, weight_grams, tax_rate, max_per_order, max_per_month, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 5, 1, ?, ?, ?, ?, ?, 15, 100, 1, ?, ?)
        ");

        $shop1Products = [
            [1, 2, 'خودکار آبی پنتر', 'خودکار-آبی-پنتر', 'خودکار روان با جوهر آبی مقاوم و مناسب استفاده اداری.', 120000, 85000, 150, 'SKU-001', '626000100001', 'عدد', 15, 0.1],
            [1, 2, 'خودکار مشکی فابرکاستل', 'خودکار-مشکی-فابرکاستل', 'خودکار با کیفیت آلمانی مناسب نگارش روزمره و اسناد رسمی.', 140000, 98000, 120, 'SKU-002', '626000100002', 'عدد', 15, 0.1],
            [1, 3, 'بسته کاغذ A4 کپی‌مکس ۵۰۰ برگی', 'بسته-کاغذ-a4-کپی-مکس', 'بسته ۵۰۰ برگی کاغذ تحریر سفید ۸۰ گرمی مناسب چاپگر و دستگاه کپی.', 850000, 680000, 80, 'SKU-003', '626000100003', 'بسته', 2400, 0.1],
            [1, 3, 'زونکن اداری ۸ سانتی‌متری', 'زونکن-اداری-8-سانتی', 'زونکن فلزی بادوام مناسب بایگانی پوشه‌ها و اسناد حسابداری.', 320000, 220000, 60, 'SKU-004', '626000100004', 'عدد', 450, 0.1],
            [1, 1, 'فلش مموری ۳۲ گیگابایت سن‌دیسک', 'فلش-مموری-32-گیگ-سندیسک', 'فلش مموری پرسرعت USB 3.0 با بدنه مقاوم در برابر ضربه و آب.', 2450000, 1950000, 45, 'SKU-005', '626000100005', 'عدد', 20, 0.1],
            [1, 1, 'ماوس بی‌سیم تسکو مدل TM 667', 'ماوس-بی-سیم-تسکو-tm667', 'ماوس ارگونومیک با دانگل وایرلس و دقت ۱۶۰۰ دی‌پی‌آی.', 540000, 390000, 40, 'SKU-006', '626000100006', 'عدد', 90, 0.1],
            [1, 1, 'کیبورد باسیم بیاند با حروف فارسی', 'کیبورد-باسیم-بیاند-فارسی', 'کیبورد استاندارد اداری با کلیدهای بی‌صدا و پایه‌های تنظیم شیب.', 1850000, 1380000, 35, 'SKU-007', '626000100007', 'عدد', 550, 0.1],
            [1, 1, 'کابل شارژ تایپ سی انکر ۱ متری', 'کابل-شارژ-تایپ-سی-انکر', 'کابل با روکش بافته شده نایلونی مقاوم در برابر خمش و پارگی.', 380000, 270000, 90, 'SKU-008', '626000100008', 'عدد', 40, 0.1],
            [1, 2, 'ماشین حساب رومیزی کاسیو ۱۲ رقمی', 'ماشین-حساب-کاسیو-12-رقمی', 'ماشین حساب اداری با دو منبع تغذیه خورشیدی و باتری برای حسابداری.', 1450000, 1100000, 25, 'SKU-009', '626000100009', 'عدد', 210, 0.1],
            [1, 2, 'منگنه فلزی کانکس سایز بزرگ', 'منگنه-فلزی-کانکس', 'منگنه رومیزی با ظرفیت دوخت تا ۳۰ برگه به صورت همزمان.', 390000, 280000, 50, 'SKU-010', '626000100010', 'عدد', 320, 0.1],
            [1, 2, 'ماژیک وایت‌برد بسته ۴ رنگ', 'ماژیک-وایت-برد-4-رنگ', 'ماژیک کم‌بو و سریع‌خشک‌شونده در رنگ‌های آبی، مشکی، قرمز و سبز.', 220000, 150000, 70, 'SKU-011', '626000100011', 'بسته', 80, 0.1],
            [1, 2, 'دفتر ۱۰۰ برگ سیمی جلد سخت', 'دفتر-100-برگ-سیمی', 'دفتر با خطوط شفاف و جلد ضدآب مناسب جلسات و یادداشت‌برداری اداری.', 650000, 450000, 65, 'SKU-012', '626000100012', 'عدد', 350, 0.1],
        ];

        foreach ($shop1Products as $p) {
            $insProd->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12], $now, $now]);
        }

        $shop2Products = [
            [2, 4, 'پیراهن آستین بلند مردانه کتان', 'پیراهن-مردانه-کتان', 'پیراهن اسلیم‌فیت دوخته‌شده از پنبه ۱۰۰٪ طبیعی با رنگ ثابت.', 1250000, 850000, 40, 'SKU-201', '626000200001', 'عدد', 280, 0.05],
            [2, 4, 'شلوار کتان کلاسیک مردانه', 'شلوار-کتان-مردانه', 'شلوار راحت با تن‌خور استاندارد و جیب‌های عمیق مناسب استفاده روزمره.', 1850000, 1300000, 30, 'SKU-202', '626000200002', 'عدد', 450, 0.05],
            [2, 5, 'شومیز زنانه یقه ملوانی مجلسی', 'شومیز-زنانه-یقه-ملوانی', 'شومیز لطیف از جنس کرپ الیزه در رنگ‌های استخوانی و کرم.', 1650000, 1150000, 25, 'SKU-203', '626000200003', 'عدد', 210, 0.05],
            [2, 5, 'شال نخی دست‌دوز بهاره', 'شال-نخی-بهاره-دست-دوز', 'شال قواره بزرگ با حاشیه منگوله‌دار و بدون پرزدهی.', 450000, 280000, 60, 'SKU-204', '626000200004', 'عدد', 110, 0.05],
            [2, 6, 'کفش چرم طبیعی کالج زنانه', 'کفش-چرم-کالج-زنانه', 'کفش راحتی دست‌دوز با کفی طبی آنتی‌باکتریال.', 3200000, 2400000, 20, 'SKU-205', '626000200005', 'جفت', 650, 0.05],
            [2, 6, 'کیف دوشی مینیمال چرم مصنوعی', 'کیف-دوشی-مینیمال', 'کیف سبک و شیک با بند قابل تنظیم و زیپ ضدآب.', 950000, 650000, 35, 'SKU-206', '626000200006', 'عدد', 350, 0.05],
        ];

        foreach ($shop2Products as $p) {
            $insProd->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12], $now, $now]);
        }

        // 7. Seed Initial Inventory Transactions
        $insInv = $pdo->prepare("
            INSERT INTO inventory_transactions (shop_id, product_id, type, quantity, unit_cost, reference_type, notes, created_by_id, created_at)
            VALUES (?, ?, 'inward', ?, ?, 'initial_stock', 'شارژ اولیه انبار در زمان راه‌اندازی', ?, ?)
        ");
        $prods = $pdo->query("SELECT id, shop_id, stock_quantity, cost_price FROM products")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prods as $pr) {
            $insInv->execute([$pr['shop_id'], $pr['id'], $pr['stock_quantity'], $pr['cost_price'], $owner1Id, $now]);
        }

        // 8. Seed Realistic Orders
        $insOrd = $pdo->prepare("
            INSERT INTO orders (uuid, shop_id, customer_id, created_by_type, created_by_id, status, subtotal, shipping_cost, tax_amount, discount_amount, estimated_total, final_total, payment_method, payment_status, payment_reference, paid_at, reservation_expires_at, shipping_method, tracking_code, shipped_at, delivered_at, completed_at, finalised_at, address_id, address_snapshot, customer_snapshot, description, seen_by_customer, seen_by_admin, created_at, updated_at)
            VALUES (?, ?, ?, 'customer', ?, ?, ?, ?, ?, 0, ?, ?, 'card_to_card', ?, ?, ?, ?, 'پست پیشتاز', ?, ?, ?, ?, ?, 1, 'تهران، خیابان ولیعصر، پلاک ۲۴', 'امیر رضایی (۰۹۱۲۱۰۰۰۰۰۱)', 'سفارش ثبت‌شده از ویترین', 1, 1, ?, ?)
        ");

        $insOrdItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_title, product_sku, unit_price, unit_cost_price, quantity, tax_amount, line_total, unit, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'عدد', ?, ?)
        ");

        $sampleStatuses = [
            ['ORD-1001', 1, $customerIds[0], 'completed', 2450000, 250000, 245000, 2945000, 2945000, 'paid', 'REF-987101', $now, null, '2400000000000000001001', $now, $now, $now, $now],
            ['ORD-1002', 1, $customerIds[1], 'shipped', 1700000, 250000, 170000, 2120000, 2120000, 'paid', 'REF-987102', $now, null, '2400000000000000001002', $now, null, null, $now],
            ['ORD-1003', 1, $customerIds[2], 'paid', 850000, 250000, 85000, 1185000, 1185000, 'paid', 'REF-987103', $now, null, null, null, null, null, $now],
            ['ORD-1004', 1, $customerIds[3], 'submitted', 540000, 250000, 54000, 844000, 844000, 'unpaid', null, null, date('Y-m-d H:i:s', time() + 4 * 86400), null, null, null, null, null],
            ['ORD-2001', 2, $customerIds[4], 'completed', 3100000, 200000, 155000, 3455000, 3455000, 'paid', 'REF-987201', $now, null, '2400000000000000002001', $now, $now, $now, $now],
            ['ORD-2002', 2, $customerIds[5], 'submitted', 1250000, 200000, 62500, 1512500, 1512500, 'unpaid', null, null, date('Y-m-d H:i:s', time() + 3 * 86400), null, null, null, null, null],
        ];

        foreach ($sampleStatuses as $so) {
            $insOrd->execute([
                $so[0], $so[1], $so[2], $so[2], $so[3], $so[4], $so[5], $so[6], $so[7], $so[8],
                $so[9], $so[10], $so[11], $so[12], $so[13], $so[14], $so[15], $so[16], $so[17], $now, $now
            ]);
            $ordId = (int)$pdo->lastInsertId();
            $insOrdItem->execute([$ordId, 1, 'کالای نمونه سفارش', 'SKU-001', (int)$so[4], (int)($so[4] * 0.75), 1, (float)$so[6], (float)$so[4], $now, $now]);
        }

        // 9. Seed Initial Accounting Entries
        $insLedger = $pdo->prepare("
            INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description, created_by_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insLedger->execute([1, $now, 'sale_revenue', 1, 0, 2450000, 'درآمد حاصل از فروش کالا', 'فروش سفارش شماره ORD-1001', $owner1Id, $now]);
        $insLedger->execute([1, $now, 'payment_received', 1, 2945000, 0, 'موجودی نقد و بانک', 'واریز کارت‌به‌کارت سفارش ORD-1001', $owner1Id, $now]);
        $insLedger->execute([2, $now, 'sale_revenue', 5, 0, 3100000, 'درآمد حاصل از فروش کالا', 'فروش سفارش شماره ORD-2001', $owner2Id, $now]);
        $insLedger->execute([2, $now, 'payment_received', 5, 3455000, 0, 'موجودی نقد و بانک', 'واریز کارت‌به‌کارت سفارش ORD-2001', $owner2Id, $now]);

        // 10. Seed Sample Tickets
        $insTicket = $pdo->prepare("
            INSERT INTO tickets (shop_id, customer_id, subject, body, status, created_by_type, created_by_id, seen_by_customer, seen_by_admin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'customer', ?, 1, 0, ?, ?)
        ");
        $insMsg = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $insTicket->execute([1, $customerIds[0], 'پرسش درباره زمان ارسال سفارش ORD-1001', 'سلام، سفارش من کی تحویل پست داده می‌شود؟', 'closed', $customerIds[0], $now, $now]);
        $t1 = (int)$pdo->lastInsertId();
        $insMsg->execute([$t1, 'customer', $customerIds[0], 'سلام، سفارش من کی تحویل پست داده می‌شود؟', $now]);
        $insMsg->execute([$t1, 'shop_manager', $owner1Id, 'سلام و درود، مرسوله شما تحویل پست پیشتاز شد و کد رهگیری در سامانه ثبت گردید.', $now]);

        $insTicket->execute([1, $customerIds[1], 'درخواست فاکتور رسمی', 'با سلام و احترام، لطفا فاکتور رسمی با شناسه اقتصادی ارسال فرمایید.', 'open', $customerIds[1], $now, $now]);
        $t2 = (int)$pdo->lastInsertId();
        $insMsg->execute([$t2, 'customer', $customerIds[1], 'با سلام و احترام، لطفا فاکتور رسمی با شناسه اقتصادی ارسال فرمایید.', $now]);

        // Mark demo seeded
        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('demo_seeded', '1')")->execute();
        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('installed_at', ?)")->execute([$now]);

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
