<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class UnbalancedLedgerException extends DomainException
{
    public function __construct(
        string $message = 'تراز سند حسابداری نامتعادل است.',
        string $errorCode = 'UNBALANCED_LEDGER_ENTRIES',
        array $context = [],
        int $httpStatusCode = 500,
        ?Throwable $previous = null,
        public readonly float $totalDebit = 0.0,
        public readonly float $totalCredit = 0.0,
        public readonly float $difference = 0.0
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function fromSums(float $totalDebit, float $totalCredit, float $difference): self
    {
        return new self(
            message: sprintf(
                'تراز سند حسابداری نامتعادل است. مجموع بدهکار: %s، مجموع بستانکار: %s، اختلاف: %s. در حسابداری دوطرفه اختلاف باید دقیقا ۰.۰۰ باشد.',
                number_format($totalDebit, 2),
                number_format($totalCredit, 2),
                number_format($difference, 2)
            ),
            errorCode: 'UNBALANCED_LEDGER_ENTRIES',
            context: [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'difference' => $difference,
            ],
            httpStatusCode: 500,
            previous: null,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            difference: $difference
        );
    }
}
