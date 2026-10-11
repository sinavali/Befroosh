<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class InvalidOrderStateTransitionException extends DomainException
{
    public function __construct(
        string $message = 'تغییر وضعیت سفارش نامعتبر است.',
        string $errorCode = 'INVALID_ORDER_STATE_TRANSITION',
        array $context = [],
        int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function fromTransition(
        int|string $orderId,
        string $orderUuid,
        string $currentStatus,
        string $targetStatus
    ): self {
        return new self(
            message: sprintf(
                'تغییر وضعیت نامعتبر برای سفارش %s: امکان انتقال از وضعیت «%s» به وضعیت «%s» وجود ندارد.',
                $orderUuid,
                $currentStatus,
                $targetStatus
            ),
            errorCode: 'INVALID_ORDER_STATE_TRANSITION',
            context: [
                'order_id' => $orderId,
                'order_uuid' => $orderUuid,
                'current_status' => $currentStatus,
                'target_status' => $targetStatus,
            ],
            httpStatusCode: 422
        );
    }
}
