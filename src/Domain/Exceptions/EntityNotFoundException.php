<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

class EntityNotFoundException extends DomainException
{
    public function __construct(
        string $message = 'موجودیت مورد نظر یافت نشد.',
        string $errorCode = 'ENTITY_NOT_FOUND',
        array $context = [],
        int $httpStatusCode = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forEntity(string $entityName, string|int $identifier): self
    {
        return new self(
            message: sprintf('موجودیت «%s» با شناسه «%s» یافت نشد.', $entityName, (string)$identifier),
            errorCode: 'ENTITY_NOT_FOUND',
            context: [
                'entity_name' => $entityName,
                'identifier' => $identifier,
            ],
            httpStatusCode: 404
        );
    }

    public static function shop(int|string $identifier): self
    {
        return new self(
            message: sprintf('فروشگاه با شناسه یا آدرس «%s» یافت نشد.', (string)$identifier),
            errorCode: 'SHOP_NOT_FOUND',
            context: ['shop_identifier' => $identifier],
            httpStatusCode: 404
        );
    }

    public static function product(int $productId): self
    {
        return new self(
            message: sprintf('کالای مورد نظر با شناسه %d یافت نشد یا حذف شده است.', $productId),
            errorCode: 'PRODUCT_NOT_FOUND',
            context: ['product_id' => $productId],
            httpStatusCode: 404
        );
    }

    public static function order(int|string $identifier): self
    {
        return new self(
            message: sprintf('سفارش مورد نظر با شناسه «%s» یافت نشد.', (string)$identifier),
            errorCode: 'ORDER_NOT_FOUND',
            context: ['order_identifier' => $identifier],
            httpStatusCode: 404
        );
    }

    public static function branch(int|string $identifier): self
    {
        return new self(
            message: sprintf('شعبه مورد نظر با شناسه «%s» یافت نشد.', (string)$identifier),
            errorCode: 'BRANCH_NOT_FOUND',
            context: ['branch_identifier' => $identifier],
            httpStatusCode: 404
        );
    }

    public static function user(int|string $identifier): self
    {
        return new self(
            message: sprintf('کاربر با شناسه «%s» در سیستم موجود نیست.', (string)$identifier),
            errorCode: 'USER_NOT_FOUND',
            context: ['user_identifier' => $identifier],
            httpStatusCode: 404
        );
    }
}
