<?php
declare(strict_types=1);

/**
 * schema_extensions.php
 * Schema extensions for Multi-Business, Branch Hierarchy, Shipping Groups,
 * Card-to-Card Receipt Verification, and 4 Subscription Tiers.
 */

function install_schema_extensions(PDO $pdo): void
{
    // 1. Subscription Plans Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscription_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            price_irt NUMERIC DEFAULT 0,
            max_businesses INTEGER DEFAULT 1,
            max_branches INTEGER DEFAULT 0,
            max_products INTEGER DEFAULT 50,
            max_managers INTEGER DEFAULT 0,
            has_ticketing INTEGER DEFAULT 0,
            has_online_payment INTEGER DEFAULT 0,
            has_ticket_escalation INTEGER DEFAULT 0,
            reporting_years INTEGER DEFAULT 1,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");

    // Seed default 4 plans if empty
    $planCount = (int)$pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ($planCount === 0) {
        $pdo->exec("
            INSERT INTO subscription_plans (slug, name, price_irt, max_businesses, max_branches, max_products, max_managers, has_ticketing, has_online_payment, has_ticket_escalation, reporting_years, is_active)
            VALUES 
            ('free', 'طرح پایه رایگان', 0, 1, 0, 50, 0, 0, 0, 0, 1, 1),
            ('plus', 'طرح پیشرفته پلاس', 490000, 1, 3, 200, 3, 1, 1, 0, 5, 1),
            ('pro', 'طرح حرفه‌ای پرو', 990000, 1, 5, 1000, 10, 1, 1, 0, 0, 1),
            ('ultimate', 'طرح نامحدود سازمانی', 1990000, 10, 9999, 999999, 9999, 1, 1, 1, 0, 1);
        ");
    }

    // 2. Shipping Groups Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shipping_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            business_id INTEGER NOT NULL REFERENCES shops(id),
            name TEXT NOT NULL,
            shipping_cost NUMERIC DEFAULT 0,
            free_shipping_threshold NUMERIC DEFAULT 0,
            central_hub_address TEXT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");

    // 3. Branches Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS branches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            business_id INTEGER NOT NULL REFERENCES shops(id),
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            logo_path TEXT NULL,
            phone TEXT NULL,
            address TEXT NULL,
            catalog_mode TEXT DEFAULT 'both' CHECK(catalog_mode IN ('business_only', 'branch_only', 'both')),
            shipping_group_id INTEGER NULL REFERENCES shipping_groups(id),
            default_shipping_cost NUMERIC DEFAULT 0,
            free_shipping_threshold NUMERIC DEFAULT 0,
            is_main INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            UNIQUE(business_id, slug)
        );
    ");

    // 4. Branch User Assignments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS branch_user_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            branch_id INTEGER NOT NULL REFERENCES branches(id),
            business_id INTEGER NOT NULL REFERENCES shops(id),
            role TEXT NOT NULL CHECK(role IN ('branch_manager', 'manager')),
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(user_id, branch_id)
        );
    ");

    // 5. Column alterations on existing tables (idempotent checks)
    $tableCols = function(string $table) use ($pdo): array {
        try {
            return array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        } catch (Throwable $e) { return []; }
    };

    // Columns on shops (businesses)
    $shopCols = $tableCols('shops');
    if (!in_array('subscription_plan_id', $shopCols, true)) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN subscription_plan_id INTEGER DEFAULT 1");
    }
    if (!in_array('subscription_expires_at', $shopCols, true)) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN subscription_expires_at TEXT NULL");
    }
    if (!in_array('zarinpal_merchant_id', $shopCols, true)) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN zarinpal_merchant_id TEXT NULL");
    }

    // Columns on products
    $prodCols = $tableCols('products');
    if (!in_array('branch_id', $prodCols, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN branch_id INTEGER NULL");
    }
    if (!in_array('is_business_product', $prodCols, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_business_product INTEGER DEFAULT 1");
    }

    // Columns on orders
    $orderCols = $tableCols('orders');
    if (!in_array('branch_id', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN branch_id INTEGER NULL");
    }
    if (!in_array('shipping_group_id', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_group_id INTEGER NULL");
    }
    if (!in_array('receipt_image_path', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_image_path TEXT NULL");
    }
    if (!in_array('receipt_description', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_description TEXT NULL");
    }
    if (!in_array('receipt_status', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_status TEXT DEFAULT 'pending'");
    }
    if (!in_array('receipt_verified_by', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_verified_by INTEGER NULL");
    }
    if (!in_array('receipt_verified_at', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_verified_at TEXT NULL");
    }
    if (!in_array('receipt_rejection_reason', $orderCols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_rejection_reason TEXT NULL");
    }

    // Columns on tickets
    $ticketCols = $tableCols('tickets');
    if (!in_array('is_elevated_to_superadmin', $ticketCols, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN is_elevated_to_superadmin INTEGER DEFAULT 0");
    }
    if (!in_array('is_escalated_to_superadmin', $ticketCols, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN is_escalated_to_superadmin INTEGER DEFAULT 0");
    }
    if (!in_array('elevated_at', $ticketCols, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN elevated_at TEXT NULL");
    }
    if (!in_array('elevated_by_id', $ticketCols, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN elevated_by_id INTEGER NULL");
    }

    // Columns on addresses
    $addrCols = $tableCols('addresses');
    if (!in_array('recipient_name', $addrCols, true)) {
        $pdo->exec("ALTER TABLE addresses ADD COLUMN recipient_name TEXT NULL");
    }
    if (!in_array('recipient_phone', $addrCols, true)) {
        $pdo->exec("ALTER TABLE addresses ADD COLUMN recipient_phone TEXT NULL");
    }

    // Columns on subscription_plans
    $subCols = $tableCols('subscription_plans');
    if (!in_array('code', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN code TEXT NULL");
        $pdo->exec("UPDATE subscription_plans SET code = slug WHERE code IS NULL");
    }
    if (!in_array('price_monthly', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN price_monthly NUMERIC DEFAULT 0");
        $pdo->exec("UPDATE subscription_plans SET price_monthly = price_irt WHERE price_monthly IS NULL OR price_monthly = 0");
    }
    if (!in_array('price_yearly', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN price_yearly NUMERIC DEFAULT 0");
    }
    if (!in_array('allow_custom_domain', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN allow_custom_domain INTEGER DEFAULT 0");
    }
    if (!in_array('allow_sms_alerts', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN allow_sms_alerts INTEGER DEFAULT 0");
    }
    if (!in_array('active', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN active INTEGER DEFAULT 1");
        $pdo->exec("UPDATE subscription_plans SET active = is_active WHERE active IS NULL");
    }
    if (!in_array('sort_order', $subCols, true)) {
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN sort_order INTEGER DEFAULT 0");
    }

    // 6. Performance Indexes
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_branches_business ON branches(business_id, active);
        CREATE INDEX IF NOT EXISTS idx_branch_users_user ON branch_user_assignments(user_id);
        CREATE INDEX IF NOT EXISTS idx_branch_users_branch ON branch_user_assignments(branch_id);
        CREATE INDEX IF NOT EXISTS idx_shipping_groups_biz ON shipping_groups(business_id);
        CREATE INDEX IF NOT EXISTS idx_orders_branch ON orders(branch_id);
        CREATE INDEX IF NOT EXISTS idx_products_branch ON products(branch_id);
    ");
}
