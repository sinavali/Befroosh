<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\MultiTenancy\User;
use App\Domain\Repositories\UserRepositoryInterface;

/**
 * Session-based authentication adapter providing 30-day sticky sessions,
 * login verification, and secure impersonation.
 */
final class SessionAuthAdapter
{
    private const SESSION_KEY = 'user_id';
    private const IMPERSONATOR_KEY = 'impersonator_id';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = 30 * 24 * 60 * 60; // 30 days
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_name('BEFROOSH_SESS');
            session_start();
        }
    }

    public function currentUser(): ?User
    {
        $this->start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $userId = (int)$_SESSION[self::SESSION_KEY];
        return $this->userRepository->findById($userId);
    }

    public function login(User $user): void
    {
        $this->start();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user->getId();
    }

    public function logout(): void
    {
        $this->start();
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION[self::IMPERSONATOR_KEY]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function impersonate(int $targetUserId): void
    {
        $this->start();
        if (!isset($_SESSION[self::IMPERSONATOR_KEY]) && isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::IMPERSONATOR_KEY] = $_SESSION[self::SESSION_KEY];
        }
        $_SESSION[self::SESSION_KEY] = $targetUserId;
    }

    public function stopImpersonating(): void
    {
        $this->start();
        if (isset($_SESSION[self::IMPERSONATOR_KEY])) {
            $_SESSION[self::SESSION_KEY] = $_SESSION[self::IMPERSONATOR_KEY];
            unset($_SESSION[self::IMPERSONATOR_KEY]);
        }
    }

    public function isImpersonating(): bool
    {
        $this->start();
        return !empty($_SESSION[self::IMPERSONATOR_KEY]);
    }
}
