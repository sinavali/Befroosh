<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class PaymentAlreadyVerifiedException extends DomainException
{
    public function __construct(
        string $message = 'پرداخت قبلاً تایید یا بررسی شده است.',
        string $errorCode = 'PAYMENT_ALREADY_VERIFIED',
        array $context = [],
        int $httpStatusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forOrder(int $orderId, string $currentReceiptStatus): self
    {
        return new self(
            message: sprintf('فیش پرداختی این سفارش قبلاً بررسی شده و در وضعیت «%s» قرار دارد.', $currentReceiptStatus),
            errorCode: 'PAYMENT_ALREADY_VERIFIED',
            context: ['order_id' => $orderId, 'current_status' => $currentReceiptStatus],
            httpStatusCode: 400
        );
    }
}
