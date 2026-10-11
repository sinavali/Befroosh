# Befroosh Design System & Figma Layering Convention

> **Standard**: Apple Design Language (iOS / macOS HIG Adapted for Web)  
> **Color Mode**: Pure Light Mode (Enterprise Operational Panels & Storefronts)  
> **Orientation**: Mobile-First (390px base viewport), 100% RTL (Persian / Vazirmatn)  
> **Calendar System**: Shamsi (Jalali) with interactive datepicker  
> **Target Figma File**: `yTnUXbha56jcKWycVHl4V4` ("Befroosh")  
> **Quality Gate**: Minimum Usability & Enterprise Clarity Score $\ge 95/100$  

---

## 1. Design Philosophy & Apple Light Mode Rules

1. **Light Mode Foundation (Clarity & Restraint)**:
   - **Canvas Background**: Apple System Gray 6 `#F5F5F7` (calm, neutral, non-distracting workspace).
   - **Content Surfaces (Cards & Tables)**: Pure White `#FFFFFF` with hairline structural borders (`1px solid #E5E5EA`).
   - **Recessed / Secondary Surfaces**: `#FBFBFD` (sidebar navigation, table headers) and `#F2F2F7` (input backings, segmented control tracks).
   - **Modals & Dialogs**: Pure White `#FFFFFF` with crisp hairline outline `#D1D1D6` and subtle ambient backdrop overlay (`rgba(0, 0, 0, 0.28)` with `backdrop-filter: blur(12px)`).
   - **Zero AI-Slop**: No dark glowing backgrounds, no neon gradients, no blurry colored glassmorphism, no fake floating badges.

2. **Hairline Precision & Subtle Elevation**:
   - Strictly `1px` borders using `#E5E5EA` (cards/dividers) and `#D1D1D6` (interactive controls/inputs).
   - Whisper shadows: `box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02)`.
   - Corner radius scale: `8px` (inputs/buttons), `10px` (cards/tables), `14px` (modals/sheets), `9999px` (pills/badges).

3. **Apple Official Light Palette**:
   - **Interactive Blue**: `#0071E3` (Hover: `#0077ED`, Active: `#0062C4`). Primary buttons, active tabs, postal tracking links.
   - **Success / Verified Emerald**: `#34C759` (Light chip: `#E8F8ED`, text: `#1D833E`, border: `rgba(52, 199, 89, 0.2)`).
   - **Warning / Pending Amber**: `#FF9500` (Light chip: `#FFF4E5`, text: `#A05E00`, border: `rgba(255, 149, 0, 0.2)`).
   - **Destructive / Error Red**: `#FF3B30` (Light chip: `#FFEBEA`, text: `#D70015`, border: `rgba(255, 59, 48, 0.2)`).
   - **Indigo / System Admin**: `#5856D6` (Light chip: `#F0EFFC`, text: `#4341B4`).
   - **Neutral Slate**: `#8E8E93` (Light chip: `#F2F2F7`, text: `#636366`).

---

## 2. Typography & RTL Standards

1. **Font Stack**:
   - Persian Primary: `Vazirmatn`, `-apple-system`, `BlinkMacSystemFont`, "SF Pro Text", "SF Pro Display", sans-serif.
   - Numerals: Strictly Persian tabular numerals (`font-variant-numeric: tabular-nums`) for currency (Toman), Jalali dates, order UUIDs, and 24-digit Iran Post barcodes.
2. **Hierarchy & Contrast**:
   - Primary Text (Ink): `#1D1D1F` (Apple near-black, high contrast).
   - Secondary Text: `#6E6E73` (Apple system gray 1 for labels, subheaders, timestamps).
   - Tertiary / Muted: `#86868B` (Placeholders, disabled controls).
3. **Scale**:
   - `Display`: 24px–28px, Bold (700), line-height 1.3.
   - `Title 1`: 18px–20px, Bold (700), line-height 1.4.
   - `Title 2 / Section`: 15px–16px, SemiBold (600), line-height 1.5.
   - `Body`: 13px–14px, Regular (400) / Medium (500), line-height 1.6.
   - `Caption / Subtext`: 11px–12px, Medium (500).

---

## 3. Figma Layering & Component Hierarchy

All Figma canvas frames and components adhere to this atomic hierarchy:

```text
Befroosh (Figma Root)
├── 00_Tokens/
│   ├── Colors/ (Light Surface #FFFFFF, Canvas #F5F5F7, Apple Blue #0071E3, Semantic Tints)
│   ├── Typography/ (Vazirmatn Display, Title, Body, Caption)
│   └── Elevation/ (Hairline 1px, Whisper Shadows, Corner Radii 8-14px)
├── 01_Atoms/
│   ├── Buttons/ (Primary Apple Blue, Secondary Outline, Destructive, Pill Stepper)
│   ├── Badges/ (Status Chips: Submitted, Paid, Shipped, Completed, Canceled)
│   ├── Inputs/ (Hairline Fields, Search with #F2F2F7 Backing, Shamsi Date Trigger)
│   └── SegmentedControls/ (Apple iOS/macOS 2-4 tab switcher with white thumb)
├── 02_Molecules/
│   ├── Cards/KPI-Metric-Card (Clean white card with subtle metric and icon)
│   ├── Tables/DataTable (Clean white table with #FBFBFD header, hairline dividers)
│   ├── Pickers/Shamsi-DatePicker (Jalali calendar grid with white popover)
│   └── Navigation/Sidebar-Item (Apple rounded nav item with blue active pill)
├── 03_Organisms/
│   ├── Layout/Dashboard-Shell (Sidebar + Topbar + Main Content)
│   ├── Queue/Order-Verification-Table (Multi-tenant filtered order ledger)
│   ├── Modal/Side-by-Side-Slip-Inspection (Split-view audit + double-entry preview)
│   └── Forms/Product-Catalog-Form (SKU, barcode, inventory thresholds)
└── 04_Screens/
    ├── Mobile-390px/
    ├── Tablet-768px/
    └── Desktop-1280px/
```

---

## 4. Shamsi (Jalali) Datepicker Specification

- Built on native `/assets/vendor/persian-datepicker.min.js`.
- Clean Apple Light popover: `#FFFFFF` card, `1px solid #D1D1D6` border, subtle shadow.
- Days rendered in Persian numerals with circular hover state and `#0071E3` selection pill.
- No Gregorian dates exposed in merchant or customer views.

---

## 5. Enterprise Usability Quality Gate ($\ge 95/100$)

| Dimension | Points | Benchmark |
|:---|:---:|:---|
| **1. Apple Light Precision** | 20 | Crisp `#FFFFFF` surfaces on `#F5F5F7`, `#1D1D1F` high contrast, zero dark/neon slop. |
| **2. Enterprise Usability** | 20 | Multi-tenant scoping, real bulk operations, full audit logs, double-entry ledger integration. |
| **3. Persian RTL & Shamsi** | 20 | Vazirmatn typography, tabular numerals, 100% RTL layout symmetry, Jalali datepicker. |
| **4. Friction Reduction** | 20 | In-place slip inspection modal, instant search filter, single-click copy for cards & barcodes. |
| **5. Performance & Accessibility** | 20 | WCAG AAA contrast, sub-millisecond local font rendering, 0 blocking external assets. |
