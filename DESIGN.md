# Befroosh Design System: Neutral Precision & Financial Trust

A comprehensive design specification for **Befroosh** — an enterprise-grade, multi-tenant Persian e-commerce and merchant operations platform. Designed for high visual clarity, tactile precision, transparent financial accounting, and effortless RTL (Right-to-Left) usability.

---

## 1. Design Philosophy & Aesthetic Foundation

- **Atmosphere**: Clean, neutral, high-contrast, quiet luxury, and engineered precision. Avoid noisy gradients, floating neon elements, and ungrounded cards.
- **RTL-First Flow**: Optimized for Persian typography, natural right-to-left optical reading hierarchy, mirrored form layouts, and deliberate spacing.
- **Materiality & Depth**: Crisp paper-like canvas (`#FFFFFF`) with ultra-subtle stratified paneling (`#F8FAFC`, `#F1F5F9`). Hairline structural borders (`1px` `#E2E8F0`) provide defined boundaries without visual heaviness. Whisper-soft ambient shadows (`0 1px 3px rgba(15, 23, 42, 0.06)`) ground floating actions and modals.
- **Financial Clarity**: Double-entry accounting views, Card-to-Card receipts, and 4-day stock reservation timers demand unambiguous numerical readouts and distinct semantic status chips.

---

## 2. Color Palette & Semantic Roles

### Neutral Foundation
- **Canvas White (`#FFFFFF`)**: Pure background for product imagery, documents, and cards.
- **Surface Muted (`#F8FAFC`)**: Secondary container background, sidebars, table headers, and page canvases.
- **Surface Well (`#F1F5F9`)**: Recessed input backgrounds, code wrappers, and filter chip backings.
- **Hairline Border (`#E2E8F0` / `#CBD5E1`)**: `1px` crisp structural dividers and card perimeter outlines.
- **Obsidian Ink (`#0F172A`)**: Primary typography, heavy headlines, and high-contrast primary buttons.
- **Slate Subtext (`#475569` / `#64748B`)**: Secondary metadata, descriptions, table labels, and timestamps.
- **Muted Placeholder (`#94A3B8`)**: Input placeholders, disabled icons, and subtle dividers.

### Functional & Semantic Accents
- **Emerald Forest (`#059669` / `#10B981`)**: Approved payments, confirmed orders, healthy inventory badges, balanced ledger debits/credits.
- **Warm Amber (`#D97706` / `#F59E0B`)**: 4-day stock reservation countdown, pending receipt audit queue, low-stock alerts.
- **Royal Cobalt (`#2563EB` / `#3B82F6`)**: Iran Post 24-digit barcode tracking, branch switcher chips, active navigation states.
- **Crimson Rose (`#DC2626` / `#EF4444`)**: Rejected payment receipts, out-of-stock indicators, destructive modal confirmations.

---

## 3. Typographic Hierarchy & Numeral Readouts

- **Typeface Families**:
  - Primary Persian UI: **Vazirmatn** / **Shabnam** (RTL, clear geometric counters, high legibility across small sizes).
  - Telemetry & Barcodes: **JetBrains Mono** / **Shabnam FD** (tabular numbers for Toman currency amounts, Iranian postal tracking numbers, and double-entry voucher IDs).
- **Scale & Weights**:
  - **Display / Hero**: `32px` / `40px`, Bold (Weight 700), `-0.02em` letter spacing.
  - **Page Headline**: `24px` / `32px`, Bold (Weight 700).
  - **Section / Card Header**: `18px` / `26px`, Semi-bold (Weight 600).
  - **Body Text**: `14px` / `22px`, Regular (Weight 400).
  - **Secondary / Meta**: `13px` / `20px`, Medium (Weight 500).
  - **Badge / Footnote**: `11px` / `16px`, Semi-bold (Weight 600), Uppercase / Tabular.

---

## 4. Reusable Component Archetypes

### Header & Branch Context Switcher
- Persistent sticky header with clean logo, multi-branch dropdown pill (`شعبه مرکزی تهران`, `شعبه تجریش`), real-time search with instant keyboard shortcut (`/` or `Ctrl+K`), and shopping bag drawer trigger with dynamic quantity badge.

### 4-Day Reservation Banner & Countdown Strip
- Distinct notification banner on checkout and order tracking views:
  - Text: `رزرو اختصاصی کالا به مدت ۹۶ ساعت (۴ روز)`
  - Progress bar and countdown timer indicating exact hours and minutes remaining before automatic stock replenishment.

### Card-to-Card Payment & Slip Verification Zone
- Bank Card Mockup displaying Iranian bank card number with formatted 4-digit groups (`۶۰۳۷-۹۹۷۵-...`), copy button, bank logo, and Sheba IBAN.
- High-fidelity drag-and-drop receipt slip uploader with instant thumbnail preview, transaction tracking code field, and real-time validation.

### Iran Post 24-Digit Tracking Stepper
- 5-step milestone progress component:
  1. `ثبت و تأیید سفارش` (Order Submitted & Confirmed)
  2. `تأیید فیش بانکی` (Payment Verified)
  3. `بسته‌بندی و صدور بارنامه` (Packaging & Dispatch)
  4. `تحویل به پست پیشتاز` (Handed to Iran Post)
  5. `تحویل به گیرنده` (Delivered)
- Includes one-click copyable 24-digit barcode and direct link to official post tracking portal (`tracking.post.ir`).

### Merchant Slip Verification Drawer / Split-Screen
- Order review queue with instant side-by-side modal:
  - Left pane: Zoomable high-resolution scan of bank transfer receipt with optical check indicators.
  - Right pane: Customer details, matching expected total in Toman, bank account selector, and two explicit action buttons:
    - `تأیید و نهایی‌سازی سفارش` (Approve & Settle Stock)
    - `رد فیش با ثبت دلیل` (Reject with reason prompt)

### General Ledger & Double-Entry Accounting Matrix
- Strict balanced accounting journal:
  - Tabular layout with Voucher No, Jalali Date, Sub-ledger Account, Debit (بدهکار), Credit (بستانکار), and Balance status.
  - Summary row validating mathematical equality: `مجموع بدهکار = مجموع بستانکار`.

---

## 5. Screen Archetypes to Generate

1. **Storefront & Branch Catalog (`/b/{slug}`)**: Customer browsing experience with branch switcher, category tabs, product card grid, and quick-add to bag.
2. **Multi-Step Checkout & Card-to-Card Upload (`/checkout`)**: Complete checkout flow with address selection, reservation countdown banner, bank card payment details, and receipt upload dropzone.
3. **Customer Order Tracking & Post Stepper (`/orders/{id}`)**: Visual tracking timeline, 24-digit Iran Post progress, receipt status, and support ticket link.
4. **Merchant Order Verification Queue (`/merchant/orders`)**: Merchant operations workspace with metric cards, order table, and Card-to-Card slip inspection modal.
5. **Double-Entry General Ledger & Trial Balance (`/merchant/accounting`)**: Financial ledger view with journal vouchers, balance verification, and account hierarchy.
