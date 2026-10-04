<?php
declare(strict_types=1);

/**
 * helpers_shop.php
 * Shop resolution, multi-tenant switching, bank cards, and management checks
 */

function active_shop_id(): int
{
    if (isset($_SESSION['active_shop_id']) && (int)$_SESSION['active_shop_id'] > 0) {
        return (int)$_SESSION['active_shop_id'];
    }
    $user = current_user();
    return !empty($user['shop_id']) ? (int)$user['shop_id'] : 1;
}

function active_business_id(): int
{
    return active_shop_id();
}

function current_shop(): ?array
{
    $shopId = active_shop_id();
    return $shopId ? get_shop($shopId) : null;
}

function get_shop(int $id): ?array
{
    global $pdo;
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt->execute([$id]);
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$id] = $shop ?: null;
        return $cache[$id];
    } catch (Throwable $e) {
        return null;
    }
}

function all_active_shops(): array
{
    global $pdo;
    try {
        return $pdo->query("SELECT * FROM shops WHERE active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function get_shop_active_cards(int $shopId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shop_bank_cards WHERE shop_id = ? AND active = 1 ORDER BY id ASC");
        $stmt->execute([$shopId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function can_manage_shop(?array $user, int $shopId): bool
{
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'superadmin') {
        return true;
    }
    if (in_array($user['role'], ['shop_owner', 'shop_manager', 'admin'], true)) {
        return (int)($user['shop_id'] ?? 0) === $shopId;
    }
    return false;
}

function require_shop_role(array $roles, ?int $targetShopId = null): array
{
    $user = require_roles($roles);
    if ($user['role'] === 'superadmin') {
        return $user;
    }
    $shopId = $targetShopId ?? active_shop_id();
    if ((int)($user['shop_id'] ?? 0) !== $shopId) {
        error_page(403, 'عدم دسترسی به فروشگاه', 'شما به داده‌های این فروشگاه دسترسی ندارید.');
    }
    return $user;
}

function resolve_shop(string $slugOrId): ?array
{
    global $pdo;
    $decoded = urldecode($slugOrId);
    try {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE (slug = ? OR id = ?) AND active = 1");
        $stmt->execute([$decoded, is_numeric($decoded) ? (int)$decoded : 0]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolve_product(int $shopId, string $slugOrId): ?array
{
    global $pdo;
    $decoded = urldecode($slugOrId);
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND (slug = ? OR id = ?) AND deleted_at IS NULL");
        $stmt->execute([$shopId, $decoded, is_numeric($decoded) ? (int)$decoded : 0]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_current_management_shop(array $user): array
{
    if (in_array($user['role'], ['business_owner', 'shop_owner', 'branch_manager', 'shop_manager', 'manager'], true)) {
        $shopId = (int)($_SESSION['active_business_id'] ?? $user['shop_id'] ?? 1);
    } else {
        $shopId = active_shop_id();
    }
    $shop = get_shop($shopId);
    $data = $shop ?: ['id' => $shopId, 'name' => 'فروشگاه'];
    $res = [$shopId, $data];
    foreach ($data as $k => $v) {
        if (!is_int($k)) {
            $res[$k] = $v;
        }
    }
    return $res;
}

function get_user_businesses(int $userId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE owner_id = ? AND active = 1 ORDER BY id ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function set_active_business(int $businessId): void
{
    $_SESSION['active_shop_id'] = $businessId;
    $_SESSION['active_business_id'] = $businessId;
    unset($_SESSION['active_branch_id']);
}

function get_business_branches(int $businessId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM branches WHERE business_id = ? AND active = 1 ORDER BY is_main DESC, name ASC");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function get_branch(int $branchId): ?array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
        $stmt->execute([$branchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function active_branch_id(): ?int
{
    if (isset($_SESSION['active_branch_id']) && (int)$_SESSION['active_branch_id'] > 0) {
        return (int)$_SESSION['active_branch_id'];
    }
    return null;
}

function set_active_branch(?int $branchId): void
{
    if ($branchId && $branchId > 0) {
        $_SESSION['active_branch_id'] = $branchId;
    } else {
        unset($_SESSION['active_branch_id']);
    }
}

function get_user_assigned_branches(array $user, int $businessId): array
{
    global $pdo;
    if (in_array($user['role'], ['superadmin', 'admin', 'business_owner', 'shop_owner'], true)) {
        return get_business_branches($businessId);
    }
    try {
        $stmt = $pdo->prepare("
            SELECT b.* 
            FROM branch_user_assignments bua
            JOIN branches b ON b.id = bua.branch_id
            WHERE bua.user_id = ? AND bua.business_id = ? AND b.active = 1
            ORDER BY b.name ASC
        ");
        $stmt->execute([$user['id'], $businessId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function get_business_shipping_groups(int $businessId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shipping_groups WHERE business_id = ? ORDER BY id ASC");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function get_shipping_group(int $id): ?array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shipping_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

