<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

/**
 * CSRF Protection Adapter with cryptographic token generation and timing-safe comparison.
 */
final class CsrfAdapter
{
    private const TOKEN_KEY = '_csrf_token';

    public function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[self::TOKEN_KEY];
    }

    public function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($token === null || empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::TOKEN_KEY], $token);
    }
}
