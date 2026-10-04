<?php
declare(strict_types=1);

/**
 * routes_shop_messages.php
 * Shop Contact Messages Inbox & Management for Shop Owners & Managers
 */

// 1. Inbox list
route('GET', '/shop/messages(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    $shop = get_current_management_shop($user);
    $shopId = (int)$shop['id'];

    $stmt = $pdo->prepare("
        SELECT * FROM shop_contact_messages 
        WHERE shop_id = ? 
        ORDER BY is_read ASC, id DESC
    ");
    $stmt->execute([$shopId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadCount = 0;
    foreach ($messages as $m) {
        if (!$m['is_read']) $unreadCount++;
    }

    layout_start('پیام‌های دریافتی مشتریان', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('send', 18) ?></div>
            <div>
                <h1>پیام‌های فرم تماس فروشگاه</h1>
                <div class="page-sub">پیام‌های ارسال‌شده از طریق فرم «ارتباط با ما» در ویترین فروشگاه <?= e($shop['name']) ?></div>
            </div>
        </div>
        <div class="flex gap-2">
            <span class="badge <?= $unreadCount > 0 ? 'badge-amber' : 'badge-emerald' ?>" style="font-size:0.85rem; padding:6px 12px;">
                <?= $unreadCount ?> پیام جدید خوانده‌نشده
            </span>
            <a href="/shop/<?= e($shop['slug']) ?>/contact" target="_blank" class="btn btn-outline btn-sm">
                <?= icon('eye', 14) ?> مشاهده فرم در سایت
            </a>
        </div>
    </div>

    <?php if (empty($messages)): ?>
        <div class="card">
            <div class="card-body">
                <?= empty_state('هیچ پیامی دریافت نشده است', 'تاکنون کاربری از طریق فرم تماس با فروشگاه شما پیامی ارسال نکرده است.', 'send') ?>
            </div>
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:14px;">
            <?php foreach ($messages as $msg): ?>
                <div class="card" style="border-right: 4px solid <?= $msg['is_read'] ? '#cbd5e1' : '#2563eb' ?>; margin-bottom:0;">
                    <div class="card-header" style="background: <?= $msg['is_read'] ? '#f8fafc' : '#ffffff' ?>;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="badge <?= $msg['is_read'] ? 'badge-gray' : 'badge-blue' ?>">
                                <?= $msg['is_read'] ? 'خوانده‌شده' : 'جدید' ?>
                            </span>
                            <strong style="color:#0f172a; font-size:0.95rem;"><?= e($msg['name']) ?></strong>
                            <?php if (!empty($msg['phone'])): ?>
                                <span style="font-size:0.85rem; color:#64748b; direction:ltr; display:inline-block;">
                                    <?= icon('phone', 13) ?> <?= e($msg['phone']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                            <?= icon('clock', 13) ?>
                            <span><?= jdate_time($msg['created_at']) ?></span>
                        </div>
                    </div>

                    <div class="card-body" style="font-size:0.92rem; color:#334155; line-height:1.8; white-space:pre-wrap; background:#fff;">
                        <?= nl2br(e($msg['message'])) ?>
                    </div>

                    <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <?php if (!empty($msg['phone'])): ?>
                                <a href="tel:<?= e($msg['phone']) ?>" class="btn btn-outline btn-sm" style="color:#2563eb;">
                                    <?= icon('phone', 13) ?> تماس با فرستنده
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if (!$msg['is_read']): ?>
                                <form method="post" action="/shop/messages/mark-read" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                    <button class="btn btn-outline btn-sm" style="color:#059669; border-color:#a7f3d0;">
                                        <?= icon('check', 13) ?> علامت‌گذاری به عنوان خوانده‌شده
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/shop/messages/delete" style="margin:0;" data-confirm="آیا از حذف این پیام اطمینان دارید؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                <button class="btn btn-danger btn-sm">
                                    <?= icon('trash', 13) ?> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    layout_end();
});

// 2. Mark as read
route('POST', '/shop/messages/mark-read', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $shop = get_current_management_shop($user);

    $msgId = (int)($_POST['message_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE shop_contact_messages SET is_read = 1 WHERE id = ? AND shop_id = ?");
    $stmt->execute([$msgId, (int)$shop['id']]);

    flash('success', 'پیام به عنوان خوانده‌شده علامت‌گذاری شد.');
    safe_redirect_back('/shop/messages');
});

// 3. Delete message
route('POST', '/shop/messages/delete', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);
    verify_csrf_or_die();
    $shop = get_current_management_shop($user);

    $msgId = (int)($_POST['message_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM shop_contact_messages WHERE id = ? AND shop_id = ?");
    $stmt->execute([$msgId, (int)$shop['id']]);

    flash('success', 'پیام مورد نظر با موفقیت حذف گردید.');
    safe_redirect_back('/shop/messages');
});
