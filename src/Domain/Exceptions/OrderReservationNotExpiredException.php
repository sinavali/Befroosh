<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class OrderReservationNotExpiredException extends DomainException
{
    public function __construct(
        string $message = 'مهلت رزرو سفارش هنوز به پایان نرسیده است.',
        string $errorCode = 'RESERVATION_NOT_EXPIRED',
        array $context = [],
        int $httpStatusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }
}
