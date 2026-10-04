<?php
declare(strict_types=1);

/**
 * setup_seed.php
 * Comprehensive demo data seeder for Befroosh Multi-Tenant & Multi-Branch Platform
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

        // 1. Seed Subscription Plans (Free, Plus, Pro, Ultimate)
        $insPlan = $pdo->prepare("
            INSERT OR REPLACE INTO subscription_plans (id, slug, code, name, price_irt, price_monthly, price_yearly, max_businesses, max_branches, max_products, max_managers, has_ticketing, has_online_payment, has_ticket_escalation, reporting_years, allow_custom_domain, allow_sms_alerts, is_active, active, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)
        ");
        $plans = [
            [1, 'free', 'free', 'طرح پایه رایگان', 0, 0, 0, 1, 1, 50, 1, 0, 0, 0, 1, 0, 0, 1],
            [2, 'plus', 'plus', 'طرح پیشرفته پلاس', 490000, 490000, 4900000, 1, 3, 200, 3, 1, 1, 0, 5, 1, 1, 2],
            [3, 'pro', 'pro', 'طرح حرفه‌ای پرو', 990000, 990000, 9900000, 1, 5, 1000, 10, 1, 1, 0, 0, 1, 1, 3],
            [4, 'ultimate', 'ultimate', 'طرح نامحدود سازمانی', 1990000, 1990000, 19900000, 10, 9999, 999999, 9999, 1, 1, 1, 0, 1, 1, 4],
        ];
        foreach ($plans as $p) {
            $insPlan->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12], $p[13], $p[14], $p[15], $p[16], $p[17], $now]);
        }

        // 2. Seed Shops (Businesses)
        $insShop = $pdo->prepare("
            INSERT INTO shops (id, name, slug, phone, email, address, national_id, economic_code, card_number, card_holder, bank_name, shaba_number, card_to_card_enabled, reservation_days, payment_methods, tax_rate, default_shipping_cost, free_shipping_threshold, subscription_plan_id, subscription_expires_at, active, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, '[\"card_to_card\"]', ?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        $insShop->execute([1, 'فروشگاه مرکزی بَفروش', 'central', '02188888888', 'info@befroosh.ir', 'تهران، خیابان آزادی، پلاک ۱', '10101234567', '411122334455', '6037997123456789', 'فروشگاه مرکزی بَفروش', 'بانک ملی ایران', 'IR120170000000101234567890', 4, 0.10, 250000, 2500000, 3, date('Y-m-d H:i:s', time() + 365 * 86400), 'فروشگاه اصلی و مرجع انواع کالاهای دیجیتال و لوازم اداری با گارانتی اصالت کالا.', $now, $now]);
        $insShop->execute([2, 'بوتیک و مد ترنج', 'toranj', '02122223333', 'support@toranj-mode.ir', 'تهران، میدان ونک، مجتمع ونک، واحد ۱۰۴', '10109876543', '411188776655', '5022291087654321', 'سارا رهنما (ترنج)', 'بانک پاسارگاد', 'IR980570000000208765432100', 3, 0.05, 200000, 1500000, 2, date('Y-m-d H:i:s', time() + 365 * 86400), 'عرضه‌کننده جدیدترین مدل‌های پوشاک فصلی، کفش و اکسسوری با کیفیت عالی.', $now, $now]);

        // 3. Seed Shipping Groups
        $insSg = $pdo->prepare("INSERT INTO shipping_groups (id, business_id, name, shipping_cost, free_shipping_threshold, central_hub_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insSg->execute([1, 1, 'گروه ارسال متمرکز پایتخت (تهران)', 250000, 2500000, 'تهران، هاب لجستیک و انبار مرکزی آزادی', $now]);
        $insSg->execute([2, 2, 'گروه ارسال سریع بوتیک ترنج', 200000, 1500000, 'تهران، هاب ارسال شمیرانات، شعبه ونک', $now]);

        // 4. Seed Branches
        $insBranch = $pdo->prepare("
            INSERT INTO branches (id, business_id, name, slug, catalog_mode, shipping_group_id, default_shipping_cost, free_shipping_threshold, address, phone, is_main, active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $branches = [
            [1, 1, 'شعبه مرکزی آزادی', 'azadi', 'both', 1, 250000, 2500000, 'تهران، خیابان آزادی، پلاک ۱', '02188888888', 1],
            [2, 1, 'شعبه شرق (تهرانپارس)', 'tehranpars', 'both', 1, 250000, 2500000, 'تهران، فلکه دوم تهرانپارس، پلاک ۸۰', '02177777777', 0],
            [3, 1, 'شعبه غرب (صادقیه)', 'sadeghiyeh', 'business_only', 1, 250000, 2500000, 'تهران، فلکه دوم صادقیه، برج گلدیس', '02144444444', 0],
            [4, 2, 'شعبه مرکزی ونک', 'vanak', 'both', 2, 200000, 1500000, 'تهران، میدان ونک، مجتمع ونک، واحد ۱۰۴', '02122223333', 1],
            [5, 2, 'شعبه اندرزگو', 'andarzgoo', 'both', 2, 200000, 1500000, 'تهران، بلوار اندرزگو، مجتمع تجاری آوان', '02122224444', 0],
        ];
        foreach ($branches as $br) {
            $insBranch->execute([$br[0], $br[1], $br[2], $br[3], $br[4], $br[5], $br[6], $br[7], $br[8], $br[9], $br[10], $now]);
        }

        // 5. Seed Bank Cards
        $insCard = $pdo->prepare("INSERT INTO shop_bank_cards (shop_id, card_number, card_holder, bank_name, shaba_number, active, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, '1406/12/29', ?, ?)");
        $insCard->execute([1, '6037997123456789', 'فروشگاه مرکزی بَفروش', 'بانک ملی ایران', 'IR120170000000101234567890', $now, $now]);
        $insCard->execute([1, '5892101234567890', 'فروشگاه مرکزی (حساب دوم)', 'بانک سپه', 'IR850150000000301234567890', $now, $now]);
        $insCard->execute([2, '5022291087654321', 'سارا رهنما (ترنج)', 'بانک پاسارگاد', 'IR980570000000208765432100', $now, $now]);

        // 6. Seed Categories
        $insCat = $pdo->prepare("INSERT INTO categories (shop_id, name, slug, sort_order, active, created_at) VALUES (?, ?, ?, ?, 1, ?)");
        foreach ([['کالای دیجیتال', 'digital', 1], ['لوازم اداری و مصرفی', 'office', 2], ['کاغذ و بایگانی', 'paper-archive', 3]] as $c) { $insCat->execute([1, $c[0], $c[1], $c[2], $now]); }
        foreach ([['پوشاک مردانه', 'men-clothing', 1], ['پوشاک زنانه', 'women-clothing', 2], ['کیف و کفش', 'shoes-bags', 3]] as $c) { $insCat->execute([2, $c[0], $c[1], $c[2], $now]); }

        // 7. Seed Users (Superadmin, BusinessOwner, BranchManager, Manager, Customer)
        $insUser = $pdo->prepare("
            INSERT INTO users (username, password, nickname, first_name, last_name, role, shop_id, phone, national_code, notes, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        $insUser->execute(['superadmin', $pwd, 'مدیر ارشد سامانه', 'مدیر', 'ارشد', 'superadmin', null, '9120000001', '0010000001', 'دسترسی ریشه', $now, $now]);
        $insUser->execute(['admin', $pwdAdmin, 'مدیر کل بَفروش', 'مدیر', 'کل', 'superadmin', null, '9120000003', '0010000003', 'مدیریت کل', $now, $now]);

        // Shop 1 Staff
        $insUser->execute(['admin1', $pwd, 'مهدی توکلی (مالک مرکزی)', 'مهدی', 'توکلی', 'business_owner', 1, '9122000001', '0020000001', 'مالک کسب‌وکار مرکزی', $now, $now]);
        $owner1Id = (int)$pdo->lastInsertId();
        $insUser->execute(['admin2', $pwd, 'نسترن رحیمی (مدیر شعب آزادی و شرق)', 'نسترن', 'رحیمی', 'branch_manager', 1, '9122000002', '0020000002', 'مدیر دو شعبه', $now, $now]);
        $mgr1Id = (int)$pdo->lastInsertId();
        $insUser->execute(['manager_tech', $pwdManager, 'پگاه سالاری (مدیر شعبه صادقیه)', 'پگاه', 'سالاری', 'branch_manager', 1, '9123000002', '0020000004', 'مدیر شعبه غرب', $now, $now]);
        $mgr2Id = (int)$pdo->lastInsertId();
        $insUser->execute(['admin5', $pwd, 'حمید طاهری (انباردار)', 'حمید', 'طاهری', 'manager', 1, '9122000005', '0020000005', 'مسئول انبار', $now, $now]);

        // Shop 2 Staff
        $insUser->execute(['admin3', $pwd, 'سارا رهنما (مالک ترنج)', 'سارا', 'رهنما', 'business_owner', 2, '9122000003', '0020000006', 'مالک بوتیک ترنج', $now, $now]);
        $owner2Id = (int)$pdo->lastInsertId();
        $insUser->execute(['admin4', $pwd, 'بابک نظری (مدیر شعب ترنج)', 'بابک', 'نظری', 'branch_manager', 2, '9122000004', '0020000007', 'مدیر شعب ترنج', $now, $now]);
        $mgr3Id = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE shops SET owner_id = ? WHERE id = 1")->execute([$owner1Id]);
        $pdo->prepare("UPDATE shops SET owner_id = ? WHERE id = 2")->execute([$owner2Id]);

        // 8. Seed Branch User Assignments
        $insAssign = $pdo->prepare("INSERT INTO branch_user_assignments (business_id, branch_id, user_id, role, created_at) VALUES (?, ?, ?, ?, ?)");
        $insAssign->execute([1, 1, $mgr1Id, 'branch_manager', $now]);
        $insAssign->execute([1, 2, $mgr1Id, 'branch_manager', $now]);
        $insAssign->execute([1, 3, $mgr2Id, 'branch_manager', $now]);
        $insAssign->execute([2, 4, $mgr3Id, 'branch_manager', $now]);
        $insAssign->execute([2, 5, $mgr3Id, 'branch_manager', $now]);

        // 9. Seed Customers
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
            $insUser->execute([$uName, $uPwd, $cn[0] . ' ' . $cn[1], $cn[0], $cn[1], 'customer', null, $phone, $natCode, 'مشتری پلتفرم', $now, $now]);
            $customerIds[] = (int)$pdo->lastInsertId();
        }

        // 10. Seed Customer Addresses
        $insAddr = $pdo->prepare("
            INSERT INTO addresses (user_id, recipient_name, recipient_phone, state, city, address, postal_code, description, is_default, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($customerIds as $idx => $cId) {
            $name = $customerNames[$idx][0] . ' ' . $customerNames[$idx][1];
            $phone = '0912100' . sprintf('%04d', $idx + 1);
            $insAddr->execute([$cId, $name, $phone, 'تهران', 'تهران', 'خیابان ولیعصر، بالاتر از میدان ونک، پلاک ۲۴، واحد ۳', '1987654321', 'تحویل به نگهبانی', 1, $now, $now]);
            $insAddr->execute([$cId, $name, $phone, 'البرز', 'کرج', 'بلوار طالقانی شمالی، خیابان بهار، پلاک ۱۲', '3145678901', 'زنگ واحد ۲', 0, $now, $now]);
        }

        // 11. Seed Products (Central and Branch Specific)
        $insProd = $pdo->prepare("
            INSERT INTO products (shop_id, branch_id, category_id, title, slug, description, price, cost_price, stock_quantity, reserved_quantity, min_stock_alert, min_order_qty, sku, barcode, unit, weight_grams, tax_rate, max_per_order, max_per_month, is_business_product, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 5, 1, ?, ?, ?, ?, ?, 15, 100, ?, 1, ?, ?)
        ");
        $prodsList = [
            // Shop 1 - Central Products (branch_id = null)
            [1, null, 2, 'خودکار آبی پنتر', 'خودکار-آبی-پنتر', 'خودکار روان با جوهر آبی مقاوم و مناسب استفاده اداری.', 120000, 85000, 150, 'SKU-001', '626000100001', 'عدد', 15, 0.1, 1],
            [1, null, 2, 'خودکار مشکی فابرکاستل', 'خودکار-مشکی-فابرکاستل', 'خودکار با کیفیت آلمانی مناسب نگارش اسناد رسمی.', 140000, 98000, 120, 'SKU-002', '626000100002', 'عدد', 15, 0.1, 1],
            [1, null, 3, 'بسته کاغذ A4 کپی‌مکس ۵۰۰ برگی', 'بسته-کاغذ-a4-کپی-مکس', 'بسته ۵۰۰ برگی کاغذ تحریر سفید ۸۰ گرمی مناسب چاپگر.', 850000, 680000, 80, 'SKU-003', '626000100003', 'بسته', 2400, 0.1, 1],
            [1, null, 1, 'فلش مموری ۳۲ گیگابایت سن‌دیسک', 'فلش-مموری-32-گیگ-سندیسک', 'فلش مموری پرسرعت USB 3.0 با بدنه مقاوم.', 2450000, 1950000, 45, 'SKU-005', '626000100005', 'عدد', 20, 0.1, 1],
            // Shop 1 - Branch 1 Specific (Azadi)
            [1, 1, 1, 'ماوس بی‌سیم تسکو مدل TM 667', 'ماوس-بی-سیم-تسکو-tm667', 'ماوس ارگونومیک با دانگل وایرلس و دقت ۱۶۰۰ دی‌پی‌آی.', 540000, 390000, 40, 'SKU-006', '626000100006', 'عدد', 90, 0.1, 0],
            [1, 1, 1, 'کیبورد باسیم بیاند با حروف فارسی', 'کیبورد-باسیم-بیاند-فارسی', 'کیبورد استاندارد اداری با کلیدهای بی‌صدا.', 1850000, 1380000, 35, 'SKU-007', '626000100007', 'عدد', 550, 0.1, 0],
            // Shop 1 - Branch 2 Specific (Tehranpars)
            [1, 2, 2, 'ماشین حساب رومیزی کاسیو ۱۲ رقمی', 'ماشین-حساب-کاسیو-12-رقمی', 'ماشین حساب اداری با دو منبع تغذیه خورشیدی و باتری.', 1450000, 1100000, 25, 'SKU-009', '626000100009', 'عدد', 210, 0.1, 0],
            [1, 2, 2, 'منگنه فلزی کانکس سایز بزرگ', 'منگنه-فلزی-کانکس', 'منگنه رومیزی با ظرفیت دوخت تا ۳۰ برگه همزمان.', 390000, 280000, 50, 'SKU-010', '626000100010', 'عدد', 320, 0.1, 0],
            // Shop 2 - Central & Branch Specific
            [2, null, 4, 'پیراهن آستین بلند مردانه کتان', 'پیراهن-مردانه-کتان', 'پیراهن اسلیم‌فیت پنبه‌ای با تن‌خور کلاسیک.', 1250000, 850000, 40, 'SKU-201', '626000200001', 'عدد', 280, 0.05, 1],
            [2, 4, 5, 'شومیز زنانه یقه ملوانی مجلسی', 'شومیز-زنانه-یقه-ملوانی', 'شومیز لطیف از جنس کرپ الیزه در رنگ‌های متنوع.', 1650000, 1150000, 25, 'SKU-203', '626000200003', 'عدد', 210, 0.05, 0],
            [2, 5, 6, 'کفش چرم طبیعی کالج زنانه', 'کفش-چرم-کالج-زنانه', 'کفش راحتی دست‌دوز با کفی طبی آنتی‌باکتریال.', 3200000, 2400000, 20, 'SKU-205', '626000200005', 'جفت', 650, 0.05, 0],
        ];
        foreach ($prodsList as $p) {
            $insProd->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12], $p[13], $p[14], $now, $now]);
        }

        // 12. Seed Inventory Transactions
        $insInv = $pdo->prepare("INSERT INTO inventory_transactions (shop_id, product_id, type, quantity, unit_cost, reference_type, notes, created_by_id, created_at) VALUES (?, ?, 'inward', ?, ?, 'initial_stock', 'شارژ موجودی اولیه', ?, ?)");
        $allProds = $pdo->query("SELECT id, shop_id, stock_quantity, cost_price FROM products")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allProds as $pr) {
            $insInv->execute([$pr['shop_id'], $pr['id'], $pr['stock_quantity'], $pr['cost_price'], $owner1Id, $now]);
        }

        // 13. Seed Orders with Branches, Shipping Groups & Receipts
        $insOrd = $pdo->prepare("
            INSERT INTO orders (uuid, shop_id, branch_id, shipping_group_id, customer_id, created_by_type, created_by_id, status, subtotal, shipping_cost, tax_amount, discount_amount, estimated_total, final_total, payment_method, payment_status, payment_reference, receipt_description, receipt_status, receipt_verified_by, receipt_verified_at, paid_at, reservation_expires_at, shipping_method, tracking_code, shipped_at, delivered_at, completed_at, finalised_at, address_id, address_snapshot, customer_snapshot, description, seen_by_customer, seen_by_admin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'customer', ?, ?, ?, ?, ?, 0, ?, ?, 'card_to_card', ?, ?, ?, ?, ?, ?, ?, ?, 'پست پیشتاز', ?, ?, ?, ?, ?, 1, 'تهران، خیابان ولیعصر، پلاک ۲۴', 'خریدار محترم', 'سفارش آنلاین', 1, 1, ?, ?)
        ");
        $insOrdItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_title, product_sku, unit_price, unit_cost_price, quantity, tax_amount, line_total, unit, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'عدد', ?, ?)");

        $ordersSeed = [
            // ORD-1001: Completed, approved receipt
            ['ORD-1001', 1, 1, 1, $customerIds[0], 'completed', 2450000, 250000, 245000, 2945000, 2945000, 'paid', 'REF-987101', 'واریز کارت به کارت فیش شماره ۹۸۷۱۰۱', 'approved', $owner1Id, $now, $now, null, '2400000000000000001001', $now, $now, $now, $now],
            // ORD-1002: Shipped
            ['ORD-1002', 1, 2, 1, $customerIds[1], 'shipped', 1700000, 250000, 170000, 2120000, 2120000, 'paid', 'REF-987102', 'رسید همراه کارت به کارت', 'approved', $owner1Id, $now, $now, null, '2400000000000000001002', $now, null, null, $now],
            // ORD-1003: Paid
            ['ORD-1003', 1, 1, 1, $customerIds[2], 'paid', 850000, 250000, 85000, 1185000, 1185000, 'paid', 'REF-987103', 'واریز پایا شماره ارجاع ۲۳۸۹', 'approved', $owner1Id, $now, $now, null, null, null, null, null, $now],
            // ORD-1004: In verification queue! (pending_verification)
            ['ORD-1004', 1, 1, 1, $customerIds[3], 'submitted', 540000, 250000, 54000, 844000, 844000, 'pending_verification', 'REF-334455', 'واریز به شماره کارت ۶۰۳۷ ساعت ۱۱:۲۳ پیگیری ۳۳۴۴۵۵', 'pending', null, null, null, date('Y-m-d H:i:s', time() + 4 * 86400), null, null, null, null, null],
            // ORD-1005: Submitted unpaid reservation
            ['ORD-1005', 1, 3, 1, $customerIds[4], 'submitted', 1450000, 250000, 145000, 1845000, 1845000, 'unpaid', null, null, 'pending', null, null, null, date('Y-m-d H:i:s', time() + 3 * 86400), null, null, null, null, null],
            // ORD-2001: Shop 2 completed
            ['ORD-2001', 2, 4, 2, $customerIds[5], 'completed', 3100000, 200000, 155000, 3455000, 3455000, 'paid', 'REF-987201', 'واریز به حساب پاسارگاد', 'approved', $owner2Id, $now, $now, null, '2400000000000000002001', $now, $now, $now, $now],
        ];

        foreach ($ordersSeed as $o) {
            $insOrd->execute([
                $o[0], $o[1], $o[2], $o[3], $o[4], $o[4], $o[5], $o[6], $o[7], $o[8], $o[9], $o[10],
                $o[11], $o[12], $o[13], $o[14], $o[15], $o[16], $o[17], $o[18], $o[19], $o[20], $o[21], $o[22], $o[23], $now, $now
            ]);
            $ordId = (int)$pdo->lastInsertId();
            $insOrdItem->execute([$ordId, 1, 'کالای منتخب سفارش', 'SKU-001', (int)$o[6], (int)($o[6] * 0.75), 1, (float)$o[8], (float)$o[6], $now, $now]);
        }

        // 14. Seed Accounting Ledger (Double-Entry)
        $insLedger = $pdo->prepare("INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description, created_by_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insLedger->execute([1, $now, 'sale_revenue', 1, 0, 2450000, 'درآمد حاصل از فروش کالا', 'فروش سفارش ORD-1001', $owner1Id, $now]);
        $insLedger->execute([1, $now, 'payment_received', 1, 2945000, 0, 'موجودی نقد و بانک', 'واریز وجه سفارش ORD-1001', $owner1Id, $now]);
        $insLedger->execute([2, $now, 'sale_revenue', 6, 0, 3100000, 'درآمد حاصل از فروش کالا', 'فروش سفارش ORD-2001', $owner2Id, $now]);
        $insLedger->execute([2, $now, 'payment_received', 6, 3455000, 0, 'موجودی نقد و بانک', 'واریز وجه سفارش ORD-2001', $owner2Id, $now]);

        // 15. Seed Tickets with Escalation
        $insTicket = $pdo->prepare("
            INSERT INTO tickets (shop_id, customer_id, subject, body, status, created_by_type, created_by_id, is_escalated_to_superadmin, is_elevated_to_superadmin, seen_by_customer, seen_by_admin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'customer', ?, ?, ?, 1, 0, ?, ?)
        ");
        $insMsg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES (?, ?, ?, ?, ?)");

        // Regular ticket
        $insTicket->execute([1, $customerIds[0], 'پیگیری زمان تحویل مرسوله ORD-1001', 'با سلام، مرسوله چه زمانی تحویل مامور پست خواهد شد؟', 'closed', $customerIds[0], 0, 0, $now, $now]);
        $t1 = (int)$pdo->lastInsertId();
        $insMsg->execute([$t1, 'customer', $customerIds[0], 'با سلام، مرسوله چه زمانی تحویل مامور پست خواهد شد؟', $now]);
        $insMsg->execute([$t1, 'shop_manager', $owner1Id, 'سلام و درود، مرسوله تحویل پست گردیده و کد رهگیری ثبت شد.', $now]);

        // Open ticket
        $insTicket->execute([1, $customerIds[1], 'درخواست صدور فاکتور رسمی با شناسه اقتصادی', 'لطفاً فاکتور رسمی ممهور صادر فرمایید.', 'open', $customerIds[1], 0, 0, $now, $now]);
        $t2 = (int)$pdo->lastInsertId();
        $insMsg->execute([$t2, 'customer', $customerIds[1], 'لطفاً فاکتور رسمی ممهور صادر فرمایید.', $now]);

        // Escalated Ticket to SuperAdmin!
        $insTicket->execute([1, $customerIds[2], 'درخواست ارتقای پلن به Ultimate و اتصال دامنه اختصاصی', 'با سلام، مایل به اتصال دامنه اختصاصی و فعال‌سازی تیکت اولویت‌دار هستیم.', 'open', $customerIds[2], 1, 1, $now, $now]);
        $t3 = (int)$pdo->lastInsertId();
        $insMsg->execute([$t3, 'customer', $customerIds[2], 'با سلام، مایل به اتصال دامنه اختصاصی و فعال‌سازی تیکت اولویت‌دار هستیم.', $now]);

        // 16. Contact Messages
        $pdo->prepare("INSERT INTO shop_contact_messages (shop_id, name, phone, message, is_read, created_at) VALUES (1, 'رضا شایان', '09123334455', 'درخواست همکاری در فروش عمده لوازم تحریر اداری', 0, ?)")->execute([$now]);
        $pdo->prepare("INSERT INTO shop_contact_messages (shop_id, name, phone, message, is_read, created_at) VALUES (2, 'مهسا تهرانی', '09127778899', 'آیا امکان سفارش حضوری در شعبه ونک وجود دارد؟', 0, ?)")->execute([$now]);

        // Meta tags
        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('demo_seeded', '1')")->execute();
        $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES ('installed_at', ?)")->execute([$now]);

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
