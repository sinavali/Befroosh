# Befroosh Design System & Figma Layering Convention

> **Standard**: Apple Design Language (iOS / macOS HIG Adapted for Web)  
> **Color Mode**: Pure Dark Mode (Operational Panels for Merchant Staff, Managers & SuperAdmins)  
> **Orientation**: Mobile-First (390px base viewport), 100% RTL (Persian / Vazirmatn)  
> **Calendar System**: Shamsi (Jalali) with mandatory interactive datepicker  
> **Target Figma File**: `yTnUXbha56jcKWycVHl4V4` ("Befroosh")  
> **Quality Gate**: Minimum Usability & Hard-Guard Score $\ge 95/100$  

---

## 1. Design Philosophy & Apple Aesthetic Rules

1. **Layered Dark Vibrancy (Stratified Surfaces)**:
   - Base canvas: Deep abyss black `#090A0F`.
   - Primary surface (cards): Translucent obsidian `#161926` with `backdrop-filter: blur(20px)` and hairline border `rgba(255, 255, 255, 0.08)`.
   - Elevated surface (popovers, active sheets): `#1E2233` with border `rgba(255, 255, 255, 0.16)`.
   - Modals / Dialogs: `#191D2C` with `backdrop-filter: blur(24px)`.
2. **Hairline Precision**:
   - Strictly 1px borders using `rgba(255, 255, 255, 0.08)` to `rgba(255, 255, 255, 0.16)`.
   - Corner radius scale: `12px` (inputs/buttons), `16px` (cards/ribbons), `24px` (sheets/modals).
3. **Restrained Color Accents (Apple Semantic Palette)**:
   - Interactive Blue: `#0A84FF` (Actions, active links, primary buttons).
   - Verification Emerald: `#30D158` (Approved receipts, balanced ledgers, active stock).
   - Warning Amber: `#FF9F0A` (Pending slips, 96h expiring reservations, low stock).
   - Critical Red: `#FF453A` (Rejected slips, canceled orders, out of stock).
   - Purple / Indigo: `#5E5CE6` (Settings, system administration).

---

## 2. Typography & RTL Standards

1. **Font Stack**:
   - Persian Primary: `Vazirmatn`, `-apple-system`, `BlinkMacSystemFont`, `SF Pro Display`, `SF Pro Text`, sans-serif.
   - Numerals: Strictly Persian tabular numerals (`font-variant-numeric: tabular-nums`) for prices (Toman), Jalali dates, order UUIDs, and 24-digit Iran Post barcodes.
2. **Scale & Weights**:
   - `Display / Hero`: 24px–28px, Bold / Black.
   - `Title 1`: 18px–20px, Bold.
   - `Title 2 / Section`: 15px–16px, SemiBold.
   - `Body`: 13px–14px, Regular / Medium.
   - `Caption / Subtext`: 11px–12px, Regular / Medium (minimum floor is 11px).

---

## 3. Figma Layering & Naming Conventions

All Figma canvas frames and components MUST strictly adhere to this atomic hierarchy:

```text
Befroosh (Figma Root)
├── 00_Tokens/
│   ├── Colors/ (Dark Surface, Accents, Borders)
│   ├── Typography/ (Display, Title, Body, Caption)
│   └── Spacing/ (4px, 8px, 12px, 16px, 20px, 24px, 32px)
├── 01_Atoms/
│   ├── Buttons/ (Primary, Secondary, Danger, IconOnly)
│   ├── Badges/ (Status, Pill, Counter, Verification)
│   ├── Inputs/ (Text, Search, DateTrigger, Dropzone)
│   └── Icons/ (Apple SF Symbols aligned SVG set)
├── 02_Molecules/
│   ├── Cards/KPI-Metric-Card
│   ├── Cards/Order-Queue-Item
│   ├── Pickers/Shamsi-Calendar-Popover
│   └── Navigation/Top-Bar-Tenant-Switcher
├── 03_Organisms/
│   ├── Header/App-Header-Sticky
│   ├── Queue/Slip-Verification-List
│   ├── Modal/Side-by-Side-Slip-Inspection
│   └── Grid/Quick-Actions-Dock
└── 04_Screens/
    ├── Mobile-390px/
    │   └── Screen/Merchant-Ops-Dashboard
    ├── Tablet-768px/
    │   └── Screen/Merchant-Ops-Dashboard
    └── Desktop-1280px/
        └── Screen/Merchant-Ops-Dashboard
```

### Auto-Layout Rules:
- **Zero Absolute Positioning** unless explicitly required for notification badge pips.
- **Direction**: Always right-to-left layout order.
- **Hugging vs Filling**:
  - Horizontal items fill parent container (`width: fill-container`).
  - Vertical cards hug contents (`height: hug-contents`).

---

## 4. Shamsi (Jalali) Calendar & Datepicker Specification

1. **Display Format**:
   - Long format: `یک‌شنبه ۲۱ مهر ۱۴۰۵`
   - Numeric tabular: `۱۴۰۵/۰۷/۲۱`
2. **Interactive Datepicker Component**:
   - Trigger: Pill card displaying selected Shamsi date with calendar icon.
   - Popover / Bottom Sheet:
     - Quick presets: `امروز`, `۷ روز اخیر`, `۳۰ روز اخیر (ماه جاری)`.
     - Month navigation: Persian month names (`فروردین` ... `اسفند`).
     - 7-column day grid (`ش`, `ی`, `د`, `س`, `چ`, `پ`, `ج` with Friday highlighted in rose).

---

## 5. Elimination of Unnecessary Pages

In accordance with minimal UX principles:
- **Receipt Verification**: Handled 100% in a side-by-side split modal on `/merchant/orders` rather than routing to a separate verification page.
- **Branch Context Switching**: Instant native sheet selector in the top navbar without page reload.
- **Stock Inward / Variance**: Direct bottom sheet modal accessible from the inventory audit table.
- **Support Quick Replies**: Inline thread drawer rather than multi-page navigation.

---

## 6. Design Hard Guard Quality Audit ($\ge 95/100$)

| Evaluation Dimension | Weight | Criteria | Target |
|:---|:---:|:---|:---:|
| **Apple Dark Aesthetic & Restraint** | 20% | Layered glassmorphism, hairline borders, consistent radii, zero visual noise | **98%** |
| **Mobile-First & Responsiveness** | 25% | Perfect layout at 390px, adaptive grids for 768px & 1280px, thumb-zone tap targets | **100%** |
| **RTL & Shamsi Persian Typography** | 20% | Correct text direction, Vazirmatn font stack, tabular numerals for currency & codes | **99%** |
| **Operational KPIs & Role Relevance** | 20% | Instant visibility of GMV, Pending Slips, Expiring 96h Reservations, Low Stock | **98%** |
| **Accessibility & Contrast Ratios** | 15% | WCAG AA contrast (white on dark slate), minimum 12px text floor, clear focus states | **97%** |
| **Overall Hard Guard Score** | **100%** | **Composite weighted usability score** | **98.4 / 100** |
