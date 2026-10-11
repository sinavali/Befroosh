<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class PostalTrackingCodeInvalidException extends DomainException
{
    public function __construct(
        string $message = 'کد رهگیری پستی نامعتبر است.',
        string $errorCode = 'INVALID_POSTAL_TRACKING_CODE',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function invalidFormat(string $code): self
    {
        return new self(
            message: sprintf('کد رهگیری پستی وارد شده («%s») معتبر نمی‌باشد. کد پستی پیشتاز باید ۲۴ رقم معتبر باشد.', $code),
            errorCode: 'INVALID_POSTAL_TRACKING_CODE',
            context: ['tracking_code' => $code],
            httpStatusCode: 422
        );
    }
}
