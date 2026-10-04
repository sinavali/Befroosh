<?php
declare(strict_types=1);

/**
 * helpers_auth.php
 * Authentication state, role checking, permissions, and request throttling
 */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        return null;
    }

    $_SESSION['last_activity'] = time();

    if (isset($_SESSION['impersonated_admin_id']) && $user['role'] === 'superadmin') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' AND active = 1 AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['impersonated_admin_id']]);
        $imp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($imp) {
            $imp['role'] = 'admin';
            $imp['is_impersonated'] = true;
            $imp['real_user_id'] = $user['id'];
            return $imp;
        }
    }

    return $user;
}

function real_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function require_real_superadmin(): array
{
    $user = real_user();

    if (!$user) {
        redirect('/login');
    }

    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        error_page(403, 'دسترسی غیرمجاز', 'فقط مدیر ارشد به این بخش دسترسی دارد.');
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        error_page(403, 'دسترسی غیرمجاز', 'شما اجازه دسترسی به این صفحه را ندارید.');
    }

    return $user;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_superadmin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return ($user['role'] ?? '') === 'superadmin';
}

function is_platform_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return in_array($user['role'] ?? '', ['superadmin', 'admin'], true);
}

function is_shop_owner(?array $user = null, ?int $shopId = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    if ($user['role'] === 'superadmin') return true;
    if ($user['role'] !== 'shop_owner') return false;
    if ($shopId === null) return true;
    return (int)($user['shop_id'] ?? 0) === $shopId;
}

function is_shop_manager(?array $user = null, ?int $shopId = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    if (in_array($user['role'], ['superadmin', 'admin', 'shop_owner'], true)) return true;
    if ($user['role'] !== 'shop_manager') return false;
    if ($shopId === null) return true;
    return (int)($user['shop_id'] ?? 0) === $shopId;
}

function can_impersonate(?array $user = null): bool
{
    $user = $user ?? current_user();
    return in_array($user['role'] ?? '', ['superadmin', 'admin'], true);
}

function check_rate_limit(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
{
    global $pdo;
    $now = time();

    if (mt_rand(1, 100) === 1) {
        try {
            $pdo->prepare("DELETE FROM rate_limits WHERE reset_at < ?")->execute([$now]);
        } catch (Throwable $e) {}
    }

    try {
        $stmt = $pdo->prepare("SELECT hits, reset_at FROM rate_limits WHERE key = ?");
        $stmt->execute([$key]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            if ((int)$record['reset_at'] < $now) {
                $pdo->prepare("UPDATE rate_limits SET hits = 1, reset_at = ? WHERE key = ?")
                    ->execute([$now + $windowSeconds, $key]);
                return true;
            }

            if ((int)$record['hits'] >= $maxAttempts) {
                return false;
            }

            $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE key = ?")->execute([$key]);
            return true;
        }

        $pdo->prepare("INSERT INTO rate_limits (key, hits, reset_at) VALUES (?, 1, ?)")
            ->execute([$key, $now + $windowSeconds]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function throttle_request(string $action = 'general', int $maxAttempts = 120, int $windowSeconds = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = "throttle:{$action}:{$ip}";
    if (!check_rate_limit($key, $maxAttempts, $windowSeconds)) {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>درخواست‌های بیش از حد</title><style>body{font-family:Tahoma,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:480px;text-align:center;color:#1f2937;}h1{color:#dc2626;font-size:1.4rem;margin-bottom:1rem;}p{line-height:1.6;color:#4b5563;}</style></head><body><div class="card"><h1>تعداد درخواست‌های بیش از حد مجاز (۴۲۹)</h1><p>لطفاً چند لحظه صبر کرده و مجدداً تلاش نمایید.</p></div></body></html>';
        exit;
    }
}
