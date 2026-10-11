<?php

declare(strict_types=1);

namespace Tests;

use App\Application\DTO\CancelOrderRequest;
use App\Application\DTO\SubmitOrderRequest;
use App\Application\DTO\VerifyPaymentReceiptRequest;
use App\Application\UseCases\MultiTenancy\CreateBranchHandler;
use App\Application\UseCases\MultiTenancy\EscalateTicketHandler;
use App\Application\UseCases\Order\CancelOrderHandler;
use App\Application\UseCases\Order\CompleteOrderHandler;
use App\Application\UseCases\Order\ReleaseExpiredReservationsHandler;
use App\Application\UseCases\Order\ShipOrderHandler;
use App\Application\UseCases\Order\SubmitOrderHandler;
use App\Application\UseCases\Payment\VerifyPaymentReceiptHandler;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\PurchaseLimitExceededException;
use App\Domain\Exceptions\SubscriptionLimitExceededException;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Payment\LedgerAccount;
use App\Domain\Repositories\AddressRepositoryInterface;
use App\Domain\Repositories\BranchRepositoryInterface;
use App\Domain\Repositories\InventoryTransactionRepositoryInterface;
use App\Domain\Repositories\LedgerRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TicketRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Container\Autoloader;
use App\Infrastructure\Container\Bootstrapper;
use App\Infrastructure\Container\Container;
use DateTimeImmutable;
use PDO;

require_once __DIR__ . '/../src/Infrastructure/Container/Autoloader.php';
Autoloader::register();

echo "Running Befroosh Application Layer & Use-Cases Test Suite...\n\n";

$passed = 0;
$failed = 0;

$it = function (string $title, callable $fn) use (&$passed, &$failed): void {
    try {
        $fn();
        echo "  ✓ [PASS] {$title}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✗ [FAIL] {$title}\n";
        echo "           " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")\n";
        $failed++;
    }
};

// Setup in-memory SQLite schema for pristine test isolation
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Create essential schema tables
$pdo->exec("
    CREATE TABLE shops (
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
        default_shipping_cost REAL DEFAULT 250000.0,
        free_shipping_threshold REAL DEFAULT 2000000.0,
        active INTEGER DEFAULT 1,
        subscription_plan_id INTEGER DEFAULT 1,
        subscription_expires_at TEXT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NULL,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        nickname TEXT NOT NULL,
        phone TEXT NULL,
        email TEXT NULL,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        state TEXT NOT NULL,
        city TEXT NOT NULL,
        address TEXT NOT NULL,
        recipient_name TEXT NULL,
        recipient_phone TEXT NULL,
        postal_code TEXT NULL,
        description TEXT NULL,
        is_default INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        price INTEGER NOT NULL,
        cost_price REAL DEFAULT 0,
        stock_quantity REAL NOT NULL DEFAULT 0,
        reserved_quantity REAL NOT NULL DEFAULT 0,
        sku TEXT NULL,
        branch_id INTEGER NULL,
        category_id INTEGER NULL,
        slug TEXT NULL,
        description TEXT NULL,
        min_stock_alert REAL DEFAULT 5,
        min_order_qty REAL DEFAULT 1,
        barcode TEXT NULL,
        unit TEXT DEFAULT 'عدد',
        weight_grams INTEGER DEFAULT 0,
        tax_rate REAL DEFAULT 0,
        max_per_order REAL DEFAULT 0,
        max_per_month REAL DEFAULT 0,
        is_business_product INTEGER DEFAULT 1,
        image_path TEXT NULL,
        active INTEGER DEFAULT 1,
        deleted_at TEXT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE NOT NULL,
        shop_id INTEGER NOT NULL,
        customer_id INTEGER NOT NULL,
        branch_id INTEGER NULL,
        shipping_group_id INTEGER NULL,
        created_by_type TEXT DEFAULT 'customer',
        created_by_id INTEGER NULL,
        status TEXT NOT NULL,
        subtotal REAL NOT NULL,
        shipping_cost REAL NOT NULL,
        tax_amount REAL NOT NULL,
        discount_amount REAL NOT NULL DEFAULT 0,
        estimated_total REAL NOT NULL,
        final_total REAL NOT NULL,
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
        address_snapshot TEXT NULL,
        customer_snapshot TEXT NULL,
        description TEXT NULL,
        receipt_status TEXT DEFAULT 'pending',
        receipt_verified_by INTEGER NULL,
        receipt_verified_at TEXT NULL,
        receipt_rejection_reason TEXT NULL,
        seen_by_customer INTEGER DEFAULT 1,
        seen_by_admin INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NULL,
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

    CREATE TABLE accounting_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NOT NULL,
        entry_date TEXT DEFAULT (datetime('now')),
        entry_type TEXT NOT NULL,
        order_id INTEGER NULL,
        debit REAL NOT NULL DEFAULT 0,
        credit REAL NOT NULL DEFAULT 0,
        account TEXT NOT NULL,
        description TEXT NOT NULL,
        created_by_id INTEGER NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE inventory_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        quantity REAL NOT NULL,
        unit_cost REAL NULL,
        reference_type TEXT NULL,
        reference_id INTEGER NULL,
        notes TEXT NULL,
        created_by_id INTEGER NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE subscription_plans (
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

    CREATE TABLE branches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        business_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        slug TEXT NOT NULL,
        logo_path TEXT NULL,
        phone TEXT NULL,
        address TEXT NULL,
        catalog_mode TEXT DEFAULT 'both',
        shipping_group_id INTEGER NULL,
        default_shipping_cost NUMERIC DEFAULT 0,
        free_shipping_threshold NUMERIC DEFAULT 0,
        is_main INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        UNIQUE(business_id, slug)
    );

    CREATE TABLE branch_user_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        branch_id INTEGER NOT NULL,
        business_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        UNIQUE(user_id, branch_id)
    );

    CREATE TABLE tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shop_id INTEGER NULL,
        customer_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'open',
        priority TEXT DEFAULT 'medium',
        is_escalated_to_superadmin INTEGER DEFAULT 0,
        is_elevated_to_superadmin INTEGER DEFAULT 0,
        assigned_to_user_id INTEGER NULL,
        created_by_type TEXT DEFAULT 'customer',
        created_by_id INTEGER NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    );
