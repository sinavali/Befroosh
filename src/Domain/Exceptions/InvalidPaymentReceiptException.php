<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class InvalidPaymentReceiptException extends DomainException
{
    public function __construct(
        string $message = 'فایل یا اطلاعات فیش پرداختی نامعتبر است.',
        string $errorCode = 'INVALID_PAYMENT_RECEIPT',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function invalidFile(string $reason, array $meta = []): self
    {
        return new self(
            message: 'فایل فیش واریزی نامعتبر است: ' . $reason,
            errorCode: 'INVALID_PAYMENT_RECEIPT',
            context: $meta,
            httpStatusCode: 422
        );
    }
}
