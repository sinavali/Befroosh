<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

/**
 * Backed Enum representing platform roles with hierarchical permission aliasing.
 */
enum Role: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case BusinessOwner = 'business_owner';
    case ShopOwner = 'shop_owner';
    case BranchManager = 'branch_manager';
    case ShopManager = 'shop_manager';
    case Manager = 'manager';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر ارشد سامانه',
            self::Admin => 'مدیر سامانه',
            self::BusinessOwner, self::ShopOwner => 'مالک کسب‌وکار',
            self::BranchManager, self::ShopManager => 'مدیر شعبه',
            self::Manager => 'مسئول عملیات / انبار',
            self::Customer => 'مشتری',
        };
    }

    /**
     * Evaluates hierarchical role aliasing against requested role(s).
     */
    public function satisfies(Role ...$requiredRoles): bool
    {
        if (empty($requiredRoles)) {
            return true;
        }

        foreach ($requiredRoles as $requiredRole) {
            if ($this === $requiredRole) {
                return true;
            }

            if ($this === self::SuperAdmin) {
                return true;
            }

            if ($this === self::Admin && $requiredRole !== self::SuperAdmin) {
                return true;
            }

            // Owner level aliasing
            if (in_array($this, [self::BusinessOwner, self::ShopOwner], true)) {
                if (in_array($requiredRole, [
                    self::BusinessOwner,
                    self::ShopOwner,
                    self::BranchManager,
                    self::ShopManager,
                    self::Manager,
                    self::Customer,
                ], true)) {
                    return true;
                }
            }

            // Branch manager level aliasing
            if (in_array($this, [self::BranchManager, self::ShopManager], true)) {
                if (in_array($requiredRole, [
                    self::BranchManager,
                    self::ShopManager,
                    self::Manager,
                    self::Customer,
                ], true)) {
                    return true;
                }
            }

            // Manager level aliasing
            if ($this === self::Manager) {
                if (in_array($requiredRole, [self::Manager, self::Customer], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isPlatformStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function isShopStaff(): bool
    {
        return !in_array($this, [self::Customer], true);
    }
}
