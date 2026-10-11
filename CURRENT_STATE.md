# CURRENT_STATE.md - Befroosh Architectural Specification & System Audit

> **Generated**: October 11, 2026  
> **Repository Branch**: `docs/current-state`  
> **Scope**: Pure Clean Architecture (DDD), 25 Database Tables, 22 End-to-End User Flows, 10 Action Categories, and 12 Major Features Looped to Atomic Entities.  
> **Test Harness**: 153/153 Tests Passing (`test.php`) across 6 Test Suites in ~1.42s.  

---

## Table of Contents
1. [Executive Architectural Overview & Runtime Stack](#1-executive-architectural-overview--runtime-stack)
2. [Complete Relational Database Schema & Data Dictionary (25 Tables)](#2-complete-relational-database-schema--data-dictionary-25-tables)
3. [Domain-Driven Design (DDD) Core Architecture](#3-domain-driven-design-ddd-core-architecture)
4. [Complete Catalog of Frontend Pages (Must-Have vs. Preferred)](#1-complete-catalog-of-frontend-pages-must-have-vs-preferred)
5. [Complete List of End-to-End User Flows (22 Flows)](#2-complete-list-of-end-to-end-user-flows)
6. [Exhaustive Catalog of User Actions & System Mutations (10 Categories)](#3-exhaustive-catalog-of-user-actions--system-mutations)
7. [Major Features & Recursive Sub-Features Breakdown (12 Features)](#4-major-features--recursive-sub-features-breakdown)

---

## 1. Executive Architectural Overview & Runtime Stack

Befroosh is a specialized Iranian multi-tenant e-commerce platform engineered specifically around the operational realities of Iranian commerce: offline Card-to-Card bank transfers, strict 4-day (96-hour) inventory reservations, 24-digit Iran Post logistics integration, localized branch fulfillment, and balanced double-entry financial ledger accounting.

### 1.1. Core Runtime Architecture
- **PHP 8.4 Engine**: Runs in a Podman container under Lerd (`lerd-php84-fpm`).
- **Database Engine**: SQLite 3 operating in **Write-Ahead Logging (WAL)** mode (`PRAGMA journal_mode = WAL`, `PRAGMA synchronous = NORMAL`, `PRAGMA foreign_keys = ON`), located at `storage/database.sqlite`.
- **Presentation Layer**: Dual-mode architecture supporting both server-rendered HTML views and high-velocity RESTful JSON endpoints (`routes_api.php`, `src/Http/Controllers/`).
- **Styling & Design System**: Neutral, flat, tactile Apple-inspired design system (`DESIGN.md`) optimized for Persian RTL, Vazirmatn typography, hairline borders (`1px solid #E2E8F0`), and tabular numerals for Rial/Toman and 24-digit tracking barcodes.

---

## 2. Complete Relational Database Schema & Data Dictionary (25 Tables)

### 2.1. Complete Relational Database DDL & Data Dictionary (25 Tables)

The SQLite database (`storage/database.sqlite`) operates in Write-Ahead Logging (`WAL`) mode with strict foreign key constraints enabled.

#### 1. `shops` (Tenants / Businesses)
- `id` (INTEGER, Primary Key, Autoincrement): Unique tenant identifier.
- `name` (TEXT, NOT NULL): Commercial display name of the store.
- `slug` (TEXT, UNIQUE, NOT NULL): URL-safe identifier for routing (`/b/{slug}`).
- `owner_id` (INTEGER, NULL): Foreign key referencing `users(id)` representing the primary account holder.
- `phone` (TEXT, NULL): Customer service phone number.
- `email` (TEXT, NULL): Official contact email.
- `address` (TEXT, NULL): Physical headquarters / warehouse address.
- `national_id` (TEXT, NULL): Iranian National Identity number (شناسه ملی).
- `economic_code` (TEXT, NULL): Iranian Economic Code (کد اقتصادی) for statutory tax invoicing.
- `card_number` (TEXT, NULL): Primary 16-digit debit card number for offline payments.
- `card_holder` (TEXT, NULL): Account holder name matching the bank card.
- `bank_name` (TEXT, NULL): Issuing bank name (e.g., Melli, Mellat, Pasargad, Saman).
- `shaba_number` (TEXT, NULL): 24-digit Sheba IBAN prefixed with `IR`.
- `card_to_card_enabled` (INTEGER, DEFAULT 1): Flag (0/1) controlling offline payment availability.
- `reservation_days` (INTEGER, DEFAULT 4): Stock reservation duration in days (default 4 days / 96 hours).
- `payment_methods` (TEXT, DEFAULT '["card_to_card"]'): JSON array of activated payment gateways.
- `tax_rate` (REAL, DEFAULT 0.0): Value-Added Tax rate (0.09 for statutory 9% VAT).
- `default_shipping_cost` (REAL, DEFAULT 250000): Fallback delivery fee in Persian Toman.
- `free_shipping_threshold` (REAL, DEFAULT 2000000): Basket threshold for complimentary delivery.
- `policies_html` (TEXT, NULL): Sanitized rich-text HTML for merchant returns & shipping policies.
- `about_html` (TEXT, NULL): Sanitized rich-text HTML for store about/history page.
- `faq_json` (TEXT, NULL): JSON array of frequently asked questions and answers `[{"q":"...","a":"..."}]`.
- `active` (INTEGER, DEFAULT 1): Tenant status (1=active, 0=suspended by SuperAdmin).
- `description` (TEXT, NULL): Short SEO summary / meta description.
- `logo_path` (TEXT, NULL): Relative path to store branding asset in `storage/`.
- `subscription_plan_id` (INTEGER, DEFAULT 1): Foreign key referencing `subscription_plans(id)`.
- `subscription_expires_at` (TEXT, NULL): ISO8601 timestamp of plan expiration.
- `zarinpal_merchant_id` (TEXT, NULL): Optional payment gateway merchant ID.
- `created_at` / `updated_at` (TEXT): Audit timestamps.

#### 2. `branches` (Physical & Virtual Store Outlets)
- `id` (INTEGER, Primary Key, Autoincrement): Branch identifier.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Parent tenant reference.
- `name` (TEXT, NOT NULL): Branch display name (e.g., "شعبه مرکزی", "شعبه پالادیوم").
- `slug` (TEXT, NOT NULL): Branch URL slug (`/b/{shop_slug}/{branch_slug}`).
- `phone` (TEXT, NULL): Direct telephone for branch dispatch desk.
- `address` (TEXT, NULL): Local dispatch or store address.
- `catalog_mode` (TEXT, NOT NULL, DEFAULT 'both'): Catalog isolation strategy:
  - `business_only`: Shares inventory and products exclusively from the master business.
  - `branch_only`: Sells products isolated specifically to this branch.
  - `both`: Blends master business items with branch-specific items.
- `default_shipping_cost` (REAL, NULL): Override shipping fee for local courier dispatch.
- `free_shipping_threshold` (REAL, NULL): Override threshold for free branch delivery.
- `shipping_group_id` (INTEGER, NULL, FK -> `shipping_groups(id)`): Logistics hub association.
- `is_main` (INTEGER, DEFAULT 0): Indicates the default primary branch created at onboarding.
- `created_at` (TEXT): Audit timestamp.

#### 3. `shipping_groups` (Centralized Logistics Hubs)
- `id` (INTEGER, Primary Key, Autoincrement): Logistics cluster identifier.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Parent tenant reference.
- `name` (TEXT, NOT NULL): Group name (e.g., "مرکز توزیع غرب تهران", "انبار مرکزی شیراز").
- `default_shipping_cost` (REAL, DEFAULT 0): Consolidated regional courier rate.
- `free_shipping_threshold` (REAL, DEFAULT 0): Consolidated free delivery threshold.
- `hub_address` (TEXT, NULL): Physical fulfillment hub origin address.
- `created_at` (TEXT): Audit timestamp.

#### 4. `users` (Identity & Multi-Role Accounts)
- `id` (INTEGER, Primary Key, Autoincrement): User identifier.
- `shop_id` (INTEGER, NULL, FK -> `shops(id)`): Scoped tenant affiliation (null for SuperAdmin/platform users).
- `username` (TEXT, UNIQUE, NOT NULL): System login handle.
- `phone` (TEXT, UNIQUE, NULL): 10-digit Iranian mobile number normalized without leading zero (starts with `9`).
- `password_hash` (TEXT, NOT NULL): Secure `bcrypt` cryptographic hash.
- `role` (TEXT, NOT NULL): RBAC role: `superadmin`, `admin`, `shop_owner`, `branch_manager`, `shop_manager`, `customer`.
- `first_name` (TEXT, NULL): Legal first name.
- `last_name` (TEXT, NULL): Legal family name.
- `nickname` (TEXT, NULL): Friendly display moniker.
- `national_code` (TEXT, NULL): 10-digit Iranian National Code (کد ملی).
- `email` (TEXT, NULL): Verified email address.
- `active` (INTEGER, DEFAULT 1): Account status (1=active, 0=blocked).
- `created_at` (TEXT): Audit timestamp.

#### 5. `branch_user_assignments` (Scoped Staff Permissions)
- `id` (INTEGER, Primary Key, Autoincrement): Assignment ID.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Assigned staff user.
- `branch_id` (INTEGER, NOT NULL, FK -> `branches(id)`): Scoped branch.
- `assigned_at` (TEXT): Timestamp of role assignment.

#### 6. `categories` (Hierarchical Product Taxonomies)
- `id` (INTEGER, Primary Key, Autoincrement): Category ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant isolation.
- `name` (TEXT, NOT NULL): Category title in Persian.
- `slug` (TEXT, NOT NULL): URL-safe slug.
- `parent_id` (INTEGER, NULL, FK -> `categories(id)`): Parent category for nested hierarchies.
- `sort_order` (INTEGER, DEFAULT 0): Numeric sequence for frontend navigation pills.
- `created_at` (TEXT): Audit timestamp.

#### 7. `products` (Core Merchandise Inventory)
- `id` (INTEGER, Primary Key, Autoincrement): Product ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant isolation.
- `branch_id` (INTEGER, NULL, FK -> `branches(id)`): Specific branch allocation (null if universal).
- `category_id` (INTEGER, NULL, FK -> `categories(id)`): Category association.
- `title` (TEXT, NOT NULL): Persian product name.
- `slug` (TEXT, NOT NULL): URL slug (`/b/{shop}/p/{slug}`).
- `price` (REAL, NOT NULL): Unit sales price in Persian Toman.
- `cost_price` (REAL, DEFAULT 0): Purchase acquisition cost (COGS valuation).
- `stock` (INTEGER, DEFAULT 0): Physical on-hand inventory count.
- `reserved_stock` (INTEGER, DEFAULT 0): Units locked in active 4-day pending orders ($Stock_{available} = Stock_{physical} - Stock_{reserved}$).
- `min_stock_alert` (INTEGER, DEFAULT 5): Threshold triggering low-stock manager alerts.
- `min_order_qty` (INTEGER, DEFAULT 1): Minimum purchase quantity per order.
- `max_per_order` (INTEGER, NULL): Maximum purchase quantity permitted in a single checkout.
- `max_per_month` (INTEGER, NULL): 30-day rolling purchase limit per customer to prevent hoarding.
- `tax_rate` (REAL, DEFAULT 0.0): Product-specific VAT rate (overrides shop default if set).
- `sku` (TEXT, NULL): Stock Keeping Unit string.
- `barcode` (TEXT, NULL): EAN-13 / UPC / internal numeric barcode.
- `unit` (TEXT, DEFAULT 'عدد'): Unit of measure (e.g., عدد, کیلوگرم, بسته, لیتر).
- `weight_grams` (INTEGER, DEFAULT 0): Item weight in grams for post packaging calculation.
- `image_url` (TEXT, NULL): Primary cover image path.
- `active` (INTEGER, DEFAULT 1): Visibility flag (1=visible in catalog, 0=draft/archived).
- `created_at` (TEXT): Audit timestamp.

#### 8. `product_bookmarks` (Customer Wishlists)
- `id` (INTEGER, Primary Key, Autoincrement): Bookmark ID.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Customer reference.
- `product_id` (INTEGER, NOT NULL, FK -> `products(id)`): Bookmarked item reference.
- `created_at` (TEXT): Timestamp when item was saved.

#### 9. `cart_items` (Server-Side Shopping Cart)
- `id` (INTEGER, Primary Key, Autoincrement): Cart line ID.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Authenticated customer reference.
- `product_id` (INTEGER, NOT NULL, FK -> `products(id)`): Product reference.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant partitioning.
- `quantity` (INTEGER, NOT NULL, DEFAULT 1): Item quantity.
- `created_at` / `updated_at` (TEXT): Timestamp tracking for 7-day cart garbage collection.

#### 10. `addresses` (Customer Delivery Address Book)
- `id` (INTEGER, Primary Key, Autoincrement): Address ID.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Customer reference.
- `province` (TEXT, NOT NULL): Iranian province (استان).
- `city` (TEXT, NOT NULL): City name (شهر).
- `address` (TEXT, NOT NULL): Detailed street address, building, unit, floor.
- `postal_code` (TEXT, NOT NULL): Exactly 10-digit Iranian postal code (کد پستی ۱۰ رقمی).
- `recipient_name` (TEXT, NOT NULL): Name of person receiving the package.
- `recipient_phone` (TEXT, NOT NULL): 10-digit mobile number for dispatch courier contact.
- `is_default` (INTEGER, DEFAULT 0): Default selection flag.
- `created_at` (TEXT): Audit timestamp.

#### 11. `orders` (Master Sales Orders & State Machine)
- `id` (INTEGER, Primary Key, Autoincrement): Internal relational order ID.
- `uuid` (TEXT, UNIQUE, NOT NULL): 8-character uppercase tracking UUID (e.g., `ORD-A1B2C3D4`).
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant partition.
- `branch_id` (INTEGER, NULL, FK -> `branches(id)`): Fulfilling branch.
- `customer_id` (INTEGER, NOT NULL, FK -> `users(id)`): Customer account reference.
- `customer_snapshot` (TEXT, NOT NULL): Immutable JSON snapshot of customer name, phone, national ID at checkout.
- `address_snapshot` (TEXT, NOT NULL): Immutable JSON snapshot of delivery address, recipient, and postal code.
- `price_snapshot` (TEXT, NOT NULL): Immutable JSON snapshot containing: subtotal, shipping fee, tax, discounts, grand total.
- `status` (TEXT, NOT NULL, DEFAULT 'submitted'): State machine: `submitted`, `processing`, `shipped`, `completed`, `canceled`.
- `payment_status` (TEXT, NOT NULL, DEFAULT 'unpaid'): Payment state: `unpaid`, `pending_verification`, `paid`, `rejected`.
- `receipt_status` (TEXT, NOT NULL, DEFAULT 'none'): Slip status: `none`, `pending`, `verified`, `rejected`.
- `receipt_image` (TEXT, NULL): Stored path to uploaded bank transfer receipt.
- `receipt_ref` (TEXT, NULL): Bank transaction reference / tracking number entered by customer.
- `bank_card_id` (INTEGER, NULL, FK -> `shop_bank_cards(id)`): Destination merchant bank card used.
- `rejection_reason` (TEXT, NULL): Audit note explaining why a bank slip was rejected.
- `cancellation_reason` (TEXT, NULL): Reason for cancellation (e.g., "انقضای مهلت رزرو ۴ روزه").
- `postal_tracking_code` (TEXT, NULL): Strictly validated 24-digit Iran Post tracking barcode linking to `tracking.post.ir`.
- `reservation_expires_at` (TEXT, NOT NULL): ISO8601 expiration timestamp (order time + 96 hours).
- `seen_by_admin` (INTEGER, DEFAULT 0): Unread notification flag for merchant management.
- `seen_by_customer` (INTEGER, DEFAULT 1): Unread notification flag for customer portal.
- `created_at` / `updated_at` (TEXT): State transition audit timestamps.

#### 12. `order_items` (Immutable Order Line Snapshots)
- `id` (INTEGER, Primary Key, Autoincrement): Line item ID.
- `order_id` (INTEGER, NOT NULL, FK -> `orders(id)`): Parent order reference.
- `product_id` (INTEGER, NOT NULL, FK -> `products(id)`): Product reference.
- `product_title` (TEXT, NOT NULL): Frozen snapshot of product title.
- `product_sku` (TEXT, NULL): Frozen snapshot of product SKU.
- `unit_price` (REAL, NOT NULL): Frozen unit price in Toman at time of sale.
- `quantity` (INTEGER, NOT NULL): Units purchased.
- `total_price` (REAL, NOT NULL): Extended line total ($unit\_price \times quantity$).
- `cost_price` (REAL, DEFAULT 0): Frozen acquisition unit cost for COGS calculations.

#### 13. `shop_bank_cards` (Merchant Card-to-Card Payment Accounts)
- `id` (INTEGER, Primary Key, Autoincrement): Bank card account ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant reference.
- `card_number` (TEXT, NOT NULL): 16-digit debit card number.
- `card_holder` (TEXT, NOT NULL): Name on account.
- `bank_name` (TEXT, NULL): Bank identifier (e.g., ملت, ملی, صادرات).
- `shaba_number` (TEXT, NULL): 24-digit Sheba IBAN.
- `active` (INTEGER, DEFAULT 1): Active payment destination flag.
- `expires_at` (TEXT, NULL): Optional card expiry date.
- `created_at` (TEXT): Audit timestamp.

#### 14. `inventory_transactions` (Warehouse Stock Movement Ledger)
- `id` (INTEGER, Primary Key, Autoincrement): Transaction ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant isolation.
- `branch_id` (INTEGER, NULL, FK -> `branches(id)`): Branch warehouse location.
- `product_id` (INTEGER, NOT NULL, FK -> `products(id)`): Affected product.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Staff operator executing the action.
- `type` (TEXT, NOT NULL): Movement type: `inward` (purchase intake), `outward_order` (order fulfillment deduction), `adjustment_variance` (stock-taking discrepancy), `return_in` (customer return), `write_off` (damaged/expired inventory disposal).
- `quantity` (INTEGER, NOT NULL): Quantity delta (+ positive for inflows, - negative for outflows).
- `unit_cost` (REAL, DEFAULT 0): Unit valuation at time of transaction.
- `reference_no` (TEXT, NULL): Inward invoice number or order UUID.
- `notes` (TEXT, NULL): Operator explanation or audit rationale.
- `created_at` (TEXT): Audit timestamp.

#### 15. `accounting_ledger` (Double-Entry General Journal Vouchers)
- `id` (INTEGER, Primary Key, Autoincrement): Ledger line ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Tenant isolation.
- `voucher_id` (TEXT, NOT NULL): Unique voucher group ID (e.g., `VCH-20261011-001`).
- `voucher_type` (TEXT, NOT NULL): `order_payment`, `inventory_inward`, `inventory_write_off`, `manual_adjustment`.
- `account_code` (TEXT, NOT NULL): Standard Chart of Accounts code:
  - `1010`: Cash & Bank Assets (`cash_bank`)
  - `1050`: Inventory Asset (`inventory_asset`)
  - `2010`: Accounts Payable (`accounts_payable`)
  - `2050`: Tax Payable - 9% VAT (`tax_payable`)
  - `4010`: Sales Revenue (`sales_revenue`)
  - `4020`: Shipping Revenue (`shipping_revenue`)
  - `4090`: Sales Discounts (`discounts`)
  - `5010`: Cost of Goods Sold (`cogs`)
- `account_name` (TEXT, NOT NULL): Persian account name.
- `debit` (REAL, DEFAULT 0): Debit amount in Toman.
- `credit` (REAL, DEFAULT 0): Credit amount in Toman.
- `description` (TEXT, NULL): Explanation of voucher line.
- `reference_id` (TEXT, NULL): Linked order UUID or inventory intake reference.
- `operator_id` (INTEGER, NULL, FK -> `users(id)`): Operator responsible.
- `is_balanced` (INTEGER, DEFAULT 1): Flag confirming $\sum Debit = \sum Credit$.
- `created_at` (TEXT): Audit timestamp.

#### 16. `subscription_plans` (Platform Tier Definitions & Quotas)
- `id` (INTEGER, Primary Key, Autoincrement): Plan ID.
- `code` (TEXT, UNIQUE, NOT NULL): Plan tier: `free`, `plus`, `pro`, `ultimate`.
- `name` (TEXT, NOT NULL): Display name (رایگان, پلاس, پرو, التیمیت).
- `max_branches` (INTEGER, DEFAULT 1): Quota limit on branches (0 = unlimited).
- `max_products` (INTEGER, DEFAULT 50): Quota limit on active products (0 = unlimited).
- `max_managers` (INTEGER, DEFAULT 1): Quota limit on staff managers (0 = unlimited).
- `has_accounting` (INTEGER, DEFAULT 0): Access to double-entry ledger and trial balance.
- `has_ticket_escalation` (INTEGER, DEFAULT 0): Access to emergency SuperAdmin ticket escalation SLA.
- `monthly_price` (REAL, DEFAULT 0): Subscription fee in Toman per month.
- `yearly_price` (REAL, DEFAULT 0): Subscription fee in Toman per year.
- `is_active` (INTEGER, DEFAULT 1): Plan status.
- `created_at` (TEXT): Audit timestamp.

#### 17. `tickets` (Customer Support Inquiries)
- `id` (INTEGER, Primary Key, Autoincrement): Ticket ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Scoped tenant.
- `customer_id` (INTEGER, NOT NULL, FK -> `users(id)`): Submitting customer.
- `subject` (TEXT, NOT NULL): Issue subject line.
- `status` (TEXT, NOT NULL, DEFAULT 'open'): `open`, `in_progress`, `closed`.
- `priority` (TEXT, NOT NULL, DEFAULT 'normal'): `normal`, `high`, `urgent`.
- `is_escalated` (INTEGER, DEFAULT 0): Flag indicating VIP escalation to SuperAdmin queue.
- `seen_by_admin` (INTEGER, DEFAULT 0): Unread indicator for merchant staff.
- `seen_by_customer` (INTEGER, DEFAULT 1): Unread indicator for customer.
- `created_at` / `updated_at` (TEXT): Audit timestamps.

#### 18. `ticket_messages` (Threaded Conversation Entries)
- `id` (INTEGER, Primary Key, Autoincrement): Message ID.
- `ticket_id` (INTEGER, NOT NULL, FK -> `tickets(id)`): Parent ticket.
- `user_id` (INTEGER, NOT NULL, FK -> `users(id)`): Author of message (customer or staff).
- `message` (TEXT, NOT NULL): Body text.
- `created_at` (TEXT): Message timestamp.

#### 19. `ticket_attachments` (Support Documents & Images)
- `id` (INTEGER, Primary Key, Autoincrement): Attachment ID.
- `ticket_id` (INTEGER, NOT NULL, FK -> `tickets(id)`): Parent ticket.
- `message_id` (INTEGER, NOT NULL, FK -> `ticket_messages(id)`): Parent message.
- `file_name` (TEXT, NOT NULL): Original uploaded file name.
- `file_path` (TEXT, NOT NULL): Stored path in `storage/attachments`.
- `file_size` (INTEGER, NOT NULL): Size in bytes (max 10MB).
- `mime_type` (TEXT, NOT NULL): `image/jpeg`, `image/png`, `application/pdf`.
- `created_at` (TEXT): Timestamp.

#### 20. `ticket_order_relations` (Contextual Order Links)
- `id` (INTEGER, Primary Key, Autoincrement): Link ID.
- `ticket_id` (INTEGER, NOT NULL, FK -> `tickets(id)`): Ticket reference.
- `order_id` (INTEGER, NOT NULL, FK -> `orders(id)`): Linked order reference.

#### 21. `shop_contact_messages` (Storefront Contact Form Inquiries)
- `id` (INTEGER, Primary Key, Autoincrement): Contact message ID.
- `shop_id` (INTEGER, NOT NULL, FK -> `shops(id)`): Merchant inbox destination.
- `sender_name` (TEXT, NOT NULL): Inquirer's full name.
- `phone` (TEXT, NULL): Inquirer's telephone or mobile number.
- `message` (TEXT, NOT NULL): Inquiry text.
- `is_read` (INTEGER, DEFAULT 0): Read status flag.
- `created_at` (TEXT): Timestamp.

#### 22. `system_reports` (Platform Abuse & Technical Incidents)
- `id` (INTEGER, Primary Key, Autoincrement): Report ID.
- `reporter_id` (INTEGER, NOT NULL, FK -> `users(id)`): SuperAdmin or merchant reporter.
- `shop_id` (INTEGER, NULL, FK -> `shops(id)`): Associated tenant if applicable.
- `title` (TEXT, NOT NULL): Incident headline.
- `description` (TEXT, NOT NULL): Technical description / violation details.
- `severity` (TEXT, NOT NULL, DEFAULT 'normal'): `low`, `normal`, `high`, `critical`.
- `status` (TEXT, NOT NULL, DEFAULT 'open'): `open`, `in_progress`, `resolved`, `closed`.
- `resolution_notes` (TEXT, NULL): Concluding remarks by SuperAdmin.
- `created_at` / `updated_at` (TEXT): Audit timestamps.

#### 23. `login_attempts` (Brute-Force Security Protection)
- `id` (INTEGER, Primary Key, Autoincrement): Attempt ID.
- `username_or_phone` (TEXT, NOT NULL): Identity credential submitted.
- `ip_address` (TEXT, NOT NULL): Remote IP address.
- `failed_attempts` (INTEGER, DEFAULT 1): Count of failed attempts.
- `locked_until` (TEXT, NULL): Timestamp until which authentication is blocked (5-minute window after 5 failures).
- `last_attempt_at` (TEXT): Timestamp of most recent attempt.

#### 24. `rate_limits` (DDoS & API Throttling)
- `id` (INTEGER, Primary Key, Autoincrement): Rate limiter ID.
- `client_key` (TEXT, UNIQUE, NOT NULL): Client hash (`IP + Route`).
- `hits` (INTEGER, DEFAULT 1): Number of requests within active window.
- `window_start` (INTEGER, NOT NULL): Epoch timestamp of the rate window start (120 req/min limit).

#### 25. `app_meta` (Global Key-Value Configuration)
- `meta_key` (TEXT, PRIMARY KEY): Unique system parameter key:
  - `cart_retention_days`: Inactivity days before customer cart items are purged (default 7).
  - `unpaid_order_reservation_days`: Platform fallback for reservation expiration (default 4).
  - `maintenance_mode`: Global downtime flag (0/1).
- `meta_value` (TEXT, NULL): Serialized configuration value.
- `updated_at` (TEXT): Configuration modification timestamp.


---

## 3. Domain-Driven Design (DDD) Core Architecture

### 3. Domain-Driven Design (DDD) Core Architecture

The Befroosh backend is structured according to strict Domain-Driven Design principles, isolating business invariants and pure rules from database drivers, HTTP protocols, and UI templates.

#### 3.1. Pure Domain Entities & Value Objects (79 Classes)
All domain entities extend `src/Domain/Common/Entity.php` or `ValueObject.php`:
1. **Catalog Subdomain (`src/Domain/Catalog`)**:
   - `Product`: Manages inventory balance, purchase checks, price calculation, and reservation mutations.
   - `CartItem`: Encapsulates customer shopping bag line items and quantity boundaries.
   - `Category`: Represents hierarchical product navigation taxonomy.
   - `InventoryTransaction`: Encapsulates warehouse movements and audit entries.
   - `Sku`: Value object enforcing alphanumeric SKU formatting.
   - `StockQuantity`: Value object ensuring non-negative physical and reserved inventory counts.
2. **Multi-Tenancy Subdomain (`src/Domain/MultiTenancy`)**:
   - `Shop`: Root tenant aggregate governing merchant settings, bank cards, and policies.
   - `Branch`: Branch entity enforcing `catalog_mode` invariants (`business_only`, `branch_only`, `both`).
   - `ShippingGroup`: Cluster entity coordinating centralized logistics hubs.
   - `Role`: Value object strictly validating the 6 authorization roles (`superadmin`, `admin`, `shop_owner`, `branch_manager`, `shop_manager`, `customer`).
   - `SubscriptionPlan`: Aggregate governing 4-tier quotas and capability flags.
   - `SubscriptionTier`: Value object defining tier features (`Free`, `Plus`, `Pro`, `Ultimate`).
   - `User`: Identity entity encapsulating credentials, roles, and profile attributes.
   - `UserAssignment`: Pivot entity binding managers to specific branches.
3. **Order Subdomain (`src/Domain/Order`)**:
   - `Order`: Aggregate root managing the 5-stage order state machine, stock reservation timers, and receipt audits.
   - `OrderItem`: Line item snapshot capturing unit price, title, SKU, and acquisition cost.
   - `OrderStatus`: Value object managing state transitions: `submitted` -> `processing` -> `shipped` -> `completed` (or `canceled`).
   - `PaymentStatus`: Value object managing payment transitions: `unpaid` -> `pending_verification` -> `paid` (or `rejected`).
   - `PostalTracking`: Value object strictly enforcing 24-digit Iran Post barcodes with Luhn/format checks.
   - `Address`: Value object validating 10-digit postal codes and delivery coordinates.
   - `CustomerSnapshot`, `AddressSnapshot`, `PriceSnapshot`: Immutable JSON serialization objects.
   - `PurchaseLimits`: Value object enforcing minimum quantity, per-order limits, and 30-day customer caps.
4. **Payment & Accounting Subdomain (`src/Domain/Payment`)**:
   - `BankCard`: Entity managing 16-digit debit card numbers, Sheba IBANs, and account holders.
   - `CardReceipt`: Entity encapsulating uploaded receipt images, reference numbers, and verification actions.
   - `DoubleEntryLedgerEntry`: Single line of an accounting voucher ensuring signed debit/credit balance.
   - `LedgerTransaction`: Complete double-entry voucher enforcing $\sum Debit = \sum Credit$.
   - `LedgerAccount`: Value object representing Chart of Accounts codes (`1010`, `1050`, `2010`, `2050`, `4010`, `4020`, `4090`, `5010`).
   - `LedgerEntryType`: Value object identifying voucher trigger types.
5. **Support Subdomain (`src/Domain/Support`)**:
   - `Ticket`: Aggregate managing threaded customer-merchant communication and escalation states.

#### 3.2. Domain Events & Asynchronous Subscribers
The domain model emits high-fidelity domain events that trigger automated side-effects through `DomainEventDispatcher`:
- `OrderSubmittedEvent`: Emitted when an order is created; locks reserved stock and arms the 96-hour expiration timer.
- `PaymentReceiptApprovedEvent`: Emitted when merchant staff verifies a bank slip; commits stock and triggers the `GenerateLedgerOnPaymentApproved` subscriber to write balanced accounting vouchers.
- `PaymentReceiptRejectedEvent`: Emitted when a slip is rejected; notifies customer and resets slip status.
- `OrderShippedEvent`: Emitted when staff assigns a 24-digit postal code; updates milestone stepper.
- `OrderCompletedEvent`: Emitted upon confirmed customer delivery.
- `OrderCanceledEvent`: Emitted on cancellation; triggers `ReleaseStockOnOrderCanceled` to return stock to available inventory.
- `ReservationExpiredEvent`: Emitted by `ReleaseExpiredReservationsHandler` when 96 hours elapse without verified payment.
- `StockInwardRecordedEvent`: Emitted on purchase intake; posts debit to `inventory_asset` and credit to `cash_bank`.
- `StockAdjustedEvent`: Emitted during physical count adjustments or write-offs.
- `BranchCatalogModeChangedEvent`: Emitted when a branch toggles between `business_only`, `branch_only`, or `both`.

#### 3.3. Domain Exceptions & Boundary Invariants
The domain guards business invariants using 15 specialized domain exceptions:
- `InsufficientStockException`: Thrown if requested quantity exceeds available inventory ($Stock_{physical} - Stock_{reserved}$).
- `PurchaseLimitExceededException`: Thrown if order breaches min quantity, max per order, or 30-day customer purchase cap.
- `OrderReservationNotExpiredException`: Thrown if an operator attempts to forcibly cancel an active reservation prior to 96 hours without justification.
- `ReservationExpiredException`: Thrown if a customer submits payment after the 96-hour reservation has lapsed.
- `InvalidOrderStateTransitionException`: Thrown if an invalid state jump is requested (e.g., `completed` back to `submitted`).
- `InvalidPaymentReceiptException`: Thrown if a receipt lacks a valid image or bank reference code.
- `PaymentAlreadyVerifiedException`: Thrown if an operator attempts to verify an already settled order.
- `PostalTrackingCodeInvalidException`: Thrown if a postal code deviates from exactly 24 numeric digits.
- `UnbalancedLedgerException`: Thrown if any journal entry violates $\sum Debit = \sum Credit$.
- `InvalidLedgerEntryException`: Thrown if account codes or amounts are corrupted.
- `SubscriptionLimitExceededException`: Thrown when a merchant breaches branch, product, or manager quotas.
- `UnauthorizedDomainActionException`: Thrown when a user attempts an action outside their scoped tenant or role.
- `NegativeInventoryException`: Thrown if an adjustment would force physical stock below zero.
- `InvalidSkuException`: Thrown if SKU fails format criteria.
- `EntityNotFoundException`: Thrown when an aggregate root cannot be resolved by its identifier.

#### 3.4. Application Use Cases & DTOs (`src/Application`)
- `SubmitOrderHandler` (`SubmitOrderRequest` -> `SubmitOrderResponse`): Validates stock, calculates taxes, snapshots customer/address, locks reservation, and commits order.
- `VerifyPaymentReceiptHandler` (`VerifyPaymentReceiptRequest` -> `VerifyPaymentReceiptResponse`): Validates slip, updates order payment status, commits reserved stock, and dispatches ledger event.
- `ShipOrderHandler`: Validates 24-digit barcode, transitions order to `shipped`, sets dispatch timestamp.
- `CompleteOrderHandler`: Marks order terminal `completed`.
- `CancelOrderHandler` (`CancelOrderRequest` -> `CancelOrderResponse`): Cancels order, unlocks reserved inventory.
- `ReleaseExpiredReservationsHandler`: Scheduled worker job that scans for orders where `reservation_expires_at < now` and returns stock to available pool.
- `AdjustInventoryHandler`: Processes stock-taking adjustments and physical write-offs.
- `CheckPurchaseLimitsHandler`: Verifies customer rolling 30-day purchases against product caps.
- `CreateBranchHandler`: Validates branch quotas and creates isolated branch entities.
- `EscalateTicketHandler`: Validates Ultimate tier subscription entitlement and elevates ticket to SuperAdmin priority queue.


---

Based on an exhaustive codebase inspection of all 51 routing modules (`routes_*.php`), the pure Domain layer (`src/Domain`), the Application Use-Case orchestrators (`src/Application`), the Infrastructure persistence adapters and 25 SQLite database tables (`src/Infrastructure`), the RESTful controllers (`src/Http/Controllers`), and the Domain Event dispatcher, here is the **complete, deeply audited, English architectural specification** for the Befroosh frontend.

Every entity, table, route, parameter, business rule, and user interaction has been cataloged. **Nothing has been omitted.**

---

# 1. Complete Catalog of Frontend Pages (Must-Have vs. Preferred)

Grouped by user role and domain context:

### 1.1. Public & Storefront Pages (Unauthenticated & Shopper View)

| # | Page Name | Canonical Route | Classification | Access Role | Core Capabilities & UI Components |
|:---|:---|:---|:---|:---|:---|
| **1.1.1** | **Platform Landing Page** | `/` | **Must-Have** | Guest / Public | Hero showcase, value propositions (Card-to-Card, branch management, stock reservations), 4-tier pricing matrix (`#pricing`), merchant onboarding CTA, public footer. |
| **1.1.2** | **Platform Shops Directory** | `/shops` | **Must-Have** | Guest / Public | Directory grid of all active businesses on Befroosh, city/province filter, search by store name, product count badges, Card-to-Card status indicators. |
| **1.1.3** | **Business Storefront** | `/b/{biz_slug}` & `/s/{biz_slug}` | **Must-Have** | Guest / Public | Merchant sticky header, branch context switcher dropdown, omnibar search autocomplete (`Ctrl+K`), category filter pills, responsive 4-column product grid, floating quick bag drawer. |
| **1.1.4** | **Branch Storefront** | `/b/{biz_slug}/{branch_slug}` | **Must-Have** | Guest / Public | Independent branch catalog honoring `catalog_mode` (`business_only`, `branch_only`, `both`), branch-specific shipping fee and address, branch working hours banner. |
| **1.1.5** | **Product Detail Page (PDP)** | `/b/{biz_slug}/p/{prod_slug}` & `/s/.../p/...` | **Must-Have** | Guest / Public | High-res image gallery, Persian Toman price readout, real-time stock pill badge, SKU/Barcode display, purchase quantity selector with cap enforcement, "Add to Cart" and "Direct Buy" buttons, wishlist heart button. |
| **1.1.6** | **Merchant About Page** | `/shop/{slug}/about` | **Preferred** | Guest / Public | Rich-text rendered HTML (`about_html`) describing store heritage, physical location, mission, and licenses. |
| **1.1.7** | **Merchant Policies & Returns** | `/shop/{slug}/policies` | **Must-Have** | Guest / Public | Rich-text rendered HTML (`policies_html`) displaying 4-day reservation terms, return/exchange rules, and dispatch guarantees. |
| **1.1.8** | **Merchant FAQ Page** | `/shop/{slug}/faq` | **Preferred** | Guest / Public | Accordion Q&A component populated from store `faq_json`. |
| **1.1.9** | **Merchant Contact Page** | `/shop/{slug}/contact` | **Must-Have** | Guest / Public | Physical store address, phone numbers, Google Map placeholder, direct message form that submits to merchant's inbox (`shop_contact_messages`). |
| **1.1.10** | **Public Order Tracking Lookup** | `/orders/track` & `/track` | **Must-Have** | Guest / Public | Unauthenticated tracking tool querying by 8-character uppercase order UUID and optional recipient phone number; renders the full visual milestone stepper and postal tracking code. |
| **1.1.11** | **Platform Global Terms & Policies** | `/policies` | **Must-Have** | Guest / Public | Platform-wide privacy policy, user terms, electronic commerce regulations, and data retention disclosures. |
| **1.1.12** | **Error Pages** | `/errors/403`, `/errors/404`, `/errors/500` | **Must-Have** | Guest / Public | Branded, tactile error pages for access denied (403), resource not found (404), and server exception (500). |

---

### 1.2. Customer Account & Portal Pages

| # | Page Name | Canonical Route | Classification | Access Role | Core Capabilities & UI Components |
|:---|:---|:---|:---|:---|:---|
| **1.2.1** | **User Login** | `/login` | **Must-Have** | Guest | Login form accepting username or 10-digit mobile number, password, CSRF token, redirect parameter (`next`), brute-force lockout warning banner. |
| **1.2.2** | **Customer Registration** | `/register?type=customer` | **Must-Have** | Guest | First name, last name, nickname, 10-digit mobile (starting with `9`), username, password & confirmation. Triggers automatic guest cart and favorites synchronization upon completion. |
| **1.2.3** | **Customer Dashboard** | `/app/dashboard` | **Must-Have** | Customer | Metric cards (Total Orders, Pending Orders, Completed Orders, Lifetime Spend, Open Tickets), last order spotlight with quick-view, recent orders table. |
| **1.2.4** | **Multi-Shop Shopping Cart** | `/cart` | **Must-Have** | All Users | Cart items partitioned by merchant; independent subtotals, free shipping progress meters, unit quantity modifiers, remove item buttons, 7-day expiration notice, checkout CTA per merchant. |
| **1.2.5** | **Direct Buy Checkout Shortcut** | `/buy/{product_id}` | **Must-Have** | All Users | Instant single-item purchase bypass that constructs an immediate checkout session without disturbing the active cart. |
| **1.2.6** | **Multi-Step Checkout** | `/checkout` & `/orders/create` | **Must-Have** | Customer | Address selector/creator, 4-day (96-hour) stock reservation countdown timer banner, Bank Card mockup with one-click copy, drag-and-drop receipt slip uploader, transaction tracking number input, order summary sidebar. |
| **1.2.7** | **Customer Order History** | `/profile/orders` & `/orders` | **Must-Have** | Customer | Paginated list of customer orders with UUID, Jalali date, status badge, payment badge, Toman total, item count, link to order details. |
| **1.2.8** | **Customer Order Detail & Progress** | `/orders/{id}` | **Must-Have** | Customer | 5-stage Iran Post milestone progress stepper, 24-digit Iran Post tracking barcode with copy button, receipt verification status badge, re-upload receipt dropzone (if rejected), print invoice button, support ticket button. |
| **1.2.9** | **Customer Wishlist & Bookmarks** | `/favorites` & `/bookmarks` | **Must-Have** | All Users | Grid of bookmarked items; loads from `localStorage` for guests and persists to `product_bookmarks` for logged-in users, with one-click remove and add-to-cart actions. |
| **1.2.10** | **Delivery Address Book** | `/profile/addresses` | **Must-Have** | Customer | Saved address cards with default address badge, recipient name, phone, postal code, street address, "Set as Default", "Edit", and "Delete" actions. |
| **1.2.11** | **Add / Edit Delivery Address** | `/profile/addresses/create` & `.../{id}/edit` | **Must-Have** | Customer | Province, city, full street address, 10-digit postal code, recipient full name, recipient phone number. |
| **1.2.12** | **Customer Profile Settings** | `/profile` & `/account/profile` | **Must-Have** | Customer | Edit first name, last name, nickname, national code (10-digit), and email. Displays immutable username and verified phone. |
| **1.2.13** | **Change Password** | `/profile/password` | **Must-Have** | Customer | Current password, new password, confirm new password. |
| **1.2.14** | **Customer Printable Invoice** | `/orders/{id}/print` & `.../invoice` | **Must-Have** | Customer / Staff | Standard A4 printable tax invoice containing shop registration info, customer address snapshot, line items breakdown, VAT, discounts, shipping fee, and payment tracking reference. |
| **1.2.15** | **Customer Support Ticket Center** | `/tickets` | **Must-Have** | Customer | Filter by open/closed status, search subject and body, list of tickets with unread reply badges and last updated timestamps. |
| **1.2.16** | **Create Support Ticket** | `/tickets/create` | **Must-Have** | Customer | Subject, body textarea, optional file attachment upload (JPEG/PNG/PDF up to 10MB), optional order dropdown linking ticket to a specific order. |
| **1.2.17** | **View Support Ticket Thread** | `/tickets/{id}` | **Must-Have** | Customer | Threaded chat view between customer and merchant staff, attachment download pills, reply form with file upload, close ticket button. |

---

### 1.3. Merchant & Branch Operations Pages (Staff, Branch Managers, Shop Owners)

| # | Page Name | Canonical Route | Classification | Access Role | Core Capabilities & UI Components |
|:---|:---|:---|:---|:---|:---|
| **1.3.1** | **Merchant Registration (Business Onboarding)** | `/register?type=business_owner` | **Must-Have** | Guest | Register owner account, define business name and English URL slug, automatically creates primary shop, main branch (`main`), and manager assignment. |
| **1.3.2** | **Merchant Operations Dashboard** | `/dashboard` & `/app/dashboard` | **Must-Have** | Staff / Owner | Real-time KPIs: Today's GMV, Orders Count Today, Pending Receipt Verifications, Low Stock Warnings, Expiring Reservations, Open Tickets. Branch context selector pill. |
| **1.3.3** | **Merchant Orders & Verification Queue** | `/merchant/orders` & `/orders` | **Must-Have** | Staff / Owner | Filter by order status, payment status, branch, date; search by UUID/customer/phone; Card-to-Card receipt inspection modal (zoom toggle, OCR check indicators, approve & settle button, reject with reason button). |
| **1.3.4** | **Order Adjustment / Edit by Staff** | `/orders/{id}/edit` | **Preferred** | Staff / Owner | Modify line items, adjust quantities, apply manual line discounts, recalculate grand total and tax prior to fulfillment. |
| **1.3.5** | **Iran Post Shipping Label Printer** | `/orders/{id}/shipping-label` | **Must-Have** | Staff / Warehouse | Printable Iran Post dispatch label with sender address, recipient address, postal code, barcode box, package weight in grams. |
| **1.3.6** | **Bulk Order Printing Center** | `/orders/bulk-print` | **Must-Have** | Staff / Warehouse | Batch selection of multiple orders to print consolidated invoices or shipping labels in a single PDF stream. |
| **1.3.7** | **Merchant Product Catalog** | `/products` | **Must-Have** | Staff / Owner | Search products, filter by branch, category, stock status (in-stock, low-stock, out-of-stock); displays SKU, barcode, unit, price, and stock levels. |
| **1.3.8** | **Create Product** | `/products/create` | **Must-Have** | Staff / Owner | Title, slug, category, branch allocation, price, cost price (COGS), initial stock, min stock alert, min order qty, max per order, max per month, tax rate, SKU, barcode, unit, weight (grams), image upload. |
| **1.3.9** | **Edit Product** | `/products/{id}/edit` | **Must-Have** | Staff / Owner | Full product modification, image replacement, branch reassignment, active/inactive toggle, soft delete. |
| **1.3.10** | **Category Management** | `/categories` | **Must-Have** | Staff / Owner | Category hierarchy grid, sort order modifiers, create category modal, delete category. |
| **1.3.11** | **Inventory Overview & Stock Valuation** | `/merchant/inventory` | **Must-Have** | Staff / Warehouse | Total SKUs count, Total physical units in stock, Total inventory asset cost value, Total retail value, Low stock alerts count, branch-level inventory allocation. |
| **1.3.12** | **Stock Inward Entry (Purchases / Intake)** | `/inventory/inward` | **Must-Have** | Staff / Warehouse | Select product, quantity received, unit purchase cost, invoice reference, notes; automatically posts balanced double-entry accounting records (`inventory_asset` vs `cash_bank`). |
| **1.3.13** | **Stock Adjustment & Variance** | `/inventory/adjustment` | **Must-Have** | Staff / Warehouse | Record physical count discrepancies, shrinkage, damaged goods write-offs (`write_off`), or customer returns (`return_in`) with audit reason. |
| **1.3.14** | **Inventory Transactions Audit Trail** | `/inventory/transactions` | **Must-Have** | Staff / Warehouse | Paginated ledger of every inward, outward order, adjustment, and return transaction with operator ID and reference number. |
| **1.3.15** | **Printable Physical Count Sheet** | `/inventory/count-sheet` | **Preferred** | Staff / Warehouse | Printable A4 warehouse audit sheet with SKU, title, system stock, and blank boxes for physical stock verification. |
| **1.3.16** | **Branch Management Center** | `/app/branches` | **Must-Have** | Business Owner | Branch listing with catalog mode badges (`business_only`, `branch_only`, `both`), main branch badge, shipping group connection, contact info. |
| **1.3.17** | **Create Branch** | `/app/branches/create` | **Must-Have** | Business Owner | Name, unique slug, logo, phone, address, catalog mode, default shipping cost, free shipping threshold; enforces subscription branch quota. |
| **1.3.18** | **Edit Branch & Manager Assignments** | `/app/branches/edit/{id}` | **Must-Have** | Business Owner | Edit branch info, associate with shipping group, assign internal branch managers (`branch_user_assignments`). |
| **1.3.19** | **Central Shipping Groups** | `/app/shipping-groups` | **Preferred** | Business Owner | Group neighboring branches into centralized distribution hubs with shared shipping fee schedules and hub addresses. |
| **1.3.20** | **Shop Settings & Bank Cards** | `/shop/settings` | **Must-Have** | Business Owner | Support phone, email, physical address, national ID, economic code, VAT tax rate, reservation expiration days (1–14), bank card accounts management (Card Number, Cardholder, Bank, IBAN), Rich-Text editors for Policies and About pages. |
| **1.3.21** | **Elevate Staff Manager** | `/shop/elevate-manager` | **Must-Have** | Business Owner | Elevate an existing registered user to `shop_manager` or `branch_manager` by mobile phone; enforces subscription manager quota. |
| **1.3.22** | **Storefront Contact Messages Inbox** | `/shop/messages` | **Must-Have** | Staff / Owner | Inbox of customer inquiries submitted via public contact form; sender name, phone number, timestamp, unread badge, "Call Customer" CTA, "Mark as Read", and "Delete". |
| **1.3.23** | **Merchant Customers Directory** | `/customers` | **Must-Have** | Staff / Owner | Search customers by name, mobile, username; view active/inactive status, registration date, address count, and lifetime orders. |
| **1.3.24** | **Manual Customer Creation by Staff** | `/customers/create` | **Must-Have** | Staff / Owner | Register new customer on their behalf with initial shipping address for phone orders. |
| **1.3.25** | **Manual Phone Order Creation** | `/orders/create?customer_id=...` | **Must-Have** | Staff / Owner | Staff-assisted checkout selecting customer, products, branch stock, shipping method, and payment terms. |
| **1.3.26** | **Executive Reports & VAT Center** | `/reports` | **Must-Have** | Staff / Owner | Jalali date range filter, Gross Revenue, Taxable Sales, VAT Collected, Total Shipping Collected, Average Order Value (AOV), Top 7 Best-Selling Products, 7-Day Sales Trend graph. |
| **1.3.27** | **Merchant Subscription & Quotas View** | `/app/plans/my-plan` | **Must-Have** | Business Owner | Visual progress meters showing percentage used of Branches, Products, and Staff Managers quotas; current tier badge, expiry date, upgrade plan CTA. |

---

### 1.4. Store Accounting & Financial Ledger Pages

| # | Page Name | Canonical Route | Classification | Access Role | Core Capabilities & UI Components |
|:---|:---|:---|:---|:---|:---|
| **1.4.1** | **Accounting Overview & Balance Verification** | `/merchant/accounting` | **Must-Have** | Owner / Accountant | Mathematical balance indicator ($\Delta = |\sum \text{Debit} - \sum \text{Credit}| < 0.01$), Cash & Bank Asset balance, Inventory Asset value, Sales Revenue, COGS, Gross Profit, Gross Margin percentage. |
| **1.4.2** | **General Ledger & Journal Vouchers** | `/merchant/accounting/ledger` | **Must-Have** | Owner / Accountant | Paginated table of all double-entry journal vouchers; columns: Voucher ID, Jalali Date, Voucher Type, Account Code, Account Name, Debit, Credit, Operator, Balanced status badge. Filter by account and transaction type. |
| **1.4.3** | **4-Column Trial Balance** | Embedded in `/merchant/accounting` | **Must-Have** | Owner / Accountant | Chart of accounts hierarchy (Current Assets, Current Liabilities, Equity, Revenue, Cost of Goods Sold, Operating Expenses) with initial debit/credit and net closing balance. |
| **1.4.4** | **Quarterly VAT Tax Report** | `/accounting/tax-report` | **Must-Have** | Owner / Accountant | Tabulated statutory tax report detailing gross taxable sales and 9% VAT collected for quarterly submission to the Iranian National Tax Administration (سازمان امور مالیاتی). |
| **1.4.5** | **Manual Journal Voucher Entry** | `/accounting/manual` | **Preferred** | Owner / Accountant | Interface to post balanced manual adjustment entries (e.g., bank transfer fees, depreciation, capital injections). |

---

### 1.5. Platform SuperAdmin Control Panel Pages

| # | Page Name | Canonical Route | Classification | Access Role | Core Capabilities & UI Components |
|:---|:---|:---|:---|:---|:---|
| **1.5.1** | **Platform Tenants Management** | `/shops/manage` | **Must-Have** | SuperAdmin | Directory of all multi-tenant businesses, owner nickname, phone, product count, total orders, lifetime GMV, active/suspended status toggle, switch active shop shortcut. |
| **1.5.2** | **Create Business / Shop (Admin)** | `/shops/create` | **Must-Have** | SuperAdmin | Manual creation of new business tenants, assigning owner ID and initial plan. |
| **1.5.3** | **Edit Business / Shop (Admin)** | `/shops/{id}/edit` | **Must-Have** | SuperAdmin | Edit tenant slug, name, plan tier, expiration date, active state. |
| **1.5.4** | **Platform Staff & User Management** | `/admins` | **Must-Have** | SuperAdmin / Admin | Paginated list of SuperAdmins, Admins, Shop Owners, and Shop Managers; role filter, shop filter, edit access, "Impersonate User" CTA. |
| **1.5.5** | **Create Platform Admin / Staff** | `/admins/create` | **Must-Have** | SuperAdmin | Register new SuperAdmin, Admin, or elevated staff member. |
| **1.5.6** | **Edit Platform User & Permissions** | `/admins/{id}/edit` | **Must-Have** | SuperAdmin | Modify roles, assigned shop, password reset, active/deactivated toggle. |
| **1.5.7** | **Platform Subscriptions & Quota Matrix** | `/admin/plans` | **Must-Have** | SuperAdmin / Admin | 4-Tier plan matrix configuration (`Free`, `Plus`, `Pro`, `Ultimate`), quota limits editor, tenant consumption audit table, and VIP Emergency Escalated Tickets Queue. |
| **1.5.8** | **System Data Retention & Expiration Policies** | `/settings/retention` | **Must-Have** | SuperAdmin | Platform-wide settings for Customer Cart retention period (days) and Unpaid Stock Reservation duration (days) persisted to `app_meta`. |
| **1.5.9** | **System Incident & Violation Reports** | `/reports/system` | **Must-Have** | SuperAdmin / Admin | Create, triage, and audit merchant abuse reports, security violations, and technical incidents across severity levels (`low`, `normal`, `high`, `critical`) and statuses (`open`, `in_progress`, `resolved`, `closed`). |

---

# 2. Complete List of End-to-End User Flows

The 22 primary business flows spanning the entire lifecycle of the application:

### Flow 1: Guest Discovery, Local Cart & Automatic Post-Registration Sync
1. An unauthenticated guest accesses `/b/{slug}`.
2. Typing in the search bar triggers live queries to `/api/v1/store/{slug}/products?q=...`, rendering instant dropdown results.
3. The guest clicks "Add to Bag" or clicks the Wishlist heart button; items are persisted into client-side `localStorage`.
4. The guest navigates to `/cart` and proceeds to checkout, which prompts authentication.
5. The guest registers at `/register?type=customer`, supplying a 10-digit mobile number starting with `9`.
6. Upon successful registration and session creation, the client-side JavaScript automatically dispatches `POST /api/sync-guest-data` with the cached cart and wishlist items.
7. The backend validates and inserts the records into `cart_items` and `product_bookmarks`, providing a seamless transition from guest browsing to authenticated shopping.

### Flow 2: Multi-Step Checkout, 4-Day Reservation Lock & Card-to-Card Payment
1. An authenticated customer enters `/checkout`.
2. The customer selects an existing delivery address or creates a new one.
3. The order is submitted: `SubmitOrderHandler` verifies available stock via `Product::assertCanPurchase` (validating physical on-hand stock, min order quantity, per-order cap, and rolling 30-day customer cap).
4. Stock is reserved (`Product::reserveStock`); the order is persisted with `status = 'submitted'`, `payment_status = 'unpaid'`, and `reservation_expires_at = now + 4 days (96 hours)`.
5. An amber countdown urgency banner is displayed (`۹۵:۵۹:۴۵`).
6. The customer views the store's active bank card account (`shop_bank_cards`) with one-click copy for the 16-digit card number and Sheba IBAN.
7. The customer transfers the funds via mobile banking, takes a screenshot of the receipt, and drops the image into the receipt dropzone.
8. The customer enters the 10-digit bank transaction reference number and submits `POST /orders/{id}/payment/card-to-card`.
9. The receipt image is stored in `storage/receipts`, updating `payment_status = 'pending_verification'`, `receipt_status = 'pending'`, and setting `seen_by_admin = 0` to alert merchant staff.

### Flow 3: Single-Item Instant Purchase ("Direct Buy")
1. A customer clicks "خرید فوری" (Direct Buy) on a product card or PDP (`/buy/{product_id}`).
2. The controller constructs a transient single-item order session, bypassing existing items in `/cart`.
3. The customer is redirected directly to the address selection and payment confirmation view with immediate stock reservation.

### Flow 4: Merchant Receipt Verification Queue & Split-View Audit Modal
1. Merchant staff log into `/merchant/orders` and inspect the "فیش‌های در انتظار بررسی" (Pending Slips) KPI card.
2. Staff click "بررسی فیش" (Audit Receipt), opening a side-by-side inspection modal:
   - *Left Pane*: Zoomable high-resolution scan of the bank transfer slip with PDF preview support and optical verification badges.
   - *Right Pane*: Expected order total in Toman, customer contact details, matching transaction reference number, and destination bank card selector.
3. **If Approved**: Staff click "تأیید فیش و صدور سند حسابداری" (`POST /orders/{id}/payment/verify` with `action=approve`):
   - Order transitions to `status = 'paid'`, `payment_status = 'paid'`, and `receipt_status = 'verified'`.
   - Reserved stock is committed to physical deduction (`Product::commitReservation`).
   - `PaymentReceiptApprovedEvent` is dispatched.
   - The double-entry accounting subscriber automatically posts balanced ledger records:
     - Debit `cash_bank` for the total funds received.
     - Debit `discounts` (if a discount was applied).
     - Credit `sales_revenue` for product subtotal.
     - Credit `shipping_revenue` for shipping fee.
     - Credit `tax_payable` for 9% VAT.
4. **If Rejected**: Staff enter a rejection reason (e.g., "مبلغ واریزی کسری دارد" or "فیش جعلی") and click "رد فیش":
   - Order transitions to `payment_status = 'rejected'` and `receipt_status = 'rejected'`.
   - `seen_by_customer = 0` is set to alert the customer.
   - The re-upload dropzone is reactivated in the customer's order portal.

### Flow 5: Automated 4-Day Reservation Expiry & Stock Restoration
1. If an order remains in `status = 'submitted'` and the 96-hour window expires without verified payment:
2. The automated lifecycle engine (`ReleaseExpiredReservationsHandler`) detects the expired record.
3. Reserved stock is returned to physical inventory (`Product::releaseReservedStock`).
4. The order status transitions to `status = 'canceled'` with `cancellation_reason = 'انقضای مهلت رزرو ۴ روزه'`.
5. `ReservationExpiredEvent` and `OrderCanceledEvent` are dispatched, ensuring zero stock leakage.

### Flow 6: Packaging, 24-Digit Postal Code Assignment & Dispatch
1. An order with `status = 'paid'` enters the fulfillment queue.
2. Warehouse staff package the items and print the standard dispatch label (`/orders/{id}/shipping-label`).
3. Packages are handed to the Iranian National Post Office (شرکت ملی پست).
4. Staff enter the official 24-digit postal tracking barcode and submit `POST /orders/{id}/ship`.
5. The `PostalTrackingCode` value object verifies the exact 24-digit numeric format.
6. Order transitions to `status = 'shipped'`, records `shipped_at`, and dispatches `OrderShippedEvent`.
7. In the customer portal, milestone 4 illuminates, and the direct tracking link to `tracking.post.ir` activates.

### Flow 7: Final Delivery & Terminal Completion
1. Upon confirmed delivery to the recipient, staff submit `POST /orders/{id}/complete`.
2. Order transitions to `status = 'completed'` and records `completed_at`.
3. In the customer portal, the 5-stage stepper renders a final green checkmark (`✓`).
4. The entity enforces terminal status immutability (`assertCanTransitionTo`), prohibiting further state changes.

### Flow 8: Order Cancellation & Inventory Restoration
1. Prior to shipping, either the customer or staff initiates cancellation via `POST /orders/{id}/cancel`.
2. A cancellation reason is captured.
3. Order transitions to `status = 'canceled'`.
4. `ReleaseStockOnOrderCanceled` subscriber triggers immediately:
   - If unpaid/submitted: releases reserved stock.
   - If paid: restores on-hand physical stock and records an inventory transaction of type `return_in`.

### Flow 9: Order Reordering
1. A customer views a past completed order and clicks "سفارش مجدد" (Reorder) (`POST /orders/{id}/reorder`).
2. The backend validates current stock availability for all original items.
3. Available items are populated directly into the customer's active `/cart`, and the customer is redirected to review.

### Flow 10: Threaded Customer Support Ticketing
1. A customer navigates to `/tickets/create`, entering a subject, message body, optional attachment (up to 10MB), and linking an order ID.
2. Record is created in `tickets`, message in `ticket_messages`, attachment in `ticket_attachments`, and order link in `ticket_order_relations`.
3. Merchant staff receive an unread badge on `/tickets`, review the inquiry, and respond with attachments.
4. Notifications flip `seen_by_customer = 0` to alert the user. Staff can close or reopen tickets as needed.

### Flow 11: Ultimate Tier Emergency VIP Ticket Escalation
1. An Ultimate Tier merchant encounters a critical platform issue requiring immediate SuperAdmin intervention.
2. The merchant clicks "ارجاع اضطراری به مدیر ارشد" (`POST /admin/tickets/escalate`).
3. `EscalateTicketHandler` verifies store subscription:
   - If Free, Plus, or Pro: rejects with `SubscriptionLimitExceededException` (403 Forbidden).
   - If Ultimate: sets `is_escalated_to_superadmin = 1` and `elevated_at = now`.
4. The ticket is immediately elevated to the top of the SuperAdmin SLA Queue in `/admin/plans`.

### Flow 12: Multi-Branch Hierarchy & Catalog Mode Configuration
1. A business owner creates a new branch at `/app/branches/create`.
2. The subscription branch quota is validated against the active plan ceiling.
3. The owner specifies the branch name, unique slug, and **Catalog Mode**:
   - `business_only`: branch displays only business-wide centralized products.
   - `branch_only`: branch displays only products physically assigned to this branch.
   - `both`: displays a hybrid catalog of centralized and local items.
4. Storefront queries (`ProductRepository::findForStorefront`) dynamically apply these scoping rules.

### Flow 13: Centralized Distribution Hubs (Shipping Groups)
1. A merchant creates a shipping group at `/app/shipping-groups` (e.g., "West Tehran Logistics Hub").
2. Standard shipping cost, free shipping threshold, and hub address are defined.
3. In `/app/branches/edit/{id}`, multiple local branches are assigned to this shipping group.
4. Orders from any associated branch use the unified shipping fee schedule.

### Flow 14: Staff Role Elevation & Branch Assignment
1. An owner enters a registered user's 10-digit mobile number at `/shop/elevate-manager`.
2. The system verifies that the user is not a SuperAdmin/Admin and that the manager quota (`max_managers`) is not exceeded.
3. The user's role is updated to `shop_manager` or `branch_manager`, and a record is created in `branch_user_assignments`.
4. The elevated manager now gains scoped access to orders and inventory for their designated branch.

### Flow 15: Stock Inward Intake & Automated Asset Accounting
1. Warehouse staff navigate to `/inventory/inward` and select a product, received quantity, unit cost, invoice reference, and notes.
2. `record_inventory_tx` updates on-hand inventory (`stock_quantity`) and updates unit `cost_price`.
3. Balanced double-entry accounting records are automatically posted:
   - Debit `inventory_asset` (increase in inventory value).
   - Credit `cash_bank` (cash outflow or accounts payable).

### Flow 16: Warehouse Stock-Taking, Count Sheet Audit & Variance Adjustment
1. Staff print the physical count sheet at `/inventory/count-sheet` and perform physical warehouse counts.
2. Staff navigate to `/inventory/adjustment` to log any discrepancy:
   - `adjustment_plus`: physical surplus discovered.
   - `adjustment_minus` / `write_off`: shrinkage, theft, or damaged goods.
3. Inventory counts are adjusted, and corresponding adjustment loss/gain entries are posted to the general ledger.

### Flow 17: Double-Entry Trial Balance & Ledger Reconciliation
1. An accountant navigates to `/merchant/accounting`.
2. The system executes $\sum \text{Debit} - \sum \text{Credit}$ across all store vouchers.
3. If difference is zero, the green "100% Balanced" banner illuminates.
4. The 4-column trial balance calculates net closing balances across Current Assets, Liabilities, Revenue, COGS, and Expenses.

### Flow 18: Public Storefront Contact Form & Merchant Inbox
1. A visitor fills out the contact form at `/shop/{slug}/contact`.
2. Record is created in `shop_contact_messages` with `is_read = 0`.
3. Merchant views message in `/shop/messages`, clicks "Call Customer", marks as read, or deletes.

### Flow 19: Subscription Plan Quota Management & Upgrades
1. SuperAdmin manages plan tiers in `/admin/plans`.
2. Using `/admin/plans/assign`, SuperAdmin assigns a plan tier and renewal duration (1, 3, or 12 months) to a merchant, updating `subscription_expires_at`.
3. In `/app/plans/my-plan`, the merchant monitors quota usage gauges for branches, products, and managers.

### Flow 20: SuperAdmin User Impersonation
1. SuperAdmin clicks "ورود با نام" (Impersonate) next to any user in `/admins`.
2. `POST /impersonate/{id}` stores the original SuperAdmin ID in `$_SESSION['impersonator_id']` and switches the session identity.
3. A yellow banner notifies that impersonation is active.
4. Clicking "بازگشت به حساب اصلی" (`POST /stop-impersonation`) restores the original SuperAdmin session without re-authenticating.

### Flow 21: Platform Data Retention Configuration
1. SuperAdmin configures Cart retention days (default: 7) and Unpaid Stock Reservation days (default: 4) at `/settings/retention`.
2. Values are stored in `app_meta` and govern automated background garbage collection routines.

### Flow 22: Platform Incident & Violation Reporting
1. An admin submits a report at `/reports/system` specifying category (`abuse`, `security`, `bug`), severity, and tenant ID.
2. Records are tracked in `system_reports` through statuses `open`, `in_progress`, `resolved`, and `closed`.

---

# 3. Exhaustive Catalog of User Actions & System Mutations

Categorized by domain subsystem:

### 3.1. Authentication, Profile & Identity Actions
- `ACTION_LOGIN_SUBMIT`: Submit credentials; triggers rate limiter, checks lockout table, regenerates session ID.
- `ACTION_LOGOUT`: Destroy session, clear cookies, invalidate CSRF token.
- `ACTION_REGISTER_CUSTOMER`: Create customer account with 10-digit mobile number, first/last name, username, password.
- `ACTION_REGISTER_BUSINESS`: Create merchant account, shop record, default main branch, and manager assignment.
- `ACTION_PROFILE_UPDATE`: Modify nickname, first/last name, national code, email.
- `ACTION_PASSWORD_UPDATE`: Update account password with old password verification.
- `ACTION_ADDRESS_CREATE`: Add delivery address with recipient name, phone, postal code, state, city, and street address.
- `ACTION_ADDRESS_UPDATE`: Edit existing delivery address attributes.
- `ACTION_ADDRESS_DELETE`: Remove address record.
- `ACTION_ADDRESS_SET_DEFAULT`: Set chosen address as the default for all future checkouts.

### 3.2. Storefront, Catalog & Browsing Actions
- `ACTION_BRANCH_SWITCH_PUBLIC`: Switch active branch via header pill selector.
- `ACTION_SEARCH_QUERY`: Asynchronously query `/api/v1/store/{slug}/products?q=...` with debouncing.
- `ACTION_CATEGORY_FILTER`: Filter product grid by category pill click.
- `ACTION_STOCK_FILTER`: Toggle display between all products and in-stock only.
- `ACTION_PDP_NAVIGATE`: Open detailed product page.
- `ACTION_WISHLIST_TOGGLE`: Add/remove product from bookmarks (localStorage for guests, DB for customers).
- `ACTION_CONTACT_SUBMIT`: Submit inquiry from public contact form to store inbox.

### 3.3. Shopping Cart & Direct Purchase Actions
- `ACTION_CART_ADD`: Add product to cart with quantity, checking on-hand stock and purchase caps.
- `ACTION_CART_QUANTITY_CHANGE`: Increment/decrement item quantity in cart.
- `ACTION_CART_ITEM_REMOVE`: Delete line item from cart.
- `ACTION_DIRECT_BUY_TRIGGER`: Bypass multi-shop cart and initiate immediate single-product checkout session.
- `ACTION_DRAWER_TOGGLE`: Open/close slide-over cart drawer.
- `ACTION_SYNC_GUEST_DATA`: Asynchronously sync guest localStorage cart and bookmarks to database upon login.

### 3.4. Checkout & Offline Payment Actions
- `ACTION_CHECKOUT_ADDRESS_SELECT`: Select radio button for delivery address.
- `ACTION_COPY_CARD_NUMBER`: Copy 16-digit bank card number with Persian-to-ASCII digit normalization.
- `ACTION_COPY_SHABA_NUMBER`: Copy 24-digit Sheba IBAN to clipboard.
- `ACTION_RECEIPT_DROP`: Drag-and-drop payment slip image/PDF into upload dropzone.
- `ACTION_RECEIPT_BROWSE`: Select payment slip file via native file picker.
- `ACTION_INPUT_PAYMENT_REF`: Enter 10-digit bank transaction reference number.
- `ACTION_ORDER_SUBMIT_CONFIRM`: Finalize order submission; locks 4-day stock reservation.

### 3.5. Order Management & Logistics Actions
- `ACTION_ORDER_STATUS_FILTER`: Filter orders table by status (`submitted`, `paid`, `shipped`, `completed`, `canceled`).
- `ACTION_PAYMENT_STATUS_FILTER`: Filter orders table by payment state (`unpaid`, `pending_verification`, `paid`, `rejected`).
- `ACTION_ORDER_SEARCH_FILTER`: Search orders by UUID, customer name, or phone number.
- `ACTION_RECEIPT_ZOOM_TOGGLE`: Toggle high-resolution zoom mode in receipt audit modal.
- `ACTION_RECEIPT_APPROVE`: Approve payment slip, commit stock deduction, and post balanced double-entry accounting records.
- `ACTION_RECEIPT_REJECT`: Reject payment slip with mandatory rejection reason text.
- `ACTION_ORDER_SHIP_SUBMIT`: Submit 24-digit Iran Post tracking barcode and mark order as shipped.
- `ACTION_ORDER_COMPLETE_SUBMIT`: Mark shipped order as delivered and completed.
- `ACTION_ORDER_CANCEL_SUBMIT`: Cancel order with reason text and restore reserved stock.
- `ACTION_ORDER_REORDER_TRIGGER`: Repopulate active cart with items from a previous order.
- `ACTION_ORDER_PRINT_INVOICE`: Open standard printable A4 invoice view.
- `ACTION_ORDER_PRINT_LABEL`: Open printable Iran Post dispatch label.
- `ACTION_ORDER_BULK_PRINT`: Select multiple orders via checkboxes and launch batch printing.
- `ACTION_ORDER_PUBLIC_TRACK`: Query order progress by UUID and phone number without login.

### 3.6. Inventory & Product Administration Actions
- `ACTION_PRODUCT_CREATE_SUBMIT`: Create product with pricing, SKU, barcode, purchase caps, and image upload.
- `ACTION_PRODUCT_UPDATE_SUBMIT`: Modify product details, pricing, branch assignment, and active status.
- `ACTION_PRODUCT_DELETE_SUBMIT`: Soft-delete product.
- `ACTION_CATEGORY_CREATE_SUBMIT`: Create product category with name and sort order.
- `ACTION_CATEGORY_DELETE_SUBMIT`: Delete product category.
- `ACTION_INWARD_STOCK_SUBMIT`: Log stock intake, update unit cost, and record accounting asset voucher.
- `ACTION_STOCK_ADJUSTMENT_SUBMIT`: Log physical count variance or damaged goods write-off.
- `ACTION_PRINT_COUNT_SHEET`: Print warehouse physical inventory count audit sheet.

### 3.7. Double-Entry Accounting Actions
- `ACTION_LEDGER_FILTER_SUBMIT`: Filter general ledger by account code, voucher type, and date range.
- `ACTION_TAX_REPORT_FILTER`: Generate quarterly VAT report for custom date ranges.
- `ACTION_MANUAL_VOUCHER_SUBMIT`: Post manual debit/credit journal vouchers between accounts.
- `ACTION_EXPORT_FINANCIAL_REPORTS`: Export trial balance and general ledger records.

### 3.8. Multi-Tenancy & Tenant Settings Actions
- `ACTION_SWITCH_BUSINESS`: Switch active business tenant context for multi-store owners.
- `ACTION_SWITCH_BRANCH_SCOPE`: Switch dashboard view between specific branch and business-wide aggregate.
- `ACTION_BRANCH_CREATE_SUBMIT`: Create branch with catalog mode and shipping schedules.
- `ACTION_BRANCH_UPDATE_SUBMIT`: Edit branch details and assign shipping group.
- `ACTION_SHIPPING_GROUP_CREATE`: Define centralized logistics hub with unified shipping rates.
- `ACTION_ELEVATE_MANAGER_SUBMIT`: Elevate user to branch manager by mobile number.
- `ACTION_SHOP_SETTINGS_SAVE`: Update store contact info, VAT tax rate, reservation window, bank cards, and rich text policies.
- `ACTION_CONTACT_MSG_MARK_READ`: Mark customer contact message as read.
- `ACTION_CONTACT_MSG_DELETE`: Delete customer contact message.

### 3.9. Support Ticketing Actions
- `ACTION_TICKET_CREATE_SUBMIT`: Submit ticket with subject, message, attachments, and optional order link.
- `ACTION_TICKET_REPLY_SUBMIT`: Post reply message with optional attachments.
- `ACTION_TICKET_CLOSE_SUBMIT`: Close resolved ticket.
- `ACTION_TICKET_REOPEN_SUBMIT`: Reopen closed ticket.
- `ACTION_TICKET_ESCALATE_VIP`: Escalate ticket to SuperAdmin SLA queue (Ultimate tier only).

### 3.10. SuperAdmin Control Actions
- `ACTION_IMPERSONATE_USER`: Impersonate any user account.
- `ACTION_STOP_IMPERSONATION`: Revert to original SuperAdmin session.
- `ACTION_PLAN_UPDATE_SUBMIT`: Modify subscription plan pricing, limits, and feature flags.
- `ACTION_PLAN_ASSIGN_SUBMIT`: Assign subscription plan and extend renewal expiration date for a store.
- `ACTION_RETENTION_SETTINGS_SAVE`: Configure cart and reservation retention windows in `app_meta`.
- `ACTION_SYSTEM_REPORT_SUBMIT`: File platform audit report.
- `ACTION_SYSTEM_REPORT_STATUS_UPDATE`: Transition system report status.

---

# 4. Major Features & Recursive Sub-Features Breakdown

The backend is composed of **12 Major Features**, recursively detailed down to the smallest atomic capability:

---

## Major Feature 1: Multi-Tenancy, Multi-Business & Branch Hierarchy
### 1.1. Tenant Business Entity (`shops` table)
- **1.1.1. Identification & Routing**
  - `id`: Auto-increment integer primary key.
  - `name`: Display name of the business.
  - `slug`: Unique URL slug validated against `^[a-zA-Z0-9\-]+$`.
- **1.1.2. Fiscal & Regulatory Attributes**
  - `national_id`: 10- or 11-digit Iranian national identity number.
  - `economic_code`: 12-digit Iranian economic registration code.
  - `tax_rate`: Percentage VAT rate applied to orders (e.g., 0.09 for 9%).
- **1.1.3. Shipping & Reservation Settings**
  - `default_shipping_cost`: Base shipping charge in Toman.
  - `free_shipping_threshold`: Cart subtotal at which shipping becomes zero.
  - `reservation_days`: Duration (1 to 14 days; default 4) for unpaid stock locks.
  - `card_to_card_enabled`: Boolean flag enabling/disabling offline bank card payments.
  - `zarinpal_merchant_id`: Optional merchant gateway ID for online payment hooks.
- **1.1.4. Rich-Text Storefront Content**
  - `policies_html`: Sanitized HTML content for the store policies page.
  - `about_html`: Sanitized HTML content for the store about page.
  - `faq_json`: JSON string storing questions and answers array.

### 1.2. Multi-Branch Hierarchy (`branches` table)
- **1.2.1. Branch Identity & Scoping**
  - `business_id`: Foreign key reference to parent shop.
  - `slug`: Branch slug unique within the business (`UNIQUE(business_id, slug)`).
  - `is_main`: Boolean flag identifying the primary branch of the business.
- **1.2.2. Catalog Modes (`catalog_mode` ENUM)**
  - `business_only`: Branch displays only centralized business items.
  - `branch_only`: Branch displays only items physically allocated to this branch (`p.branch_id = branch.id`).
  - `both`: Branch displays both centralized items (`p.branch_id IS NULL`) and local items.
- **1.2.3. Independent Branch Logistics**
  - `default_shipping_cost` & `free_shipping_threshold`: Independent rates overriding parent business rates when set.
  - `shipping_group_id`: Foreign key linking branch to a central logistics hub.

### 1.3. Centralized Shipping Groups (`shipping_groups` table)
- **1.3.1. Hub Attributes**: `business_id`, `name`, `central_hub_address`, `shipping_cost`, `free_shipping_threshold`.
- **1.3.2. Order Association**: Orders record `shipping_group_id` when fulfilled via a centralized distribution hub.

### 1.4. Staff & Role-Based Access Control (`users` & `branch_user_assignments` tables)
- **1.4.1. Hierarchical Role Enums**
  - `superadmin`: Unrestricted platform-wide control.
  - `admin`: Level-2 platform administration.
  - `business_owner` / `shop_owner`: Full administrative rights over their business and branches.
  - `branch_manager` / `shop_manager` / `manager`: Scoped operations restricted to their assigned branch.
  - `customer`: Public shopper and account holder.
- **1.4.2. Scoped Branch Assignments**
  - Table maps `user_id`, `branch_id`, and `business_id` with role constraint.

---

## Major Feature 2: Product Catalog, Stock Tracking & Warehouse Auditing
### 2.1. Product Domain Entity (`products` table)
- **2.1.1. Identification & Classification**
  - `title`, `slug`, `category_id`, `branch_id`, `is_business_product`.
- **2.1.2. Dual-Pricing Architecture**
  - `price`: Retail selling price in Toman.
  - `cost_price`: Unit acquisition cost in Toman (used for inventory asset valuation and COGS calculations).
- **2.1.3. Warehouse Telemetry**
  - `sku`: Unique stock-keeping unit string (`Sku` Value Object).
  - `barcode`: Barcode string for physical scanner ingestion.
  - `unit`: Measurement unit (e.g., عدد, بسته, کیلوگرم).
  - `weight_grams`: Weight in grams used for postal shipping calculations.
- **2.1.4. Media & State**
  - `image_path`: Path relative to `storage/products`.
  - `active`: Boolean toggle for catalog visibility.
  - `deleted_at`: Timestamp for soft-delete operations.

### 2.2. Physical vs. Reserved Inventory Mathematics
- **2.2.1. Physical Stock (`stock_quantity`)**: Total units physically present in the warehouse.
- **2.2.2. Reserved Stock (`reserved_quantity`)**: Total units locked in unpaid orders (`status = 'submitted'`).
- **2.2.3. Available Stock (`available_stock`)**: Dynamically computed:
  $$\text{AvailableStock} = \max(0.0, \text{stock\_quantity} - \text{reserved\_quantity})$$
- **2.2.4. In-Stock Predicate**: `isInStock()` returns true if `available_stock > 0`.
- **2.2.5. Low Stock Threshold (`min_stock_alert`)**: Triggers low-stock warning chips when on-hand stock falls below threshold.

### 2.3. Stock Transaction Audit Trail (`inventory_transactions` table)
- **2.3.1. Transaction Types (`type` ENUM)**
  - `inward`: Stock intake from supplier purchase.
  - `outward_order`: Physical dispatch upon order payment approval.
  - `adjustment_plus`: Physical count surplus adjustment.
  - `adjustment_minus`: Physical count deficit adjustment.
  - `write_off`: Loss, damage, or expiration write-off.
  - `return_in`: Stock returned from order cancellation.
- **2.3.2. Transaction Metadata**: `quantity`, `unit_cost`, `reference_type`, `reference_id`, `notes`, `created_by_id`, `created_at`.

---

## Major Feature 3: Triple-Tier Anti-Hoarding & Purchase Protection
### 3.1. Minimum Order Quantity (`min_order_qty`)
- Prevents orders containing fewer than the minimum required units.
### 3.2. Maximum Units Per Order (`max_per_order`)
- Caps the maximum units of a product allowed in a single order.
### 3.3. 30-Day Rolling Customer Purchase Cap (`max_per_month`)
- Calculates the customer's total successful purchases over the trailing 30 days:
  $$\sum \text{Qty (Past 30 Days)} + \text{RequestedQty} \le \text{max\_per\_month}$$
- Enforced at domain level via `Product::assertCanPurchase`, throwing `PurchaseLimitExceededException`.

---

## Major Feature 4: Storefront Discovery, Autocomplete & Public Portal
### 4.1. Public Navigation & Scoping
- Sticky header featuring business logo, branch switcher pill, live cart drawer trigger.
### 4.2. Live Omnibar Autocomplete Search
- Direct integration with `/api/v1/store/{slug}/products?q=...` supporting keyboard navigation (`Ctrl+K`).
### 4.3. Category Hierarchy Navigation (`categories` table)
- Nested category navigation supporting `parent_id` hierarchy and display sorting (`sort_order`).
### 4.4. Wishlist & Bookmarks Engine (`product_bookmarks` table)
- Supports dual-mode persistence: browser `localStorage` for guests, SQLite database for authenticated users.

---

## Major Feature 5: Multi-Shop Isolated Cart & Guest Data Sync
### 5.1. Cart Partitioning (`cart_items` table)
- Carts partitioned by `shop_id`; independent subtotals, free shipping thresholds, and checkout flows per merchant.
### 5.2. Direct Buy Shortcut (`/buy/{id}`)
- Single-item instant checkout bypass that preserves the existing multi-item cart.
### 5.3. Guest-to-Customer Synchronization (`/api/sync-guest-data`)
- Automatically migrates local storage cart items and wishlist bookmarks to the database upon user registration or login.
### 5.4. Automated Cart Retention
- Background expiration of inactive carts based on `cart_retention_days` in `app_meta`.

---

## Major Feature 6: Multi-Step Checkout, 4-Day Reservation & Card-to-Card Payment
### 6.1. 4-Day (96-Hour) Stock Reservation Engine
- Locks items for 96 hours (`reservation_expires_at = now + 4 days`).
- Visual countdown clock displayed in checkout and order tracking views.
- Automatic background release via `ReleaseExpiredReservationsHandler`.
### 6.2. Bank Card Accounts Mockup (`shop_bank_cards` table)
- Displays active store bank accounts: 16-digit card number, cardholder name, bank name, Sheba IBAN.
- One-click clipboard copy with Persian-to-ASCII digit conversion.
### 6.3. Payment Slip Uploader
- Drag-and-drop file upload supporting JPEG, PNG, WEBP, and PDF up to 6MB.
- Rejects 0-byte or corrupted files.
- Captures 10-digit transaction tracking code and transfer timestamp.
### 6.4. Immutable Snapshot Preservation
- Captures `address_snapshot` and `customer_snapshot` as JSON strings on the order record, ensuring historical data integrity even if the user updates their profile later.

---

## Major Feature 7: Order Fulfillment, Iran Post 24-Digit Tracking & Invoicing
### 7.1. 5-Stage Milestone Stepper
- Visual progress bar across states: Submitted $\rightarrow$ Payment Verified $\rightarrow$ Packaging $\rightarrow$ Iran Post Dispatch $\rightarrow$ Delivered.
### 7.2. Iran Post 24-Digit Barcode Engine
- Strict numeric validation of 24-digit postal tracking codes.
- One-click copy and direct link to the Iranian National Post tracking system (`https://tracking.post.ir`).
### 7.3. Printable Documents
- Printable A4 tax invoice (`/orders/{id}/print`).
- Printable Iran Post shipping label (`/orders/{id}/shipping-label`).
- Bulk order printing batch processor (`/orders/bulk-print`).

---

## Major Feature 8: Merchant Order Operations & Split-View Verification Queue
### 8.1. Operational KPI Meters
- Today's GMV, Pending Slips Count, Low Stock Warnings Count, Expiring Reservations Count.
### 8.2. Side-by-Side Slip Verification Modal
- Left pane: Zoomable high-resolution payment slip image with PDF viewer support.
- Right pane: Expected order total, customer phone, transaction reference number, destination bank account selector.
- One-click "Approve & Settle" button.
- "Reject with Reason" modal prompting for rejection explanation.

---

## Major Feature 9: Double-Entry Financial Accounting & General Ledger
### 9.1. Accounting Vouchers Engine (`accounting_ledger` table)
- Strict mathematical equality enforcement: $\sum \text{Debit} = \sum \text{Credit}$.
- Throws `UnbalancedLedgerException` on any ledger imbalance.
### 9.2. Automated Order Settlement Entries
- Debit `cash_bank` for total funds received.
- Debit `discounts` for discount expenses.
- Credit `sales_revenue` for product sales.
- Credit `shipping_revenue` for postal delivery fees.
- Credit `tax_payable` for 9% VAT.
### 9.3. Automated Inventory Valuation (COGS)
- Debit `cogs` for cost of goods sold.
- Credit `inventory_asset` for inventory reduction.
### 9.4. Financial Reporting
- 4-Column Trial Balance (Assets, Liabilities, Equity, Revenue, Expenses).
- Quarterly VAT Tax Report for the Iranian National Tax Administration.
- Manual Journal Voucher Entry interface.

---

## Major Feature 10: Threaded Customer Support & VIP Escalation SLA
### 10.1. Threaded Support Ticketing (`tickets` & `ticket_messages` tables)
- Threaded conversation between customer and merchant staff.
- File attachment support up to 10MB (`ticket_attachments`).
- Order association via `ticket_order_relations`.
- Unread message tracking (`seen_by_customer`, `seen_by_admin`).
### 10.2. VIP Emergency Escalation SLA
- Restricted to merchants on the **Ultimate** tier (`has_ticket_escalation = 1`).
- Escalates critical tickets directly to the SuperAdmin high-priority queue.

---

## Major Feature 11: 4-Tier Subscription Plans & Tenant Quota Enforcement
### 11.1. 4-Tier Plan Hierarchy (`subscription_plans` table)
- **Free Tier**: 1 Business, 1 Branch, up to 50 Products, 1 Manager, Basic Ticketing.
- **Plus Tier**: 1 Business, 3 Branches, up to 500 Products, 3 Managers, Basic Accounting.
- **Pro Tier**: 1 Business, 10 Branches, Unlimited Products, 10 Managers, Advanced Double-Entry Accounting.
- **Ultimate Tier**: Unlimited Branches, Unlimited Products, Emergency SuperAdmin Ticket Escalation, Priority Queue.
### 11.2. Quota Usage Gauges (`/app/plans/my-plan`)
- Real-time meters tracking capacity usage for branches, products, and managers.
- Enforced at domain level via `SubscriptionLimitExceededException`.

---

## Major Feature 12: Platform Security, SuperAdmin Impersonation & Audit Trail
### 12.1. Session Security & Rate Limiting
- 30-day persistent session cookie (`BEFROOSH_SESS`) with `HttpOnly` and `SameSite=Lax`.
- Brute-force protection locking accounts for 5 minutes after 5 failed attempts (`login_attempts`).
- CSRF token validation on all state-mutating requests.
- Global rate limiter restricting requests to 120 per minute.
### 12.2. SuperAdmin Impersonation
- Allows SuperAdmins to log into any user account for debugging while preserving `impersonator_id`.
- One-click exit returning to the SuperAdmin session without re-authenticating.
### 12.3. System Incident & Abuse Reporting (`system_reports` table)
- Audit log for tracking platform compliance, abuse, and technical incidents across severity levels and resolution states.

---

This document represents the **100% complete, exhaustive English specification** of all pages, flows, actions, features, and sub-features in the Befroosh backend architecture.
