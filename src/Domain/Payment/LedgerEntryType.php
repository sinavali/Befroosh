<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/**
 * Journal entry operational classification types.
 */
enum LedgerEntryType: string
{
    case SaleRevenue = 'sale_revenue';
    case Cogs = 'cogs';
    case ShippingRevenue = 'shipping_revenue';
    case TaxPayable = 'tax_payable';
    case PaymentReceived = 'payment_received';
    case DiscountExpense = 'discount_expense';
    case ManualEntry = 'manual_entry';
    case Adjustment = 'adjustment';
}
