<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class InsufficientStockException extends DomainException
{
    public function __construct(
        string $message = 'موجودی کالای درخواستی کافی نیست.',
        string $errorCode = 'INSUFFICIENT_STOCK',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forProduct(
        int $productId,
        string $productTitle,
        float $availableStock,
        float $requestedQuantity
    ): self {
        return new self(
            message: sprintf(
                'موجودی کالای «%s» کافی نیست. موجودی در دسترس: %s، مقدار درخواستی: %s.',
                $productTitle,
                $availableStock,
                $requestedQuantity
            ),
            errorCode: 'INSUFFICIENT_STOCK',
            context: [
                'product_id' => $productId,
                'product_title' => $productTitle,
                'available_stock' => $availableStock,
                'requested_quantity' => $requestedQuantity,
            ],
            httpStatusCode: 422
        );
    }
}
