<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "=== Starting Multi-Tenant Database Migration ===\n";

$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->beginTransaction();

try {
    // 1. Create shops table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shops (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            owner_id INTEGER NULL REFERENCES users(id),
            phone TEXT NULL,
            email TEXT NULL,
            address TEXT NULL,
            national_id TEXT NULL,
            economic_code TEXT NULL,
            card_number TEXT NULL,
            card_holder TEXT NULL,
            bank_name TEXT NULL,
            card_to_card_enabled INTEGER DEFAULT 1,
            tax_rate REAL DEFAULT 0.0,
            default_shipping_cost REAL DEFAULT 250000,
            free_shipping_threshold REAL DEFAULT 2000000,
            active INTEGER DEFAULT 1,
            description TEXT NULL,
            logo_path TEXT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        )
    ");
    echo "[✓] shops table verified\n";

    // 2. Ensure initial default shop exists
    $shopCount = (int) $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn();
    if ($shopCount === 0) {
        $firstAdmin = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' LIMIT 1")->fetchColumn();
        $ownerId = $firstAdmin ? (int) $firstAdmin : 1;

        $stmt = $pdo->prepare("
            INSERT INTO shops (id, name, slug, owner_id, phone, address, card_number, card_holder, bank_name, tax_rate, default_shipping_cost, free_shipping_threshold, active, description)
            VALUES (1, 'فروشگاه مرکزی بَفروش', 'central', ?, '02188888888', 'تهران، خیابان آزادی، پلاک ۱', '6037997123456789', 'مدیریت فروشگاه مرکزی', 'بانک ملی ایران', 0.10, 250000, 2500000, 1, 'فروشگاه اصلی و مرکزی بستر بفروش')
        ");
        $stmt->execute([$ownerId]);
        echo "[✓] Created default shop (id=1)\n";
    }

    // 3. Create categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            parent_id INTEGER NULL REFERENCES categories(id),
            sort_order INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");
    echo "[✓] categories table verified\n";

    // Seed some categories for shop 1 if empty
    $catCount = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount === 0) {
        $cats = ['کالای دیجیتال', 'لوازم اداری و مصرفی', 'پوشاک و مد', 'خانه و آشپزخانه'];
        $insCat = $pdo->prepare("INSERT INTO categories (shop_id, name, slug, sort_order) VALUES (1, ?, ?, ?)");
        $order = 1;
        foreach ($cats as $c) {
            $slug = 'cat-' . $order;
            $insCat->execute([$c, $slug, $order++]);
        }
        echo "[✓] Seeded initial categories\n";
    }

    // 4. Create inventory_transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            product_id INTEGER NOT NULL REFERENCES products(id),
            type TEXT NOT NULL CHECK(type IN ('inward','outward_order','adjustment_plus','adjustment_minus','return_in','write_off')),
            quantity REAL NOT NULL,
            unit_cost REAL NULL,
            reference_type TEXT NULL,
            reference_id INTEGER NULL,
            notes TEXT NULL,
            created_by_id INTEGER NULL REFERENCES users(id),
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");
    echo "[✓] inventory_transactions table verified\n";

    // 5. Create accounting_ledger table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accounting_ledger (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            entry_date TEXT DEFAULT (datetime('now')),
            entry_type TEXT NOT NULL CHECK(entry_type IN ('sale_revenue','cogs','shipping_revenue','tax_payable','payment_received','discount_expense','manual_entry','adjustment')),
            order_id INTEGER NULL REFERENCES orders(id),
            debit REAL NOT NULL DEFAULT 0,
            credit REAL NOT NULL DEFAULT 0,
            account TEXT NOT NULL,
            description TEXT NOT NULL,
            created_by_id INTEGER NULL REFERENCES users(id),
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");
    echo "[✓] accounting_ledger table verified\n";

    // 6. Create rate_limits table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            key TEXT PRIMARY KEY,
            hits INTEGER NOT NULL DEFAULT 1,
            reset_at INTEGER NOT NULL
        )
    ");
    echo "[✓] rate_limits table verified\n";

    // Helper function to add columns safely to SQLite tables
    $addCol = function (string $table, string $column, string $def) use ($pdo) {
        $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array($column, $names, true)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$def}");
            echo "[+] Added column {$table}.{$column}\n";
        }
    };

    // 7. Add columns to users
    $addCol('users', 'shop_id', 'INTEGER NULL REFERENCES shops(id)');
    $addCol('users', 'national_code', 'TEXT NULL');

    // Update existing admin users to belong to shop 1
    $pdo->exec("UPDATE users SET shop_id = 1 WHERE role = 'admin' AND shop_id IS NULL");
    // Ensure superadmins have NULL shop_id (platform-wide access)
    $pdo->exec("UPDATE users SET shop_id = NULL WHERE role = 'superadmin'");

    // 8. Add columns to products
    $addCol('products', 'shop_id', 'INTEGER NULL REFERENCES shops(id)');
    $addCol('products', 'category_id', 'INTEGER NULL REFERENCES categories(id)');
    $addCol('products', 'barcode', 'TEXT NULL');
    $addCol('products', 'unit', "TEXT DEFAULT 'عدد'");
    $addCol('products', 'weight_grams', 'INTEGER DEFAULT 0');
    $addCol('products', 'cost_price', 'REAL DEFAULT 0');
    $addCol('products', 'stock_quantity', 'REAL DEFAULT 50');
    $addCol('products', 'min_stock_alert', 'REAL DEFAULT 5');
    $addCol('products', 'min_order_qty', 'REAL DEFAULT 1');
    $addCol('products', 'tax_rate', 'REAL DEFAULT 0.0');

    // Update existing products with shop_id = 1 and simulated cost prices
    $pdo->exec("UPDATE products SET shop_id = 1 WHERE shop_id IS NULL");
    $pdo->exec("UPDATE products SET unit = 'عدد' WHERE unit IS NULL");
    $pdo->exec("UPDATE products SET cost_price = ROUND(price * 0.75) WHERE cost_price = 0 OR cost_price IS NULL");
    $pdo->exec("UPDATE products SET category_id = 1 WHERE category_id IS NULL");
    $pdo->exec("UPDATE products SET stock_quantity = 50 WHERE stock_quantity IS NULL OR stock_quantity = 0");

    // 9. Add columns to orders
    $addCol('orders', 'shop_id', 'INTEGER NULL REFERENCES shops(id)');
    $addCol('orders', 'subtotal', 'REAL DEFAULT 0');
    $addCol('orders', 'shipping_cost', 'REAL DEFAULT 0');
    $addCol('orders', 'tax_amount', 'REAL DEFAULT 0');
    $addCol('orders', 'discount_amount', 'REAL DEFAULT 0');
    $addCol('orders', 'payment_method', "TEXT DEFAULT 'card_to_card'");
    $addCol('orders', 'payment_status', "TEXT DEFAULT 'unpaid'");
    $addCol('orders', 'payment_receipt_path', 'TEXT NULL');
    $addCol('orders', 'payment_reference', 'TEXT NULL');
    $addCol('orders', 'payment_reject_reason', 'TEXT NULL');
    $addCol('orders', 'paid_at', 'TEXT NULL');
    $addCol('orders', 'shipping_method', "TEXT DEFAULT 'پست پیشتاز'");
    $addCol('orders', 'tracking_code', 'TEXT NULL');
    $addCol('orders', 'shipped_at', 'TEXT NULL');
    $addCol('orders', 'delivered_at', 'TEXT NULL');

    // Backfill orders
    $pdo->exec("UPDATE orders SET shop_id = 1 WHERE shop_id IS NULL");
    $pdo->exec("UPDATE orders SET subtotal = COALESCE(final_total, estimated_total, 0) WHERE subtotal = 0 OR subtotal IS NULL");
    $pdo->exec("UPDATE orders SET payment_method = 'card_to_card' WHERE payment_method IS NULL");
    $pdo->exec("
        UPDATE orders 
        SET payment_status = CASE 
            WHEN status = 'completed' THEN 'paid'
            WHEN status = 'finalised' THEN 'paid'
            WHEN status = 'canceled' THEN 'rejected'
            ELSE 'unpaid'
        END
        WHERE payment_status IS NULL OR payment_status = 'unpaid'
    ");
    $pdo->exec("UPDATE orders SET paid_at = finalised_at WHERE paid_at IS NULL AND finalised_at IS NOT NULL");

    // 10. Add columns to order_items
    $addCol('order_items', 'product_sku', 'TEXT NULL');
    $addCol('order_items', 'unit', "TEXT DEFAULT 'عدد'");
    $addCol('order_items', 'unit_cost_price', 'REAL DEFAULT 0');
    $addCol('order_items', 'tax_amount', 'REAL DEFAULT 0');

    // Backfill order_items with sku and cost price from product
    $pdo->exec("
        UPDATE order_items 
        SET product_sku = (SELECT sku FROM products WHERE products.id = order_items.product_id),
            unit_cost_price = ROUND(order_items.unit_price * 0.75),
            unit = 'عدد'
        WHERE product_sku IS NULL OR unit_cost_price = 0
    ");

    // 11. Add columns to tickets
    $addCol('tickets', 'shop_id', 'INTEGER NULL REFERENCES shops(id)');
    $pdo->exec("UPDATE tickets SET shop_id = 1 WHERE shop_id IS NULL");

    // 12. Add performance indexes
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_orders_shop_status ON orders(shop_id, status);
        CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id, status);
        CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items(product_id);
        CREATE INDEX IF NOT EXISTS idx_products_shop ON products(shop_id, active);
        CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
        CREATE INDEX IF NOT EXISTS idx_tickets_shop ON tickets(shop_id, status);
        CREATE INDEX IF NOT EXISTS idx_tickets_customer ON tickets(customer_id, status);
        CREATE INDEX IF NOT EXISTS idx_ticket_messages_ticket ON ticket_messages(ticket_id);
        CREATE INDEX IF NOT EXISTS idx_inv_tx_shop_prod ON inventory_transactions(shop_id, product_id);
        CREATE INDEX IF NOT EXISTS idx_accounting_shop_date ON accounting_ledger(shop_id, entry_date);
        CREATE INDEX IF NOT EXISTS idx_users_shop ON users(shop_id);
        CREATE INDEX IF NOT EXISTS idx_categories_shop ON categories(shop_id);
    ");
    echo "[✓] Created performance indexes\n";

    // 13. Backfill initial accounting ledger records if empty
    $ledgerCount = (int) $pdo->query("SELECT COUNT(*) FROM accounting_ledger")->fetchColumn();
    if ($ledgerCount === 0) {
        echo "[+] Generating initial accounting entries for historical completed/finalised orders...\n";
        // Query completed orders
        $stmtOrders = $pdo->query("
            SELECT id, subtotal, final_total, estimated_total, created_at, status 
            FROM orders 
            WHERE status IN ('finalised', 'completed')
            ORDER BY id ASC
            LIMIT 200
        ");
        $insLedger = $pdo->prepare("
            INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?)
        ");
        while ($ord = $stmtOrders->fetch(PDO::FETCH_ASSOC)) {
            $amount = (float) ($ord['final_total'] ?? $ord['estimated_total'] ?? 0);
            if ($amount <= 0) continue;
            $cogs = round($amount * 0.70);
            $tax = round($amount * 0.09);
            $revenue = $amount - $tax;

            // Debit Cash / Bank (Payment received)
            $insLedger->execute([$ord['created_at'], 'payment_received', $ord['id'], $amount, 0, 'cash_bank', "دریافت وجه سفارش #" . $ord['id']]);
            // Credit Sales Revenue
            $insLedger->execute([$ord['created_at'], 'sale_revenue', $ord['id'], 0, $revenue, 'sales_income', "درآمد فروش سفارش #" . $ord['id']]);
            // Credit Tax Payable
            if ($tax > 0) {
                $insLedger->execute([$ord['created_at'], 'tax_payable', $ord['id'], 0, $tax, 'tax_payable', "مالیات بر ارزش افزوده سفارش #" . $ord['id']]);
            }
            // COGS & Inventory Asset
            $insLedger->execute([$ord['created_at'], 'cogs', $ord['id'], $cogs, 0, 'cogs', "بهای تمام شده کالای فروش رفته #" . $ord['id']]);
            $insLedger->execute([$ord['created_at'], 'cogs', $ord['id'], 0, $cogs, 'inventory_asset', "کاهش موجودی انبار بابت سفارش #" . $ord['id']]);
        }
        echo "[✓] Backfilled initial accounting ledger records\n";
    }

    $pdo->commit();
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "=== Migration Completed Successfully! ===\n";
} catch (Throwable $ex) {
    $pdo->rollBack();
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "[-] Migration Failed: " . $ex->getMessage() . "\n";
    exit(1);
}
