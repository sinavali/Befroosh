<?php
declare(strict_types=1);

/**
 * setup_schema.php
 * Table schemas and index definitions for Befroosh Platform
 */

function drop_all_platform_tables(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');

    $tables = [
        'system_reports',
        'shop_contact_messages',
        'rate_limits',
        'cart_items',
        'product_bookmarks',
        'accounting_ledger',
        'inventory_transactions',
        'ticket_attachments',
        'ticket_order_relations',
        'ticket_messages',
        'tickets',
        'order_items',
        'orders',
        'addresses',
        'products',
        'categories',
        'shop_bank_cards',
        'users',
        'shops',
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

function install_platform_schema(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_meta (
            meta_key TEXT PRIMARY KEY,
            meta_value TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS shops (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            owner_id INTEGER NULL,
            phone TEXT NULL,
            email TEXT NULL,
            address TEXT NULL,
            national_id TEXT NULL,
            economic_code TEXT NULL,
            card_number TEXT NULL,
            card_holder TEXT NULL,
            bank_name TEXT NULL,
            shaba_number TEXT NULL,
            card_to_card_enabled INTEGER DEFAULT 1,
            reservation_days INTEGER DEFAULT 4,
            payment_methods TEXT DEFAULT '[\"card_to_card\"]',
            tax_rate REAL DEFAULT 0.0,
            default_shipping_cost REAL DEFAULT 250000,
            free_shipping_threshold REAL DEFAULT 2000000,
            policies_html TEXT NULL,
            about_html TEXT NULL,
            faq_json TEXT NULL,
            active INTEGER DEFAULT 1,
            description TEXT NULL,
            logo_path TEXT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

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

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            parent_id INTEGER NULL REFERENCES categories(id),
            sort_order INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS users (
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
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            category_id INTEGER NULL REFERENCES categories(id),
            title TEXT NOT NULL,
            slug TEXT NULL,
            description TEXT NULL,
            price INTEGER NOT NULL,
            cost_price REAL DEFAULT 0,
            stock_quantity REAL DEFAULT 0,
            reserved_quantity REAL DEFAULT 0,
            min_stock_alert REAL DEFAULT 5,
            min_order_qty REAL DEFAULT 1,
            sku TEXT UNIQUE,
            barcode TEXT NULL,
            unit TEXT DEFAULT 'عدد',
            weight_grams INTEGER DEFAULT 0,
            tax_rate REAL DEFAULT 0.0,
            max_per_order REAL DEFAULT 0,
            max_per_month REAL DEFAULT 0,
            image_path TEXT NULL,
            active INTEGER DEFAULT 1,
            deleted_at TEXT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS orders (
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

        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id),
            product_id INTEGER NULL REFERENCES products(id),
            product_title TEXT NOT NULL,
            product_sku TEXT NULL,
            unit_price INTEGER NOT NULL,
            unit_cost_price REAL DEFAULT 0,
            quantity REAL NOT NULL,
            tax_amount REAL DEFAULT 0,
            line_total REAL NOT NULL,
            unit TEXT DEFAULT 'عدد',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

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
        );

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
        );

        CREATE TABLE IF NOT EXISTS rate_limits (
            key TEXT PRIMARY KEY,
            hits INTEGER NOT NULL DEFAULT 1,
            reset_at INTEGER NOT NULL
        );

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

        CREATE TABLE IF NOT EXISTS product_bookmarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            shop_id INTEGER NOT NULL REFERENCES shops(id),
            product_id INTEGER NOT NULL REFERENCES products(id),
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(user_id, product_id)
        );

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

        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NULL REFERENCES shops(id),
            customer_id INTEGER NOT NULL REFERENCES users(id),
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('open','closed')) DEFAULT 'open',
            created_by_type TEXT NOT NULL CHECK(created_by_type IN ('customer','admin','superadmin','shop_owner','shop_manager')),
            created_by_id INTEGER NOT NULL REFERENCES users(id),
            seen_by_customer INTEGER DEFAULT 1,
            seen_by_admin INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id),
            sender_type TEXT NOT NULL CHECK(sender_type IN ('customer','admin','superadmin','shop_owner','shop_manager')),
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

    install_platform_indices($pdo);

    $pdo->exec('PRAGMA foreign_keys = ON');
}

require_once __DIR__ . '/setup_indices.php';

