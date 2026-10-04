<?php
declare(strict_types=1);

/**
 * setup_indices.php
 * Performance Indexes for Befroosh Platform
 */

function install_platform_indices(PDO $pdo): void
{
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
        CREATE INDEX IF NOT EXISTS idx_users_role_shop ON users(role, shop_id);
        CREATE INDEX IF NOT EXISTS idx_users_shop ON users(shop_id);
        CREATE INDEX IF NOT EXISTS idx_orders_customer_status ON orders(customer_id, status);
        CREATE INDEX IF NOT EXISTS idx_orders_shop_status ON orders(shop_id, status);
        CREATE INDEX IF NOT EXISTS idx_orders_reservation ON orders(status, reservation_expires_at);
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
        CREATE INDEX IF NOT EXISTS idx_cart_user_shop ON cart_items(user_id, shop_id);
        CREATE INDEX IF NOT EXISTS idx_cart_expires ON cart_items(expires_at);
        CREATE INDEX IF NOT EXISTS idx_bookmarks_user ON product_bookmarks(user_id);
        CREATE INDEX IF NOT EXISTS idx_shop_cards_shop ON shop_bank_cards(shop_id, active);
        CREATE INDEX IF NOT EXISTS idx_reports_status ON system_reports(status, severity);
    ");
}
