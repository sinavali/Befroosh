<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class NegativeInventoryException extends DomainException
{
    public function __construct(
        string $message = 'موجودی انبار نمی‌تواند منفی باشد.',
        string $errorCode = 'NEGATIVE_INVENTORY_DISALLOWED',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forProduct(int $productId, float $currentStock, float $deduction): self
    {
        return new self(
            message: sprintf('موجودی کالا نمی‌تواند منفی شود. موجودی فعلی: %s، مقدار کسر: %s.', $currentStock, $deduction),
            errorCode: 'NEGATIVE_INVENTORY_DISALLOWED',
            context: ['product_id' => $productId, 'current_stock' => $currentStock, 'deduction' => $deduction],
            httpStatusCode: 422
        );
    }
}
