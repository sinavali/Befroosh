<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class PurchaseLimitExceededException extends DomainException
{
    public function __construct(
        string $message = 'سقف مجاز خرید کالا رعایت نشده است.',
        string $errorCode = 'PURCHASE_LIMIT_EXCEEDED',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function perOrder(
        int $productId,
        string $productTitle,
        float $maxPerOrder,
        float $requestedQty
    ): self {
        return new self(
            message: sprintf(
                'حداکثر سقف خرید کالای «%s» در هر سفارش %s عدد می‌باشد. مقدار درخواستی شما: %s.',
                $productTitle,
                $maxPerOrder,
                $requestedQty
            ),
            errorCode: 'PURCHASE_LIMIT_PER_ORDER_EXCEEDED',
            context: [
                'product_id' => $productId,
                'product_title' => $productTitle,
                'limit_type' => 'per_order',
                'max_allowed' => $maxPerOrder,
                'requested_quantity' => $requestedQty,
            ],
            httpStatusCode: 422
        );
    }

    public static function perMonth(
        int $productId,
        string $productTitle,
        float $maxPerMonth,
        float $alreadyBoughtIn30Days,
        float $requestedQty
    ): self {
        return new self(
            message: sprintf(
                'سقف خرید ۳۰ روزه کالای «%s» %s عدد است. شما در ۳۰ روز گذشته %s عدد خریداری کرده‌اید و نمی‌توانید %s عدد دیگر اضافه کنید.',
                $productTitle,
                $maxPerMonth,
                $alreadyBoughtIn30Days,
                $requestedQty
            ),
            errorCode: 'PURCHASE_LIMIT_PER_MONTH_EXCEEDED',
            context: [
                'product_id' => $productId,
                'product_title' => $productTitle,
                'limit_type' => 'per_month_30days',
                'max_allowed' => $maxPerMonth,
                'already_bought' => $alreadyBoughtIn30Days,
                'requested_quantity' => $requestedQty,
            ],
            httpStatusCode: 422
        );
    }
}
