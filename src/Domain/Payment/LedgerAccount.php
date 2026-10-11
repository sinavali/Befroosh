<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/**
 * Chart of accounts for platform double-entry general ledger.
 */
enum LedgerAccount: string
{
    case CashBank = 'cash_bank';
    case InventoryAsset = 'inventory_asset';
    case SalesRevenue = 'sales_revenue';
    case ShippingRevenue = 'shipping_revenue';
    case CostOfGoodsSold = 'cogs';
    case TaxPayable = 'tax_payable';
    case Discounts = 'discounts';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::CashBank => 'نقد و بانک',
            self::InventoryAsset => 'موجودی کالای انبار',
            self::SalesRevenue => 'درآمد فروش کالا',
            self::ShippingRevenue => 'درآمد خدمات حمل و نقل',
            self::CostOfGoodsSold => 'بهای تمام‌شده کالای فروش‌رفته (COGS)',
            self::TaxPayable => 'مالیات بر ارزش افزوده پرداختنی',
            self::Discounts => 'تخفیفات اعطایی به مشتریان',
            self::Adjustment => 'تعدیلات و ضایعات انبار',
        };
    }

    /**
     * Account natural accounting balance classification.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::CashBank,
            self::InventoryAsset,
            self::CostOfGoodsSold,
            self::Discounts => 'debit',

            self::SalesRevenue,
            self::ShippingRevenue,
            self::TaxPayable => 'credit',

            self::Adjustment => 'variable',
        };
    }
}
