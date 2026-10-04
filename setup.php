<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DB_PATH', STORAGE_PATH . '/database.sqlite');
define('TICKET_UPLOAD_PATH', STORAGE_PATH . '/tickets');
define('PRODUCT_UPLOAD_PATH', STORAGE_PATH . '/products');

if (file_exists(STORAGE_PATH . '/installed.lock') && !isset($_GET['unlock_token'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>سیستم قفل است</title><style>body{font-family:Tahoma,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:480px;text-align:center;color:#1f2937;}h1{color:#dc2626;font-size:1.5rem;margin-bottom:1rem;}p{line-height:1.6;color:#4b5563;margin-bottom:1.5rem;}.btn{display:inline-block;background:#3b82f6;color:#fff;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:bold;}</style></head><body><div class="card"><h1>سیستم قبلاً راه‌اندازی شده است</h1><p>برای حفظ امنیت و جلوگیری از حذف تصادفی پایگاه داده، دسترسی به این صفحه مسدود است. در صورت نیاز به بازنشانی، فایل <code>storage/installed.lock</code> را حذف نمایید.</p><a href="/" class="btn">ورود به سامانه</a></div></body></html>';
    exit;
}

$fresh = isset($_GET['fresh']);

try {
    foreach ([STORAGE_PATH, TICKET_UPLOAD_PATH, PRODUCT_UPLOAD_PATH] as $dir) {
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($fresh) {
        drop_all_tables($pdo);
    }

    install_schema($pdo);

    $alreadySeeded = get_meta($pdo, 'demo_seeded') === '1';
    $hasData = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;

    $seededNow = false;

    if ($fresh || (!$alreadySeeded && !$hasData)) {
        seed_demo($pdo);
        set_meta($pdo, 'demo_seeded', '1');
        $seededNow = true;
    }

    $stats = get_stats($pdo);
    $error = '';
} catch (Throwable $ex) {
    $stats = [];
    $seededNow = false;
    $error = 'خطا در نصب یا درج داده نمونه: ' . $ex->getMessage();
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function fa_digits(string $s): string
{
    return str_replace(
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        $s
    );
}

function tehran_timezone(): DateTimeZone
{
    static $tz = null;

    if ($tz === null) {
        try {
            $tz = new DateTimeZone('Asia/Tehran');
        } catch (Throwable $ex) {
            $tz = new DateTimeZone('+03:30');
        }
    }

    return $tz;
}

function to_utc(DateTimeImmutable $dt): string
{
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function random_dt(DateTimeImmutable $min, DateTimeImmutable $max): DateTimeImmutable
{
    if ($max <= $min) {
        return $min;
    }

    $ts = mt_rand($min->getTimestamp(), $max->getTimestamp());

    return (new DateTimeImmutable('@' . $ts))->setTimezone(tehran_timezone());
}

function generate_uuid(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, 8);
}

function make_unique_uuid(array &$used): string
{
    do {
        $uuid = generate_uuid();
    } while (isset($used[$uuid]));

    $used[$uuid] = true;

    return $uuid;
}

function qty_by_price(float $price): float
{
    if ($price > 1500000) {
        return (float) mt_rand(1, 2);
    }

    if ($price > 500000) {
        return (float) mt_rand(1, 5);
    }

    return (float) mt_rand(1, 20);
}

function drop_all_tables(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');

    $tables = [
        'ticket_attachments',
        'ticket_order_relations',
        'ticket_messages',
        'tickets',
        'order_items',
        'orders',
        'addresses',
        'products',
        'users',
        'login_attempts',
        'app_meta',
    ];

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS {$table}");
    }

    try {
        $pdo->exec("DELETE FROM sqlite_sequence");
    } catch (Throwable $ex) {
        // ignore
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function install_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_meta (
            meta_key TEXT PRIMARY KEY,
            meta_value TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            nickname TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('superadmin','admin','customer')),
            phone TEXT UNIQUE NULL,
            notes TEXT NULL,
            active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            deleted_at TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            state TEXT NOT NULL,
            city TEXT NOT NULL,
            address TEXT NOT NULL,
            postal_code TEXT NULL,
            description TEXT NULL,
            is_default INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NULL,
            price INTEGER NOT NULL,
            sku TEXT UNIQUE,
            image_path TEXT NULL,
            active INTEGER DEFAULT 1,
            deleted_at TEXT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            customer_id INTEGER NOT NULL REFERENCES users(id),
            created_by_type TEXT NOT NULL CHECK(created_by_type IN ('customer','admin','superadmin')),
            created_by_id INTEGER NULL REFERENCES users(id),
            status TEXT NOT NULL CHECK(status IN ('placed','finalised','canceled','completed')),
            cancellation_reason TEXT NULL,
            estimated_total REAL NOT NULL DEFAULT 0,
            final_total REAL NULL,
            address_id INTEGER NULL,
            address_snapshot TEXT,
            customer_snapshot TEXT,
            description TEXT NULL,
            finalised_at TEXT NULL,
            canceled_at TEXT NULL,
            completed_at TEXT NULL,
            seen_by_customer INTEGER DEFAULT 1,
            seen_by_admin INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id),
            product_id INTEGER NULL REFERENCES products(id),
            product_title TEXT NOT NULL,
            unit_price INTEGER NOT NULL,
            quantity REAL NOT NULL,
            line_total REAL NOT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES users(id),
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('open','closed')) DEFAULT 'open',
            created_by_type TEXT NOT NULL CHECK(created_by_type IN ('customer','admin','superadmin')),
            created_by_id INTEGER NOT NULL REFERENCES users(id),
            seen_by_customer INTEGER DEFAULT 1,
            seen_by_admin INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id),
            sender_type TEXT NOT NULL CHECK(sender_type IN ('customer','admin','superadmin')),
            sender_id INTEGER NOT NULL REFERENCES users(id),
            message TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS ticket_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_message_id INTEGER NOT NULL REFERENCES ticket_messages(id),
            file_path TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS ticket_order_relations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id),
            order_id INTEGER NOT NULL REFERENCES orders(id)
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            ip_address TEXT NOT NULL,
            attempt_count INTEGER DEFAULT 0,
            last_attempt_at TEXT DEFAULT (datetime('now')),
            blocked_until TEXT NULL
        );
    ");

    $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);

    if (!in_array('address_id', array_column($cols, 'name'), true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN address_id INTEGER NULL");
    }
}

