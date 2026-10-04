<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DB_PATH', STORAGE_PATH . '/database.sqlite');
define('TICKET_UPLOAD_PATH', STORAGE_PATH . '/tickets');
define('PRODUCT_UPLOAD_PATH', STORAGE_PATH . '/products');
define('RECEIPT_UPLOAD_PATH', STORAGE_PATH . '/receipts');
define('SESSION_TIMEOUT', 30 * 86400); // 30 days session
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_MINUTES', 5);

foreach ([STORAGE_PATH, TICKET_UPLOAD_PATH, PRODUCT_UPLOAD_PATH, RECEIPT_UPLOAD_PATH] as $dir) {
    if (!is_dir($dir))
        @mkdir($dir, 0755, true);
}

if (!is_file(DB_PATH)) {
    header('Location: /setup.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)(30 * 86400));
    session_name('BEFROOSH_SESS');
    session_set_cookie_params([
        'lifetime' => 30 * 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    $pdo->query("SELECT COUNT(*) FROM users LIMIT 1")->fetchColumn();
} catch (Throwable $ex) {
    header('Location: /setup.php');
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$GLOBALS['routes'] = [];