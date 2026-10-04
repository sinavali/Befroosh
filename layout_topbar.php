<?php
declare(strict_types=1);

/**
 * layout_topbar.php
 * Top header bar for the authenticated dashboard
 */

require_once __DIR__ . '/layout_icons.php';

function render_dashboard_topbar(string $title, array $user, int $shopId, ?array $currentShop): void
{
    $role = $user['role'] ?? 'customer';
    ?>
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:10px;">
            <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="منو"><?= icon('menu', 18) ?></button>
            <h1 class="topbar-title" style="font-size:1.1rem; margin:0;"><?= e($title) ?></h1>
        </div>

        <div class="topbar-actions" style="display:flex; align-items:center; gap:10px;">
            <?php if ($role === 'superadmin'): ?>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:0.75rem; color:var(--muted); font-weight:bold;">شعبه فعال:</span>
                    <select class="select" onchange="if(this.value) location.href='/shops/' + this.value + '/switch'" style="padding:4px 8px; font-size:0.8rem; height:32px; width:auto; min-width:140px;">
                        <?php foreach (all_active_shops() as $sh): ?>
                            <option value="<?= (int)$sh['id'] ?>" <?= (int)$shopId === (int)$sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif (in_array($role, ['shop_owner', 'shop_manager'], true) && $currentShop): ?>
                <span class="badge badge-blue" style="font-size:0.8rem; padding:4px 10px;">
                    <?= e($currentShop['name']) ?>
                </span>
            <?php endif; ?>

            <?php if (in_array($role, ['customer', 'shop_owner', 'shop_manager', 'admin', 'superadmin'], true)): ?>
                <a class="btn btn-primary btn-sm" href="/orders/create"><?= icon('plus', 13) ?> ثبت سفارش</a>
            <?php endif; ?>

            <form method="post" action="/logout" style="margin:0;">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm"><?= icon('logout', 14) ?> خروج</button>
            </form>
        </div>
    </header>
    <?php
}
