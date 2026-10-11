<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class InvalidSkuException extends DomainException
{
    public function __construct(
        string $message = 'فرمت شناسه کالا (SKU) نامعتبر است.',
        string $errorCode = 'INVALID_SKU_FORMAT',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }
}
