<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class ReservationExpiredException extends DomainException
{
    public function __construct(
        string $message = 'مهلت رزرو کالا منقضی شده است.',
        string $errorCode = 'RESERVATION_EXPIRED',
        array $context = [],
        int $httpStatusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forOrder(int $orderId, string $orderUuid, string $expiredAt): self
    {
        return new self(
            message: sprintf('مهلت ۴ روزه رزرو سفارش %s در تاریخ %s به پایان رسیده و سفارش لغو گردیده است.', $orderUuid, $expiredAt),
            errorCode: 'RESERVATION_EXPIRED',
            context: [
                'order_id' => $orderId,
                'order_uuid' => $orderUuid,
                'expired_at' => $expiredAt,
            ],
            httpStatusCode: 400
        );
    }
}
