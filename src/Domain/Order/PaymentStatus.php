<?php

declare(strict_types=1);

namespace App\Domain\Order;

/**
 * Payment status lifecycle enum.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PendingVerification = 'pending_verification';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'پرداخت‌نشده',
            self::PendingVerification => 'در انتظار تایید فیش',
            self::Paid => 'پرداخت‌شده',
            self::Rejected => 'ردشده',
        };
    }
}
