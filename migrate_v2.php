<?php
declare(strict_types=1);

/**
 * migrate_v2.php
 * Comprehensive schema migration for Befroosh Platform Overhaul
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Starting Befroosh Migration v2 ===\n";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Back up database first
$backupPath = STORAGE_PATH . '/database.sqlite.v2.bak';
if (copy(DB_PATH, $backupPath)) {
    echo "[✓] Database backed up to: {$backupPath}\n";
} else {
    die("[!] Failed to create database backup before migration.\n");
}

$pdo->exec('PRAGMA foreign_keys = OFF');

// Helper to check column existence
$hasCol = function (string $table, string $column) use ($pdo): bool {
    $cols = array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
    return in_array($column, $cols, true);
};

// Helper to safely add column
$addCol = function (string $table, string $column, string $def) use ($pdo, $hasCol) {
    if (!$hasCol($table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$def}");
        echo "[+] Added column {$table}.{$column}\n";
    }
};

// -------------------------------------------------------------
// 2. Rebuild USERS table with new role CHECK constraint & fields
// -------------------------------------------------------------
echo "[*] Migrating users table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users_v2 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        nickname TEXT NOT NULL,
        first_name TEXT NULL,
        last_name TEXT NULL,
        role TEXT NOT NULL CHECK(role IN ('superadmin','admin','shop_owner','shop_manager','customer')),
        shop_id INTEGER NULL REFERENCES shops(id),
        phone TEXT UNIQUE NULL,
        national_code TEXT NULL,
        notes TEXT NULL,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        deleted_at TEXT NULL
    );
");

// Fetch existing users and migrate with phone normalization and name splitting
$users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
$insUser = $pdo->prepare("
    INSERT INTO users_v2 (id, username, password, nickname, first_name, last_name, role, shop_id, phone, national_code, notes, active, created_at, updated_at, deleted_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($users as $u) {
    $nick = trim($u['nickname'] ?? '');
    $parts = explode(' ', $nick, 2);
    $firstName = $u['first_name'] ?? $parts[0] ?? '';
    $lastName = $u['last_name'] ?? ($parts[1] ?? '');

    // Normalize phone: strip spaces, dashes, +98, and leading 0 -> e.g. 09121000001 -> 9121000001
    $rawPhone = preg_replace('/[^\d]/', '', $u['phone'] ?? '');
    if (str_starts_with($rawPhone, '98') && strlen($rawPhone) === 12) {
        $rawPhone = substr($rawPhone, 2);
    }
    if (str_starts_with($rawPhone, '0') && strlen($rawPhone) === 11) {
        $rawPhone = substr($rawPhone, 1);
    }
    $normPhone = (strlen($rawPhone) === 10 && str_starts_with($rawPhone, '9')) ? $rawPhone : null;
    if (!$normPhone) {
        // Fallback for demo users if empty
        $normPhone = '9' . str_pad((string)$u['id'], 9, '0', STR_PAD_LEFT);
    }

    $role = $u['role'];
    if ($role === 'admin' && !empty($u['shop_id'])) {
        $role = 'shop_owner';
    }

    $insUser->execute([
        $u['id'],
        $u['username'],
        $u['password'],
        $nick,
        $firstName,
        $lastName,
        $role,
        $u['shop_id'] ?? null,
        $normPhone,
        $u['national_code'] ?? null,
        $u['notes'] ?? null,
        $u['active'] ?? 1,
        $u['created_at'] ?? date('Y-m-d H:i:s'),
        $u['updated_at'] ?? date('Y-m-d H:i:s'),
        $u['deleted_at'] ?? null,
    ]);
}

$pdo->exec("DROP TABLE users");
$pdo->exec("ALTER TABLE users_v2 RENAME TO users");
echo "[✓] Users table successfully updated with phone normalization & name fields\n";

// -------------------------------------------------------------
// 3. Rebuild ORDERS table with expanded status and created_by_type
// -------------------------------------------------------------
echo "[*] Migrating orders table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS orders_v2 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE NOT NULL,
        shop_id INTEGER NULL REFERENCES shops(id),
        customer_id INTEGER NOT NULL REFERENCES users(id),
        created_by_type TEXT NOT NULL CHECK(created_by_type IN ('customer','shop_owner','shop_manager','admin','superadmin')),
        created_by_id INTEGER NULL REFERENCES users(id),
        status TEXT NOT NULL CHECK(status IN ('submitted','paid','shipped','completed','canceled','placed','finalised')),
        subtotal REAL DEFAULT 0,
        shipping_cost REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        discount_amount REAL DEFAULT 0,
        estimated_total REAL NOT NULL DEFAULT 0,
        final_total REAL NULL,
        payment_method TEXT DEFAULT 'card_to_card',
        payment_status TEXT DEFAULT 'unpaid',
        payment_card_id INTEGER NULL,
        payment_receipt_path TEXT NULL,
        payment_reference TEXT NULL,
        payment_reject_reason TEXT NULL,
        paid_at TEXT NULL,
        reservation_expires_at TEXT NULL,
        shipping_method TEXT DEFAULT 'پست پیشتاز',
        tracking_code TEXT NULL,
        shipped_at TEXT NULL,
        delivered_at TEXT NULL,
        completed_at TEXT NULL,
        canceled_at TEXT NULL,
        cancellation_reason TEXT NULL,
        finalised_at TEXT NULL,
        address_id INTEGER NULL,
        address_snapshot TEXT,
        customer_snapshot TEXT,
        description TEXT NULL,
        seen_by_customer INTEGER DEFAULT 1,
        seen_by_admin INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );
");

// Copy existing orders into orders_v2
$pdo->exec("
    INSERT INTO orders_v2 (
        id, uuid, shop_id, customer_id, created_by_type, created_by_id, status,
        subtotal, shipping_cost, tax_amount, discount_amount, estimated_total, final_total,
        payment_method, payment_status, payment_receipt_path, payment_reference, payment_reject_reason,
        paid_at, shipping_method, tracking_code, shipped_at, delivered_at, completed_at, canceled_at,
        cancellation_reason, finalised_at, address_id, address_snapshot, customer_snapshot, description,
        seen_by_customer, seen_by_admin, created_at, updated_at
    )
    SELECT 
        id, uuid, COALESCE(shop_id, 1), customer_id, created_by_type, created_by_id, status,
        COALESCE(subtotal, 0), COALESCE(shipping_cost, 0), COALESCE(tax_amount, 0), COALESCE(discount_amount, 0),
        estimated_total, final_total, COALESCE(payment_method, 'card_to_card'), COALESCE(payment_status, 'unpaid'),
        payment_receipt_path, payment_reference, payment_reject_reason, paid_at,
        COALESCE(shipping_method, 'پست پیشتاز'), tracking_code, shipped_at, delivered_at, completed_at, canceled_at,
        cancellation_reason, finalised_at, address_id, address_snapshot, customer_snapshot, description,
        seen_by_customer, seen_by_admin, created_at, updated_at
    FROM orders
");

$pdo->exec("DROP TABLE orders");
$pdo->exec("ALTER TABLE orders_v2 RENAME TO orders");
echo "[✓] Orders table successfully updated with reservation and tracking fields\n";

// -------------------------------------------------------------
// 4. Update PRODUCTS table
// -------------------------------------------------------------
echo "[*] Updating products table...\n";
$addCol('products', 'max_per_order', 'REAL DEFAULT 0');
$addCol('products', 'max_per_month', 'REAL DEFAULT 0');
$addCol('products', 'slug', 'TEXT NULL');

// Backfill products.slug from title
$products = $pdo->query("SELECT id, title FROM products WHERE slug IS NULL OR slug = ''")->fetchAll(PDO::FETCH_ASSOC);
$updProdSlug = $pdo->prepare("UPDATE products SET slug = ? WHERE id = ?");
foreach ($products as $p) {
    $cleanSlug = mb_substr(trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $p['title'])), 0, 150);
    $updProdSlug->execute([$cleanSlug ?: ('product-' . $p['id']), $p['id']]);
}
echo "[✓] Products table updated with purchase limit caps and UTF-8 slugs\n";

// -------------------------------------------------------------
// 5. Update SHOPS table
// -------------------------------------------------------------
echo "[*] Updating shops table...\n";
$addCol('shops', 'reservation_days', 'INTEGER DEFAULT 4');
$addCol('shops', 'payment_methods', "TEXT DEFAULT '[\"card_to_card\"]'");

// -------------------------------------------------------------
// 6. Create SHOP_BANK_CARDS table
// -------------------------------------------------------------
echo "[*] Creating shop_bank_cards table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS shop_bank_cards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NOT NULL REFERENCES shops(id),
        card_number TEXT NOT NULL,
        card_holder TEXT NOT NULL,
        bank_name TEXT NULL,
        shaba_number TEXT NULL,
        active INTEGER DEFAULT 1,
        expires_at TEXT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );
");

// Seed bank cards from existing shops if empty
$cardCount = (int)$pdo->query("SELECT COUNT(*) FROM shop_bank_cards")->fetchColumn();
if ($cardCount === 0) {
    $shops = $pdo->query("SELECT id, card_number, card_holder, bank_name, shaba_number FROM shops WHERE card_number IS NOT NULL AND card_number != ''")->fetchAll(PDO::FETCH_ASSOC);
    $insCard = $pdo->prepare("
        INSERT INTO shop_bank_cards (shop_id, card_number, card_holder, bank_name, shaba_number, active, expires_at)
        VALUES (?, ?, ?, ?, ?, 1, '1406/12/29')
    ");
    foreach ($shops as $sh) {
        $insCard->execute([$sh['id'], $sh['card_number'], $sh['card_holder'] ?: 'مدیریت فروشگاه', $sh['bank_name'] ?: 'بانک ملی ایران', $sh['shaba_number']]);
    }
    echo "[✓] Seeded initial bank cards from shops\n";
}

// -------------------------------------------------------------
// 7. Create CART_ITEMS table (7-day per-shop customer cart)
// -------------------------------------------------------------
echo "[*] Creating cart_items table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cart_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        shop_id INTEGER NOT NULL REFERENCES shops(id),
        product_id INTEGER NOT NULL REFERENCES products(id),
        quantity REAL NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        expires_at TEXT NOT NULL,
        UNIQUE(user_id, product_id)
    );
");

// -------------------------------------------------------------
// 8. Create PRODUCT_BOOKMARKS table (per-shop wishlist)
// -------------------------------------------------------------
echo "[*] Creating product_bookmarks table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_bookmarks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        shop_id INTEGER NOT NULL REFERENCES shops(id),
        product_id INTEGER NOT NULL REFERENCES products(id),
        created_at TEXT DEFAULT (datetime('now')),
        UNIQUE(user_id, product_id)
    );
");

// -------------------------------------------------------------
// 9. Create SYSTEM_REPORTS table (superadmin & admin incident tracking)
// -------------------------------------------------------------
echo "[*] Creating system_reports table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS system_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reporter_id INTEGER NOT NULL REFERENCES users(id),
        shop_id INTEGER NULL REFERENCES shops(id),
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT DEFAULT 'general',
        severity TEXT DEFAULT 'normal',
        status TEXT DEFAULT 'open',
        created_at TEXT DEFAULT (datetime('now')),
        resolved_at TEXT NULL
    );
");

// -------------------------------------------------------------
// 10. Performance Indexes
// -------------------------------------------------------------
echo "[*] Ensuring performance indexes...\n";
$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
    CREATE INDEX IF NOT EXISTS idx_users_role_shop ON users(role, shop_id);
    CREATE INDEX IF NOT EXISTS idx_orders_customer_status ON orders(customer_id, status);
    CREATE INDEX IF NOT EXISTS idx_orders_shop_status ON orders(shop_id, status);
    CREATE INDEX IF NOT EXISTS idx_orders_reservation ON orders(status, reservation_expires_at);
    CREATE INDEX IF NOT EXISTS idx_cart_user_shop ON cart_items(user_id, shop_id);
    CREATE INDEX IF NOT EXISTS idx_cart_expires ON cart_items(expires_at);
    CREATE INDEX IF NOT EXISTS idx_bookmarks_user ON product_bookmarks(user_id);
    CREATE INDEX IF NOT EXISTS idx_shop_cards_shop ON shop_bank_cards(shop_id, active);
    CREATE INDEX IF NOT EXISTS idx_reports_status ON system_reports(status, severity);
");

$pdo->exec('PRAGMA foreign_keys = ON');

echo "\n[SUCCESS] Befroosh Migration v2 completed successfully!\n";
