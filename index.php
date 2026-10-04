<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/routes_auth.php';
require_once __DIR__ . '/routes_admin.php';
require_once __DIR__ . '/routes_shops.php';
require_once __DIR__ . '/routes_shop_settings.php';
require_once __DIR__ . '/routes_inventory.php';
require_once __DIR__ . '/routes_accounting.php';
require_once __DIR__ . '/routes_orders.php';
require_once __DIR__ . '/routes_tickets.php';
require_once __DIR__ . '/routes_cart.php';
require_once __DIR__ . '/routes_bookmarks.php';
require_once __DIR__ . '/routes_public.php';
require_once __DIR__ . '/routes_public_products.php';
require_once __DIR__ . '/routes_sync.php';
require_once __DIR__ . '/routes_reports.php';

// Global request rate limiting to protect server against overload (120 req / 60s)
throttle_request('global', 120, 60);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

if ($base && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}

$path = '/' . ltrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

$matched = false;

foreach ($GLOBALS['routes'] as $route) {
    if ($route['method'] !== $method && $route['method'] !== 'GET|POST') {
        continue;
    }

    if (preg_match($route['pattern'], $path, $matches)) {
        array_shift($matches);
        $route['handler'](...$matches);
        $matched = true;
        break;
    }
}

if (!$matched) {
    error_page(404, 'صفحه یافت نشد', 'آدرس وارد شده معتبر نیست.');
}