<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\MultiTenancy\User;
use App\Http\Response\ResponseFactory;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Security\RateLimiterAdapter;
use App\Infrastructure\Security\SessionAuthAdapter;
use PDO;

/**
 * Core API Controller handling system health and authentication endpoints.
 */
final class ApiController
{
    public function health(): void
    {
        $container = Container::getInstance();
        $pdo = $container->get(PDO::class);

        $dbOk = false;
        try {
            $dbOk = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable) {}

        ResponseFactory::json([
            'status' => $dbOk ? 'healthy' : 'unhealthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'database' => $dbOk ? 'connected' : 'disconnected',
        ], $dbOk ? 200 : 503);
    }

    public function me(): void
    {
        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();

        if ($user === null) {
            ResponseFactory::error('کاربر وارد سیستم نشده است.', 401, 'UNAUTHORIZED');
        }

        ResponseFactory::json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nickname' => $user->getNickname(),
            'role' => $user->getRole()->value,
            'role_label' => $user->getRole()->label(),
            'phone' => $user->getPhone(),
            'email' => $user->getEmail(),
            'shop_id' => $user->getShopId(),
            'active' => $user->isActive(),
            'is_impersonating' => $auth->isImpersonating(),
        ]);
    }

    public function login(): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($username === '' || $password === '') {
            ResponseFactory::error('نام کاربری و کلمه عبور الزامی است.', 422, 'VALIDATION_FAILED');
        }

        $rateLimiter = Container::getInstance()->get(RateLimiterAdapter::class);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $throttleKey = 'login_api:' . md5($username . '|' . $clientIp);

        if ($rateLimiter->tooManyAttempts($throttleKey, 5)) {
            ResponseFactory::error('تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً چند دقیقه دیگر مجدداً تلاش نمایید.', 429, 'RATE_LIMITED');
        }

        $container = Container::getInstance();
        $userRepo = $container->get(\App\Domain\Repositories\UserRepositoryInterface::class);
        $user = $userRepo->findByUsername($username);

        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            $rateLimiter->hit($throttleKey, 300);
            ResponseFactory::error('نام کاربری یا کلمه عبور اشتباه است.', 401, 'INVALID_CREDENTIALS');
        }

        if (!$user->isActive()) {
            ResponseFactory::error('حساب کاربری شما غیرفعال شده است.', 403, 'ACCOUNT_DISABLED');
        }

        $rateLimiter->clear($throttleKey);

        $auth = $container->get(SessionAuthAdapter::class);
        $auth->login($user);

        ResponseFactory::json([
            'message' => 'ورود با موفقیت انجام شد.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'nickname' => $user->getNickname(),
                'role' => $user->getRole()->value,
                'shop_id' => $user->getShopId(),
            ]
        ]);
    }

    public function logout(): void
    {
        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $auth->logout();

        ResponseFactory::json([
            'message' => 'خروج با موفقیت انجام شد.'
        ]);
    }
}
