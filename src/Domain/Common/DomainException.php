<?php

declare(strict_types=1);

namespace App\Domain\Common;

use Exception;
use Throwable;

/**
 * Base domain exception representing business rule and invariant violations.
 */
abstract class DomainException extends Exception
{
    /**
     * @param string $message Localized human-readable error explanation.
     * @param string $errorCode Machine-readable uppercase identifier for API responses.
     * @param array<string, mixed> $context Key-value metadata describing the invariant breach.
     * @param int $httpStatusCode Recommended HTTP status code for API layer mapping.
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        protected readonly string $errorCode = 'DOMAIN_ERROR',
        protected readonly array $context = [],
        protected readonly int $httpStatusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getUserMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Serializes error for structured dual-mode JSON envelopes.
     *
     * @return array{code: string, message: string, details: array<string, mixed>}
     */
    public function toErrorPayload(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->context,
        ];
    }
}
