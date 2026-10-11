<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

final class UnauthorizedDomainActionException extends DomainException
{
    public function __construct(
        string $message = 'دسترسی به این عملیات غیرمجاز است.',
        string $errorCode = 'UNAUTHORIZED_DOMAIN_ACTION',
        array $context = [],
        int $httpStatusCode = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forAction(
        int $actorId,
        string $actorRole,
        string $action,
        string $resourceDescription
    ): self {
        return new self(
            message: sprintf(
                'دسترسی غیرمجاز: کاربر با شناسه %d و نقش «%s» اجازه انجام عملیات «%s» بر روی %s را ندارد.',
                $actorId,
                $actorRole,
                $action,
                $resourceDescription
            ),
            errorCode: 'UNAUTHORIZED_DOMAIN_ACTION',
            context: [
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'action' => $action,
                'resource' => $resourceDescription,
            ],
            httpStatusCode: 403
        );
    }
}
