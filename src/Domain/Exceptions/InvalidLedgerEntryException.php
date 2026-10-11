<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class InvalidLedgerEntryException extends DomainException
{
    public function __construct(
        string $message = 'سند حسابداری نامعتبر است.',
        string $errorCode = 'INVALID_LEDGER_ENTRY',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }
}