");

// Seed default plans
$pdo->exec("
    INSERT INTO subscription_plans (id, slug, name, price_irt, max_businesses, max_branches, max_products, max_managers, has_ticketing, has_online_payment, has_ticket_escalation)
    VALUES 
    (1, 'free', 'طرح پایه رایگان', 0, 1, 0, 50, 0, 0, 0, 0),
    (2, 'plus', 'طرح پیشرفته پلاس', 490000, 1, 3, 200, 3, 1, 1, 0),
    (3, 'pro', 'طرح حرفه‌ای پرو', 990000, 1, 5, 1000, 10, 1, 1, 0),
    (4, 'ultimate', 'طرح نامحدود سازمانی', 1990000, 10, 9999, 999999, 9999, 1, 1, 1);
");

// Initialize Container with in-memory PDO
$container = new Container();
Bootstrapper::boot($container, $pdo);

// Seed shop, customer, address, product
$shopRepo = $container->get(ShopRepositoryInterface::class);
$userRepo = $container->get(UserRepositoryInterface::class);
$addrRepo = $container->get(AddressRepositoryInterface::class);
$prodRepo = $container->get(ProductRepositoryInterface::class);
$orderRepo = $container->get(OrderRepositoryInterface::class);
$ledgerRepo = $container->get(LedgerRepositoryInterface::class);

$shop = new \App\Domain\MultiTenancy\Shop(
    name: 'زعفران قائنات',
    slug: 'zaferan-qaen',
    taxRate: 0.09,
    defaultShippingCost: 50000.0,
    freeShippingThreshold: 500000.0,
    subscriptionPlanId: 1
);
$shop = $shopRepo->save($shop);
$shopId = $shop->getId();

$customer = new \App\Domain\MultiTenancy\User(
    username: 'ali98',
    nickname: 'علی رضایی',
    role: \App\Domain\MultiTenancy\Role::Customer,
    passwordHash: 'secret_hash',
    phone: '09123456789',
    shopId: $shopId
);
$customer = $userRepo->save($customer);
$customerId = $customer->getId();

$address = new \App\Domain\Order\Address(
    userId: $customerId,
    state: 'تهران',
    city: 'تهران',
    address: 'خیابان ولیعصر، پلاک ۱',
    postalCode: '1234567890',
    isDefault: true
);
$address = $addrRepo->save($address);
$addressId = $address->getId();

$product = new \App\Domain\Catalog\Product(
    shopId: $shopId,
    title: 'زعفران سرگل ممتاز ۱ مثقالی',
    price: 200000,
    stockQuantity: 10.0,
    costPrice: 150000.0,
    sku: 'ZAF-001',
    taxRate: 0.09,
    maxPerOrder: 5.0,
    maxPerMonth: 10.0
);
$product = $prodRepo->save($product);
$productId = $product->getId();

echo "=== Section 1: SubmitOrderHandler Use-Case ===\n";

$it('Submits order, reserves stock, computes shipping and 9% tax, and records 4-day reservation', function () use (
    $container, $shopId, $customerId, $addressId, $productId, $prodRepo
) {
    $handler = $container->get(SubmitOrderHandler::class);
    $req = new SubmitOrderRequest(
        shopId: $shopId,
        customerId: $customerId,
        addressId: $addressId,
        items: [
            ['product_id' => $productId, 'quantity' => 2.0]
        ]
    );

    $res = $handler->handle($req);

    assert($res->orderId > 0, 'Order ID must be positive');
    assert($res->status === 'submitted', 'Status must be submitted');
    assert($res->subtotal === 400000.0, 'Subtotal: 2 * 200,000 = 400,000');
    assert($res->shippingCost === 50000.0, 'Shipping cost below 500,000 free threshold is 50,000');
    assert($res->taxAmount === 36000.0, 'Tax: 400,000 * 0.09 = 36,000');
    assert($res->grandTotal === 486000.0, 'Grand total = 400k + 50k + 36k = 486,000');
    assert(!$res->isPaid, 'Order is not paid');

    // Verify stock was reserved on product
    $updatedProduct = $prodRepo->findById($productId);
    assert($updatedProduct->getStockQuantity()->value === 10.0, 'Physical stock remains 10');
    assert($updatedProduct->getReservedQuantity()->value === 2.0, 'Reserved stock is 2');
    assert($updatedProduct->getAvailableStock() === 8.0, 'Available stock is 8');
});

$it('Rejects order if purchase quantity exceeds available stock', function () use (
    $container, $shopId, $customerId, $addressId, $productId
) {
    $handler = $container->get(SubmitOrderHandler::class);
    $req = new SubmitOrderRequest(
        shopId: $shopId,
        customerId: $customerId,
        addressId: $addressId,
        items: [
            ['product_id' => $productId, 'quantity' => 20.0]
        ]
    );

    try {
        $handler->handle($req);
        assert(false, 'Did not throw');
    } catch (InsufficientStockException $e) {
        assert(true);
    }
});

$it('Rejects order if purchase quantity exceeds maxPerOrder limit', function () use (
    $container, $shopId, $customerId, $addressId, $productId
) {
    $handler = $container->get(SubmitOrderHandler::class);
    $req = new SubmitOrderRequest(
        shopId: $shopId,
        customerId: $customerId,
        addressId: $addressId,
        items: [
            ['product_id' => $productId, 'quantity' => 6.0] // maxPerOrder is 5
        ]
    );

    try {
        $handler->handle($req);
        assert(false, 'Did not throw');
    } catch (PurchaseLimitExceededException $e) {
        assert(true);
    }
});

echo "\n=== Section 2: VerifyPaymentReceiptHandler & Double-Entry Accounting ===\n";

$it('Approves payment and automatically generates balanced double-entry ledger entries via domain events', function () use (
    $container, $orderRepo, $ledgerRepo
) {
    $orders = $orderRepo->findByCustomerId(1);
    $order = $orders[0];
    $orderId = $order->getId();

    $handler = $container->get(VerifyPaymentReceiptHandler::class);
    $req = new VerifyPaymentReceiptRequest(
        orderId: $orderId,
        verifierUserId: 99,
        action: 'approve'
    );

    $res = $handler->handle($req);
    assert($res->status === 'paid', 'Status must be paid');
    assert($res->paymentStatus === 'paid', 'Payment status must be paid');

    // Verify double-entry ledger entries were generated automatically!
    $ledgerEntries = $ledgerRepo->findByOrderId($orderId);
    assert(count($ledgerEntries) >= 3, 'Must generate cash, sales, tax, shipping entries');

    // Assert balanced batch
    \App\Domain\Payment\DoubleEntryLedgerEntry::assertBalancedBatch($ledgerEntries);

    // Assert account heads
    $accounts = array_map(fn($e) => $e->getAccount()->value, $ledgerEntries);
    assert(in_array('cash_bank', $accounts, true), 'Cash/Bank must be debited');
    assert(in_array('sales_revenue', $accounts, true), 'Sales Revenue must be credited');
    assert(in_array('tax_payable', $accounts, true), 'Tax Payable must be credited');
    assert(in_array('shipping_revenue', $accounts, true), 'Shipping Revenue must be credited');
});

echo "\n=== Section 3: ShipOrderHandler & CompleteOrderHandler ===\n";

$it('Ships order with 24-digit Iran Post tracking barcode', function () use ($container, $orderRepo) {
    $orders = $orderRepo->findByCustomerId(1);
    $orderId = $orders[0]->getId();

    $trackingCode = '123456789012345678901234';
    $handler = $container->get(ShipOrderHandler::class);
    $order = $handler->handle($orderId, $trackingCode, 'پست پیشتاز');

    assert($order->getStatus() === OrderStatus::Shipped, 'Status must be Shipped');
    assert($order->getPostalTracking()->code === $trackingCode, 'Tracking code must match');
});

$it('Completes order delivery transition into terminal state', function () use ($container, $orderRepo) {
    $orders = $orderRepo->findByCustomerId(1);
    $orderId = $orders[0]->getId();

    $handler = $container->get(CompleteOrderHandler::class);
    $order = $handler->handle($orderId);

    assert($order->getStatus() === OrderStatus::Completed, 'Status must be Completed');
    assert($order->getStatus()->isTerminal(), 'Must be terminal');
});

echo "\n=== Section 4: CancelOrderHandler & Stock Restoration ===\n";

$it('Cancels active order and automatically restores reserved stock via domain event', function () use (
    $container, $shopId, $customerId, $addressId, $productId, $prodRepo
) {
    // Submit a fresh order
    $submitHandler = $container->get(SubmitOrderHandler::class);
    $subRes = $submitHandler->handle(new SubmitOrderRequest(
        shopId: $shopId,
        customerId: $customerId,
        addressId: $addressId,
        items: [['product_id' => $productId, 'quantity' => 3.0]]
    ));

    $orderId = $subRes->orderId;
    $prodBefore = $prodRepo->findById($productId);
    assert($prodBefore->getReservedQuantity()->value === 5.0, 'Reserved stock is 5 (2 from previous order + 3)');

    // Cancel order
    $cancelHandler = $container->get(CancelOrderHandler::class);
    $cancelRes = $cancelHandler->handle(new CancelOrderRequest(
        orderId: $orderId,
        userId: $customerId,
        userRole: 'customer',
        reason: 'پشیمانی خریدار'
    ));

    assert($cancelRes->status === 'canceled', 'Order must be canceled');

    // Verify stock restoration subscriber restored the 3 reserved items
    $prodAfter = $prodRepo->findById($productId);
    assert($prodAfter->getReservedQuantity()->value === 2.0, 'Reserved stock dropped back from 5 to 2');
    assert($prodAfter->getAvailableStock() === 8.0, 'Available stock restored to 8');
});

echo "\n=== Section 5: Subscription Plan Limits & Multi-Tenancy ===\n";

$it('Enforces subscription branch quota when creating branches on Free plan', function () use (
    $container, $shopId
) {
    $handler = $container->get(CreateBranchHandler::class);

    // Free plan allows max 0 branches!
    try {
        $handler->handle(
            businessId: $shopId,
            name: 'شعبه تجریش',
            slug: 'tajrish'
        );
        assert(false, 'Did not throw');
    } catch (SubscriptionLimitExceededException $e) {
        assert(str_contains($e->getMessage(), 'سقف مجاز تعداد شعب'), 'Throws branch limit exceeded');
    }
});

$it('Restricts ticket escalation to Ultimate subscription tier only', function () use (
    $container, $shopId, $customerId
) {
    $ticketRepo = $container->get(TicketRepositoryInterface::class);
    $ticket = new \App\Domain\Support\Ticket(
        shopId: $shopId,
        customerId: $customerId,
        title: 'مشکل در درگاه بانکی'
    );
    $ticket = $ticketRepo->save($ticket);

    $handler = $container->get(EscalateTicketHandler::class);

    // Shop is on Free plan -> cannot escalate!
    try {
        $handler->handle($ticket->getId(), 1);
        assert(false, 'Did not throw');
    } catch (SubscriptionLimitExceededException $e) {
        assert(str_contains($e->getMessage(), 'ارجاع مستقیم') && str_contains($e->getMessage(), 'Ultimate'), 'Throws escalation restriction');
    }
});

echo "\n======================================================\n";
echo "Application Layer Test Summary:\n";
echo "Total: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
if ($failed === 0) {
    echo "All Application Layer & Use-Case tests passed successfully (100%)!\n";
} else {
    echo "Failures encountered!\n";
    exit(1);
}
echo "======================================================\n";
