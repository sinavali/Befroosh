<?php
declare(strict_types=1);

/**
 * routes_admin.php
 * Administrative Modules Aggregator
 * - routes_customers.php: Customers & Delivery Addresses Management
 * - routes_products.php: Products, Barcodes, Inventory Caps & Categories Management
 * - routes_users.php: Staff Management, Elevation, Impersonation & System Settings
 */

require_once __DIR__ . '/routes_customers.php';
require_once __DIR__ . '/routes_products.php';
require_once __DIR__ . '/routes_users.php';