<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DB_PATH', STORAGE_PATH . '/database.sqlite');
define('TICKET_UPLOAD_PATH', STORAGE_PATH . '/tickets');
define('PRODUCT_UPLOAD_PATH', STORAGE_PATH . '/products');
define('RECEIPT_UPLOAD_PATH', STORAGE_PATH . '/receipts');
define('LOCK_FILE', STORAGE_PATH . '/installed.lock');

if (file_exists(LOCK_FILE) && !isset($_GET['fresh']) && !isset($_GET['unlock_token'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>سیستم راه‌اندازی شده است</title><style>body{font-family:Tahoma,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:480px;text-align:center;color:#1f2937;}h1{color:#059669;font-size:1.4rem;margin-bottom:1rem;}p{line-height:1.6;color:#4b5563;margin-bottom:1.5rem;}.btn{display:inline-block;background:#2563eb;color:#fff;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:bold;margin:4px;}</style></head><body><div class="card"><h1>سیستم قبلاً راه‌اندازی شده است</h1><p>پایگاه داده و جداول سیستم در دسترس هستند. برای نصب مجدد می‌توانید از کلید زیر یا حذف فایل قفل استفاده نمایید.</p><a href="/" class="btn">ورود به سامانه</a><a href="setup.php?fresh=1" class="btn" style="background:#dc2626;" onclick="return confirm(\'آیا از بازنشانی کامل دیتابیس مطمئن هستید؟\')">بازنشانی و نصب مجدد</a></div></body></html>';
    exit;
}

require_once __DIR__ . '/setup_schema.php';
require_once __DIR__ . '/setup_seed.php';

$fresh = isset($_GET['fresh']);
$seededNow = false;
$error = '';
$stats = [];

try {
    foreach ([STORAGE_PATH, TICKET_UPLOAD_PATH, PRODUCT_UPLOAD_PATH, RECEIPT_UPLOAD_PATH] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userCols = [];
    try {
        $userCols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    } catch (Throwable $e) {}

    if ($fresh || empty($userCols) || !in_array('shop_id', $userCols, true)) {
        drop_all_platform_tables($pdo);
        $fresh = true;
    }

    install_platform_schema($pdo);

    $hasUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
    if ($fresh || !$hasUsers) {
        seed_platform_demo($pdo);
        $seededNow = true;
    }

    @file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));

    $stats = [
        'shops' => (int) $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn(),
        'users' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'products' => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'categories' => (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        'tickets' => (int) $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    ];
} catch (Throwable $ex) {
    $error = 'خطا در عملیات راه‌اندازی دیتابیس: ' . $ex->getMessage();
}

function e_setup(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function fa_num(int $n): string {
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>راه‌اندازی و نصب سامانه چندفروشگاهی بفروش</title>
    <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f3f4f6; color: #1f2937; padding: 24px; line-height: 1.8; }
        .container { max-width: 860px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        h1 { font-size: 1.35rem; margin-bottom: 12px; color: #111827; }
        h2 { font-size: 1.1rem; margin-bottom: 14px; color: #374151; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: bold; }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-top: 14px; }
        .stat-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; text-align: center; }
        .stat-item strong { display: block; font-size: 1.3rem; color: #1d4ed8; }
        .stat-item span { font-size: 0.85rem; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9rem; }
        th, td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
        th { background: #f9fafb; color: #374151; }
        .btn { display: inline-flex; align-items: center; justify-content: center; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: bold; margin-left: 8px; }
        .btn:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.88rem; direction: ltr; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>سامانه چندفروشگاهی بَفروش</h1>
            <?php if (!empty($error)): ?>
                <div class="err"><?= e_setup($error) ?></div>
            <?php else: ?>
                <div class="ok">
                    <?= $seededNow ? 'پایگاه داده چندفروشگاهی و تمامی داده‌های اولیه با موفقیت ایجاد و نصب گردیدند.' : 'سامانه آماده و فعال است.' ?>
                </div>
            <?php endif; ?>

            <p style="color:#4b5563; font-size:0.92rem; margin-bottom: 16px;">
                معماری چندفروشگاهی، موجودی انبار، اسناد دوبل حسابداری، تسویه‌حساب کارت‌به‌کارت و رهگیری سفارشات فعال هستند.
            </p>

            <div>
                <a class="btn" href="/">ورود به سامانه</a>
                <a class="btn" href="/shops" style="background:#059669;">ویترین عمومی فروشگاه‌ها</a>
                <a class="btn btn-danger" href="setup.php?fresh=1" onclick="return confirm('تمامی اطلاعات قبلی حذف و جداول از نو ساخته می‌شوند. ادامه می‌دهید؟');">
                    نصب مجدد (Fresh)
                </a>
            </div>
        </div>

        <?php if (!empty($stats)): ?>
            <div class="card">
                <h2>آمار موجودیت‌های نصب شده</h2>
                <div class="grid">
                    <div class="stat-item"><strong><?= fa_num($stats['shops']) ?></strong><span>فروشگاه فعال</span></div>
                    <div class="stat-item"><strong><?= fa_num($stats['users']) ?></strong><span>کاربر و مشتری</span></div>
                    <div class="stat-item"><strong><?= fa_num($stats['products']) ?></strong><span>کالا و محصول</span></div>
                    <div class="stat-item"><strong><?= fa_num($stats['categories']) ?></strong><span>دسته‌بندی</span></div>
                    <div class="stat-item"><strong><?= fa_num($stats['orders']) ?></strong><span>سفارش نمونه</span></div>
                    <div class="stat-item"><strong><?= fa_num($stats['tickets']) ?></strong><span>تیکت پشتیبانی</span></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>حساب‌های کاربری پیش‌فرض جهت تست</h2>
            <table>
                <thead>
                    <tr>
                        <th>نقش دسترسی</th>
                        <th>نام کاربری</th>
                        <th>رمز عبور</th>
                        <th>توضیحات و دسترسی</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>مدیر کل سامانه (Super Admin)</strong></td>
                        <td><code>admin</code> یا <code>superadmin</code></td>
                        <td><code>admin123</code> / <code>123456</code></td>
                        <td>دسترسی فراگیر به تمامی شعب، گزارشات و بازرسی کل</td>
                    </tr>
                    <tr>
                        <td><strong>مالک فروشگاه ۱ (Shop Owner)</strong></td>
                        <td><code>admin1</code> یا <code>owner_tech</code></td>
                        <td><code>123456</code> / <code>owner123</code></td>
                        <td>مدیریت کامل فروشگاه مرکزی، کالاها، انبار و حسابداری</td>
                    </tr>
                    <tr>
                        <td><strong>مدیر فروشگاه ۱ (Shop Manager)</strong></td>
                        <td><code>admin2</code> یا <code>manager_tech</code></td>
                        <td><code>123456</code> / <code>manager123</code></td>
                        <td>رسیدگی به سفارشات، انبارداری و پاسخگویی تیکت‌ها</td>
                    </tr>
                    <tr>
                        <td><strong>مالک فروشگاه ۲ (Shop Owner)</strong></td>
                        <td><code>admin3</code> یا <code>owner_toranj</code></td>
                        <td><code>123456</code> / <code>owner123</code></td>
                        <td>مدیریت کامل بوتیک و مد ترنج</td>
                    </tr>
                    <tr>
                        <td><strong>مشتری (Customer)</strong></td>
                        <td><code>customer1</code> تا <code>customer16</code></td>
                        <td><code>customer123</code> / <code>123456</code></td>
                        <td>خرید از همه فروشگاه‌ها، سبد خرید، کارت‌به‌کارت و پیگیری مرسوله</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>