function get_meta(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare("SELECT meta_value FROM app_meta WHERE meta_key = ?");
    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    return $value === false ? null : (string) $value;
}

function set_meta(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare("INSERT OR REPLACE INTO app_meta (meta_key, meta_value) VALUES (?, ?)")
        ->execute([$key, $value]);
}

function clear_all_data(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');

    $tables = [
        'ticket_attachments',
        'ticket_order_relations',
        'ticket_messages',
        'tickets',
        'order_items',
        'orders',
        'addresses',
        'products',
        'users',
        'login_attempts',
        'app_meta',
    ];

    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM {$table}");
    }

    try {
        $pdo->exec("DELETE FROM sqlite_sequence");
    } catch (Throwable $ex) {
        // ignore
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function get_stats(PDO $pdo): array
{
    return [
        'superadmins' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn(),
        'admins' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'customers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
        'products' => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'addresses' => (int) $pdo->query("SELECT COUNT(*) FROM addresses")->fetchColumn(),
        'placed_orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'placed'")->fetchColumn(),
        'finalised_orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'finalised'")->fetchColumn(),
        'order_items' => (int) $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn(),
        'tickets' => (int) $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
        'ticket_messages' => (int) $pdo->query("SELECT COUNT(*) FROM ticket_messages")->fetchColumn(),
    ];
}

function seed_demo(PDO $pdo): void
{
    clear_all_data($pdo);

    mt_srand(20260616);

    $tz = tehran_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $start = $now->modify('-3 years')->setTime(8, 0, 0);
    $nowUtc = to_utc($now);

    $finalisedMax = $now->modify('-8 days');
    if ($finalisedMax <= $start) {
        $finalisedMax = $now;
    }

    $ticketMax = $now->modify('-1 day');
    if ($ticketMax <= $start) {
        $ticketMax = $now;
    }

    $pdo->beginTransaction();

    try {
        $insertUser = $pdo->prepare("
            INSERT INTO users (username, password, nickname, role, phone, notes, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");

        $makeUser = function (string $username, string $password, string $nickname, string $role, ?string $phone, ?string $notes = null) use ($insertUser, $pdo, $nowUtc): int {
            $insertUser->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $nickname,
                $role,
                $phone,
                $notes,
                $nowUtc,
                $nowUtc,
            ]);

            return (int) $pdo->lastInsertId();
        };

        // ---------------------------------------------------------------
        // Super admins
        // ---------------------------------------------------------------
        $superadminIds = [];

        $superadminIds[] = $makeUser('superadmin', '123456', 'مدیر ارشد یک', 'superadmin', '09120000001', 'دسترسی کامل سیستمی');
        $superadminIds[] = $makeUser('superadmin2', '123456', 'مدیر ارشد دو', 'superadmin', '09120000002', 'دسترسی کامل سیستمی');

        // ---------------------------------------------------------------
        // Admins
        // ---------------------------------------------------------------
        $adminNames = [
            'مهدی توکلی',
            'نسترن رحیمی',
            'کاوه احمدی',
            'پگاه سالاری',
            'بابک نظری',
        ];

        $adminIds = [];

        foreach ($adminNames as $i => $name) {
            $adminIds[] = $makeUser(
                'admin' . ($i + 1),
                '123456',
                $name,
                'admin',
                '0912' . sprintf('%07d', 2000001 + $i),
                'مدیر فروش و پشتیبانی'
            );
        }

        // ---------------------------------------------------------------
        // Customers
        // ---------------------------------------------------------------
        $customerNames = [
            'امیر رضایی',
            'مهدی کریمی',
            'سارا محمدی',
            'نگار حسینی',
            'رضا موسوی',
            'زهرا جعفری',
            'حسین صادقی',
            'مریم نوری',
            'علی قاسمی',
            'نرگس شریفی',
            'فاطمه عباسی',
            'محمد فرهادی',
            'الهام نجفی',
            'پویا طاهری',
            'شیرین صفری',
            'آرش ملکی',
            'مینا کاظمی',
            'بهنام روشنی',
            'لیلا پناهی',
            'کیان اسکندری',
            'آیدا فلاحی',
            'سامان ظریفی',
            'رؤیا شهبازی',
            'پیمان رستمی',
            'هانیه بهرامی',
        ];

        $customers = [];

        foreach ($customerNames as $i => $name) {
            $customerId = $makeUser(
                'customer' . ($i + 1),
                '123456',
                $name,
                'customer',
                '0912' . sprintf('%07d', 1000001 + $i),
                mt_rand(1, 100) <= 50 ? 'مشتری حضوری' : 'مشتری شرکتی'
            );

            $customers[] = [
                'id' => $customerId,
                'username' => 'customer' . ($i + 1),
                'nickname' => $name,
                'phone' => '0912' . sprintf('%07d', 1000001 + $i),
            ];
        }

        // ---------------------------------------------------------------
        // Addresses
        // ---------------------------------------------------------------
        $citiesByState = [
            'تهران' => ['تهران', 'شهریار', 'اسلامشهر', 'ری', 'ورامین'],
            'البرز' => ['کرج', 'فردیس', 'نظرآباد'],
            'قم' => ['قم', 'کهک'],
            'اصفهان' => ['اصفهان', 'کاشان', 'نجف‌آباد'],
            'فارس' => ['شیراز', 'مرودشت', 'جهرم'],
            'خراسان رضوی' => ['مشهد', 'نیشابور', 'سبزوار'],
            'آذربایجان شرقی' => ['تبریز', 'مراغه', 'اهر'],
            'گیلان' => ['رشت', 'انزلی', 'لاهیجان'],
            'مازندران' => ['ساری', 'بابل', 'آمل'],
            'کرمان' => ['کرمان', 'سیرجان', 'رفسنجان'],
        ];

        $streets = [
            'ولیعصر',
            'انقلاب',
            'آزادی',
            'شریعتی',
            'مطهری',
            'بزرگراه چمران',
            'کشاورز',
            'پاسداران',
            'جردن',
            'سهروردی',
            'عباس‌آباد',
            'پیروزی',
            'دماوند',
            'میرداماد',
            'نیاوران',
            'سعادت‌آباد',
            'فردوسی',
            'جمهوری',
            'کارگر',
            'امیرآباد',
        ];

        $alleys = [
            'گلستان',
            'نرگس',
            'یاس',
            'بهشت',
            'شهید محمدی',
            'شهید حسینی',
            'لاله',
            'آفتاب',
        ];

        $addressDescriptions = [
            'تحویل به نگهبانی',
            'زنگ واحد خراب است',
            'قبل از ارسال تماس بگیرید',
            'واحد تجاری است',
            'طبقه دوم',
            '',
        ];

        $insertAddress = $pdo->prepare("
            INSERT INTO addresses (user_id, state, city, address, postal_code, description, is_default, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $addressesByCustomer = [];

        foreach ($customers as $customer) {
            $addressesByCustomer[$customer['id']] = [];

            $addressCount = mt_rand(1, 3);

            for ($j = 0; $j < $addressCount; $j++) {
                if (mt_rand(1, 100) <= 55) {
                    $state = 'تهران';
                } else {
                    $stateKeys = array_keys($citiesByState);
                    $state = $stateKeys[mt_rand(0, count($stateKeys) - 1)];
                }

                $cityOptions = $citiesByState[$state];
                $city = $cityOptions[mt_rand(0, count($cityOptions) - 1)];

                $street = $streets[mt_rand(0, count($streets) - 1)];
                $alley = $alleys[mt_rand(0, count($alleys) - 1)];

                $plaque = mt_rand(1, 220);
                $unit = mt_rand(1, 40);

                $address = "خیابان {$street}، کوچه {$alley}، پلاک {$plaque}، واحد {$unit}";
                $postalCode = (string) mt_rand(1000000000, 9999999999);
                $description = $addressDescriptions[mt_rand(0, count($addressDescriptions) - 1)] ?: null;
                $isDefault = $j === 0 ? 1 : 0;

                $insertAddress->execute([
                    $customer['id'],
                    $state,
                    $city,
                    $address,
                    $postalCode,
                    $description,
                    $isDefault,
                    $nowUtc,
                    $nowUtc,
                ]);

                $addressId = (int) $pdo->lastInsertId();

                $addressesByCustomer[$customer['id']][] = [
                    'id' => $addressId,
                    'state' => $state,
                    'city' => $city,
                    'address' => $address,
                    'postal_code' => $postalCode,
                    'description' => $description,
                ];
            }
        }

        // ---------------------------------------------------------------
        // Products
        // ---------------------------------------------------------------
        $productRows = [
            ['خودکار آبی', 'خودکار روان با جوهر آبی مناسب استفاده اداری.', 120000],
            ['خودکار مشکی', 'خودکار با کیفیت مناسب امضا و نگارش روزمره.', 120000],
            ['روان‌نویس مشکی', 'روان‌نویس با نوک نمدی و جوهر مشکی.', 180000],
            ['ماژیک وایت‌برد', 'ماژیک قابل پاک‌شدن مخصوص وایت‌برد.', 220000],
            ['دفتر ۱۰۰ برگ', 'دفتر سیمی با کاغذ مناسب تحریر.', 650000],
            ['دفترچه یادداشت', 'دفترچه جیبی مناسب یادداشت‌های روزانه.', 280000],
            ['پوشه دکمه‌دار', 'پوشه پلاستیکی مقاوم با درب دکمه‌دار.', 95000],
            ['پوشه کلاسور', 'کلاسور اداری با قابلیت تعویض برگه.', 145000],
            ['بسته کاغذ A4', 'بسته ۵۰۰ برگی کاغذ تحریر سفید.', 850000],
            ['رول کاغذ حرارتی', 'رول حرارتی مناسب دستگاه فیش‌زن.', 420000],
            ['زونکن اداری', 'زونکن بزرگ مناسب بایگانی اسناد.', 320000],
            ['منگنه اداری', 'منگنه فلزی مناسب اسناد اداری.', 390000],
            ['سوزن منگنه', 'بسته سوزن منگنه سایز استاندارد.', 45000],
            ['چسب ماتیکی', 'چسب ماتیکی مناسب کاغذ و مقوا.', 85000],
            ['چسب نواری', 'چسب نواری شفاف با چسبندگی مناسب.', 65000],
            ['قیچی اداری', 'قیچی استیل با دسته راحت.', 140000],
            ['کاتر اداری', 'کاتر با تیغ قابل تعویض.', 110000],
            ['بسته تیغ کاتر', 'بسته تیغ یدک مخصوص کاتر.', 55000],
            ['پاک‌کن', 'پاک‌کن نرم بدون آسیب به کاغذ.', 25000],
            ['غلط‌گیر نواری', 'غلط‌گیر نواری با پوشش یکنواخت.', 98000],
            ['ماشین حساب رومیزی', 'ماشین حساب ۱۲ رقمی مناسب حسابداری.', 1450000],
            ['باتری قلمی بسته ۴ عددی', 'باتری قلمی با ماندگاری مناسب.', 210000],
            ['باتری نیم‌قلمی بسته ۴ عددی', 'باتری نیم‌قلمی مناسب دستگاه‌های کوچک.', 190000],
            ['فلش مموری ۳۲ گیگابایت', 'فلش مموری پرسرعت با بدنه مقاوم.', 2450000],
            ['کابل شارژ تایپ سی', 'کابل شارژ و انتقال داده با طول مناسب.', 380000],
            ['ماوس سیمی', 'ماوس ارگونومیک مناسب استفاده اداری.', 540000],
            ['کیبورد سیمی', 'کیبورد استاندارد با کلیدهای نرم.', 1850000],
            ['هدست اداری', 'هدست با میکروفون مناسب تماس‌های کاری.', 2850000],
            ['وبکم ۱۰۸۰', 'وبکم مناسب جلسات آنلاین.', 3650000],
            ['اسپیکر رومیزی', 'اسپیکر کوچک با صدای شفاف.', 2950000],
        ];

        $insertProduct = $pdo->prepare("
            INSERT INTO products (title, description, price, sku, image_path, active, deleted_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, NULL, 1, NULL, ?, ?)
        ");

        $products = [];

        foreach ($productRows as $i => $row) {
            $sku = 'SKU-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            $insertProduct->execute([
                $row[0],
                $row[1],
                $row[2],
                $sku,
                $nowUtc,
                $nowUtc,
            ]);

            $products[] = [
                'id' => (int) $pdo->lastInsertId(),
                'title' => $row[0],
                'price' => (float) $row[2],
            ];
        }

        // ---------------------------------------------------------------
        // Orders
        // ---------------------------------------------------------------
        $ordersByCustomer = [];

        foreach ($customers as $customer) {
            $ordersByCustomer[$customer['id']] = [];
        }

        $insertOrder = $pdo->prepare("
            INSERT INTO orders (
                uuid, customer_id, created_by_type, created_by_id, status, cancellation_reason,
                estimated_total, final_total, address_id, address_snapshot, customer_snapshot,
                description, finalised_at, canceled_at, completed_at, seen_by_customer, seen_by_admin,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
        ");

        $insertOrderItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_title, unit_price, quantity, line_total, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $orderDescriptions = [
            '',
            '',
            '',
            'تحویل در ساعات اداری باشد.',
            'قبل از ارسال تماس بگیرید.',
            'بسته‌بندی محکم باشد.',
            'فاکتور رسمی داخل بسته باشد.',
            'تحویل به نگهبانی انجام شود.',
        ];

        $usedUuids = [];

        $createOrder = function (string $status) use ($pdo, $insertOrder, $insertOrderItem, $customers, $addressesByCustomer, $products, $adminIds, $superadminIds, $orderDescriptions, $start, $now, $finalisedMax, &$usedUuids, &$ordersByCustomer): void {
            $customer = $customers[mt_rand(0, count($customers) - 1)];
            $customerId = $customer['id'];

            $addressList = $addressesByCustomer[$customerId];
            $address = $addressList[mt_rand(0, count($addressList) - 1)];

            $createdMax = $status === 'finalised' ? $finalisedMax : $now;
            $created = random_dt($start, $createdMax);

            $createdByType = 'customer';
            $createdById = $customerId;

            $creatorRoll = mt_rand(1, 100);

            if ($creatorRoll <= 20) {
                $createdByType = 'admin';
                $createdById = $adminIds[mt_rand(0, count($adminIds) - 1)];
            } elseif ($creatorRoll <= 30) {
                $createdByType = 'superadmin';
                $createdById = $superadminIds[mt_rand(0, count($superadminIds) - 1)];
            }

            $uuid = make_unique_uuid($usedUuids);

            $itemCount = mt_rand(1, 4);

            $productIndexes = range(0, count($products) - 1);
            shuffle($productIndexes);
            $selectedIndexes = array_slice($productIndexes, 0, $itemCount);

            $estimatedTotal = 0.0;
            $items = [];

            foreach ($selectedIndexes as $index) {
                $product = $products[$index];
                $quantity = qty_by_price($product['price']);
                $lineTotal = $product['price'] * $quantity;

                $estimatedTotal += $lineTotal;

                $items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $finalTotal = null;
            $finalisedAt = null;

            if ($status === 'finalised') {
                if (mt_rand(1, 100) <= 80) {
                    $finalTotal = $estimatedTotal;
                } else {
                    $changePercent = mt_rand(-5, 5) / 100;
                    $finalTotal = max(1000.0, round($estimatedTotal * (1 + $changePercent)));
                }

                $finalisedAt = $created
                    ->modify('+' . mt_rand(1, 10) . ' days')
                    ->setTime(mt_rand(8, 18), mt_rand(0, 59), 0);

                if ($finalisedAt > $now) {
                    $finalisedAt = $now->modify('-' . mt_rand(1, 20) . ' hours');
                }
            }

            $description = $orderDescriptions[mt_rand(0, count($orderDescriptions) - 1)];
            $description = $description === '' ? null : $description;

            $seenByCustomer = 1;
            $seenByAdmin = mt_rand(1, 100) <= 85 ? 1 : 0;

            $addressSnapshot = json_encode([
                'state' => $address['state'],
                'city' => $address['city'],
                'address' => $address['address'],
                'postal_code' => $address['postal_code'],
                'description' => $address['description'],
            ], JSON_UNESCAPED_UNICODE);

            $customerSnapshot = json_encode([
                'username' => $customer['username'],
                'nickname' => $customer['nickname'],
                'phone' => $customer['phone'],
            ], JSON_UNESCAPED_UNICODE);

            $updatedAt = $status === 'finalised' && $finalisedAt ? $finalisedAt : $created;

            $insertOrder->execute([
                $uuid,
                $customerId,
                $createdByType,
                $createdById,
                $status,
                $estimatedTotal,
                $finalTotal,
                $address['id'],
                $addressSnapshot,
                $customerSnapshot,
                $description,
                $finalisedAt ? to_utc($finalisedAt) : null,
                $seenByCustomer,
                $seenByAdmin,
                to_utc($created),
                to_utc($updatedAt),
            ]);

            $orderId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                $insertOrderItem->execute([
                    $orderId,
                    $item['product']['id'],
                    $item['product']['title'],
                    $item['product']['price'],
                    $item['quantity'],
                    $item['line_total'],
                    to_utc($created),
                    to_utc($created),
                ]);
            }

            $ordersByCustomer[$customerId][] = [
                'id' => $orderId,
                'uuid' => $uuid,
            ];
        };

        // Exactly 837 placed orders
        for ($i = 0; $i < 837; $i++) {
            $createOrder('placed');
        }

        // Exactly 816 finalised orders
        for ($i = 0; $i < 816; $i++) {
            $createOrder('finalised');
        }

        // ---------------------------------------------------------------
        // Tickets and messages
        // ---------------------------------------------------------------
        $ticketSubjects = [
            'پیگیری سفارش',
            'مشکل در ثبت سفارش',
            'درخواست تغییر آدرس',
            'سوال درباره قیمت',
            'درخواست فاکتور رسمی',
            'مشکل در پرداخت',
            'درخواست مرجوعی',
            'هماهنگی ارسال',
            'تغییر تعداد اقلام',
            'پیگیری وضعیت مرسوله',
        ];

        $ticketBodies = [
            'سلام، وقت بخیر. لطفاً وضعیت سفارش را بررسی کنید.',
            'سلام. در ثبت سفارش با خطا مواجه شدم. ممنون می‌شوم راهنمایی کنید.',
            'وقت بخیر. امکان تغییر آدرس تحویل وجود دارد؟',
            'سلام. لطفاً قیمت نهایی را اعلام کنید.',
            'با سلام. درخواست صدور فاکتور رسمی دارم.',
            'سلام. پرداخت انجام شد اما سفارش ثبت نشد.',
            'وقت بخیر. یکی از اقلام را اشتباه انتخاب کردم. لطفاً اصلاح کنید.',
            'سلام. لطفاً زمان ارسال را هماهنگ کنید.',
            'با سلام. تعداد اقلام را تغییر دادم. لطفاً بررسی شود.',
            'سلام. مرسوله هنوز به دست ما نرسیده است. لطفاً پیگیری کنید.',
        ];

        $customerMessages = [
            'سلام، لطفاً وضعیت را بررسی کنید.',
            'ممنون از پاسخ شما. لطفاً سریع‌تر رسیدگی شود.',
            'آیا امکان ارسال سریع وجود دارد؟',
            'متشکرم. منتظر خبر شما هستم.',
            'لطفاً فاکتور را هم ارسال کنید.',
            'آدرس تحویل را اصلاح کردم. لطفاً بررسی کنید.',
            'لطفاً قبل از ارسال تماس بگیرید.',
            'ممنون از پیگیری شما.',
        ];

        $adminMessages = [
            'سلام، درخواست شما در حال بررسی است.',
            'مورد شما بررسی شد و به واحد مربوطه ارجاع گردید.',
            'لطفاً شماره تماس خود را اعلام کنید.',
            'سفارش شما در وضعیت ثبت است و به‌زودی نهایی می‌شود.',
            'ممنون از صبوری شما. مشکل برطرف شد.',
            'لطفاً جزئیات بیشتر را ارسال کنید.',
            'درخواست شما ثبت شد و به‌زودی اطلاع‌رسانی می‌کنیم.',
            'هماهنگی لازم با واحد ارسال انجام شد.',
        ];

        $insertTicket = $pdo->prepare("
            INSERT INTO tickets (customer_id, subject, body, status, created_by_type, created_by_id, seen_by_customer, seen_by_admin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insertTicketMessage = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $insertTicketRelation = $pdo->prepare("
            INSERT INTO ticket_order_relations (ticket_id, order_id)
            VALUES (?, ?)
        ");

        $ticketCount = 180;

        for ($i = 0; $i < $ticketCount; $i++) {
            $customer = $customers[mt_rand(0, count($customers) - 1)];

            $created = random_dt($start, $ticketMax);

            $subject = $ticketSubjects[mt_rand(0, count($ticketSubjects) - 1)];
            $body = $ticketBodies[mt_rand(0, count($ticketBodies) - 1)];

            $relatedOrder = null;

            if (!empty($ordersByCustomer[$customer['id']]) && mt_rand(1, 100) <= 60) {
                $relatedOrder = $ordersByCustomer[$customer['id']][mt_rand(0, count($ordersByCustomer[$customer['id']]) - 1)];
                $subject .= ' - ' . $relatedOrder['uuid'];
                $body .= ' سفارش شماره ' . $relatedOrder['uuid'] . '.';
            }

            $status = mt_rand(1, 100) <= 35 ? 'open' : 'closed';

            $createdByType = 'customer';
            $createdById = $customer['id'];

            $creatorRoll = mt_rand(1, 100);

            if ($creatorRoll <= 25) {
                $createdByType = 'admin';
                $createdById = $adminIds[mt_rand(0, count($adminIds) - 1)];
            } elseif ($creatorRoll <= 32) {
                $createdByType = 'superadmin';
                $createdById = $superadminIds[mt_rand(0, count($superadminIds) - 1)];
            }

            $seenByCustomer = mt_rand(1, 100) <= 80 ? 1 : 0;
            $seenByAdmin = mt_rand(1, 100) <= 80 ? 1 : 0;

            $insertTicket->execute([
                $customer['id'],
                $subject,
                $body,
                $status,
                $createdByType,
                $createdById,
                $seenByCustomer,
                $seenByAdmin,
                to_utc($created),
                to_utc($created),
            ]);

            $ticketId = (int) $pdo->lastInsertId();

            if ($relatedOrder) {
                $insertTicketRelation->execute([$ticketId, $relatedOrder['id']]);
            }

            $messageCount = mt_rand(2, 8);
            $lastTime = $created;
            $lastSenderType = $createdByType;

            for ($m = 0; $m < $messageCount; $m++) {
                if ($m === 0) {
                    $senderType = $createdByType;
                    $senderId = $createdById;
                    $message = $body;
                    $time = $created;
                } else {
                    if ($lastSenderType === 'customer') {
                        $senderType = mt_rand(1, 100) <= 85 ? 'admin' : 'customer';
                    } else {
                        $senderType = mt_rand(1, 100) <= 75 ? 'customer' : 'admin';
                    }

                    if ($senderType === 'customer') {
                        $senderId = $customer['id'];
                        $message = $customerMessages[mt_rand(0, count($customerMessages) - 1)];
                    } else {
                        if (mt_rand(1, 100) <= 90) {
                            $senderType = 'admin';
                            $senderId = $adminIds[mt_rand(0, count($adminIds) - 1)];
                        } else {
                            $senderType = 'superadmin';
                            $senderId = $superadminIds[mt_rand(0, count($superadminIds) - 1)];
                        }

                        $message = $adminMessages[mt_rand(0, count($adminMessages) - 1)];
                    }

                    $time = $lastTime->modify('+' . mt_rand(1, 72) . ' hours');

                    if ($time > $now) {
                        $time = $now;
                    }
                }

                $insertTicketMessage->execute([
                    $ticketId,
                    $senderType,
                    $senderId,
                    $message,
                    to_utc($time),
                ]);

                $lastTime = $time;
                $lastSenderType = $senderType;
            }

            $pdo->prepare("UPDATE tickets SET updated_at = ? WHERE id = ?")
                ->execute([to_utc($lastTime), $ticketId]);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب و درج داده نمونه</title>
        <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: #f5f6f8;
            color: #1f2937;
            padding: 24px;
            line-height: 1.9;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        h1 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .err {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .warn {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            font-size: .88rem;
            text-align: right;
        }

        th {
            background: #f9fafb;
        }

        .btn {
            display: inline-block;
            background: #1d4ed8;
            color: #fff;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: .88rem;
            font-weight: 700;
        }

        .btn-danger {
            background: #be123c;
        }

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: .85rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .stat {
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .stat strong {
            display: block;
            font-size: 1.05rem;
        }

        .muted {
            color: #6b7280;
            font-size: .82rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1>نصب و درج داده نمونه</h1>

            <?php if (!empty($error)): ?>
                <div class="err"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($seededNow): ?>
                <div class="ok">
                    نصب و درج داده نمونه با موفقیت انجام شد.
                </div>
            <?php elseif (!$fresh && !empty($stats) && (int) ($stats['customers'] ?? 0) > 0 && !$alreadySeeded): ?>
                <div class="warn">
                    دیتابیس دارای داده موجود است ولی هنوز داده نمونه درج نشده است.
                    برای حذف داده‌های فعلی و درج کامل داده نمونه، این آدرس را باز کنید:
                    <br><br>
                    <code>setup.php?fresh=1</code>
                </div>
            <?php elseif (!$seededNow && empty($error)): ?>
                <div class="ok">
                    دیتابیس آماده است. داده نمونه قبلاً درج شده است.
                </div>
            <?php endif; ?>

            <p class="muted">
                اگر می‌خواهید دیتابیس دوباره از صفر پر شود، از آدرس
                <code>setup.php?fresh=1</code>
                استفاده کنید. توجه کنید که این کار تمام داده‌ها را حذف و دوباره درج می‌کند.
            </p>

            <div style="margin-top: 12px;">
                <a class="btn" href="/">ورود به سامانه</a>
                <a class="btn btn-danger" href="setup.php?fresh=1"
                    onclick="return confirm('تمام داده‌ها حذف و دوباره درج می‌شوند. ادامه می‌دهید؟');">
                    درج مجدد داده نمونه
                </a>
            </div>
        </div>

        <?php if (!empty($stats)): ?>
            <div class="card">
                <h2>آمار داده‌های فعلی</h2>
                <div class="grid">
                    <div class="stat"><span>مدیران
                            ارشد</span><strong><?= fa_digits((string) $stats['superadmins']) ?></strong></div>
                    <div class="stat"><span>مدیران</span><strong><?= fa_digits((string) $stats['admins']) ?></strong></div>
                    <div class="stat"><span>مشتریان</span><strong><?= fa_digits((string) $stats['customers']) ?></strong>
                    </div>
                    <div class="stat"><span>محصولات</span><strong><?= fa_digits((string) $stats['products']) ?></strong>
                    </div>
                    <div class="stat"><span>آدرس‌ها</span><strong><?= fa_digits((string) $stats['addresses']) ?></strong>
                    </div>
                    <div class="stat"><span>سفارشات ثبت
                            شده</span><strong><?= fa_digits((string) $stats['placed_orders']) ?></strong></div>
                    <div class="stat"><span>سفارشات نهایی
                            شده</span><strong><?= fa_digits((string) $stats['finalised_orders']) ?></strong></div>
                    <div class="stat"><span>اقلام
                            سفارشات</span><strong><?= fa_digits((string) $stats['order_items']) ?></strong></div>
                    <div class="stat"><span>تیکت‌ها</span><strong><?= fa_digits((string) $stats['tickets']) ?></strong></div>
                    <div class="stat"><span>پیام‌های
                            تیکت</span><strong><?= fa_digits((string) $stats['ticket_messages']) ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>حساب‌های تستی</h2>

            <table>
                <thead>
                    <tr>
                        <th>نوع حساب</th>
                        <th>نام کاربری</th>
                        <th>رمز عبور</th>
                        <th>توضیح</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>مدیر ارشد</td>
                        <td>superadmin</td>
                        <td>123456</td>
                        <td>دسترسی کامل</td>
                    </tr>
                    <tr>
                        <td>مدیر ارشد</td>
                        <td>superadmin2</td>
                        <td>123456</td>
                        <td>دسترسی کامل</td>
                    </tr>

                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <tr>
                            <td>مدیر</td>
                            <td>admin<?= $i ?></td>
                            <td>123456</td>
                            <td>دسترسی مدیر</td>
                        </tr>
                    <?php endfor; ?>

                    <?php for ($i = 1; $i <= 25; $i++): ?>
                        <tr>
                            <td>مشتری</td>
                            <td>customer<?= $i ?></td>
                            <td>123456</td>
                            <td>حساب مشتری</td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>نکته امنیتی</h2>
            <p>
                بعد از نصب و تست، حتماً فایل <code>setup.php</code> را حذف یا دسترسی آن را محدود کنید.
            </p>
        </div>
    </div>
</body>

</html>