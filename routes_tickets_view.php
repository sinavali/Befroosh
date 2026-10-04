<?php
declare(strict_types=1);

route('GET', '/tickets/(\d+)', ['customer', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();

    $stmt = $pdo->prepare("SELECT t.*, u.nickname AS customer_nickname FROM tickets t JOIN users u ON u.id = t.customer_id WHERE t.id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket || ($user['role'] === 'customer' && (int) $ticket['customer_id'] !== (int) $user['id'])) {
        error_page(404, 'یافت نشد', 'تیکت مورد نظر وجود ندارد.');
    }

    if ($user['role'] === 'admin' && !empty($ticket['shop_id']) && (int)$ticket['shop_id'] !== (int)active_shop_id()) {
        error_page(403, 'دسترسی غیرمجاز', 'این تیکت متعلق به فروشگاه شما نیست.');
    }

    update_seen('tickets', (int) $id, $user['role'] === 'customer' ? 'customer' : 'admin');

    $stmt = $pdo->prepare("
        SELECT m.*, u.nickname AS sender_name
        FROM ticket_messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.ticket_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT o.uuid, o.id
        FROM ticket_order_relations tor
        JOIN orders o ON o.id = tor.order_id
        WHERE tor.ticket_id = ?
    ");
    $stmt->execute([$id]);
    $relatedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT u.nickname
        FROM ticket_messages tm
        JOIN users u ON u.id = tm.sender_id
        WHERE tm.ticket_id = ?
          AND tm.sender_type IN ('admin','superadmin')
        ORDER BY tm.id DESC
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $lastAdminName = $stmt->fetchColumn();

    layout_start('مشاهده تیکت', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('tickets', 18) ?></div>
            <div>
                <h1><?= e($ticket['subject']) ?></h1>
                <div class="page-sub">
                    <?= $user['role'] !== 'customer' ? 'مشتری: ' . e($ticket['customer_nickname']) . ' | ' : '' ?>
                    وضعیت: <?= ticket_badge($ticket['status']) ?>
                    <?php if (in_array($user['role'], ['admin', 'superadmin'], true)): ?>
                        | آخرین پاسخ مدیر: <strong><?= e($lastAdminName ?: '—') ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (in_array($user['role'], ['admin', 'superadmin'], true)): ?>
            <div class="action-cluster">
                <?php if ($ticket['status'] === 'open'): ?>
                    <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/close" class="inline-form"
                        data-confirm="این تیکت بسته شود؟">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm">بستن تیکت</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/reopen" class="inline-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-success btn-sm">بازگشایی تیکت</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($relatedOrders): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2>سفارش‌های مرتبط</h2>
            </div>
            <div class="card-body">
                <div class="action-cluster">
                    <?php foreach ($relatedOrders as $ro): ?>
                        <a class="btn btn-outline btn-sm" href="/orders/<?= (int) $ro['id'] ?>"><?= e($ro['uuid']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="chat">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $mine = (int) $msg['sender_id'] === (int) $user['id'] && $msg['sender_type'] === $user['role'];
                    ?>
                    <div class="message <?= $mine ? 'mine' : 'other' ?>">
                        <div class="message-meta">
                            <strong><?= e($msg['sender_name']) ?> (<?= e(role_fa($msg['sender_type'])) ?>)</strong>
                            <span class="date-cell"><?= format_jalali($msg['created_at']) ?></span>
                        </div>

                        <div class="message-body"><?= e($msg['message']) ?></div>

                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_message_id = ?");
                        $stmt->execute([$msg['id']]);
                        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if ($attachments): ?>
                            <div class="attachments">
                                <?php foreach ($attachments as $att): ?>
                                    <a href="/storage/<?= e($att['file_path']) ?>" target="_blank">
                                        <img src="/storage/<?= e($att['file_path']) ?>" alt="پیوست">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/reply" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-header">
                <h2>پاسخ جدید</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>متن پاسخ</label>
                    <textarea class="textarea" name="message" required></textarea>
                </div>
                <div class="form-group">
                    <label>پیوست‌ها</label>
                    <input class="input" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('send', 14) ?> ارسال پاسخ</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

route('POST', '/tickets/(\d+)/reply', ['customer', 'admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_login();
    verify_csrf_or_die();

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket || ($user['role'] === 'customer' && (int) $ticket['customer_id'] !== (int) $user['id'])) {
        redirect('/tickets');
    }

    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        flash('error', 'متن پاسخ نمی‌تواند خالی باشد.');
        redirect("/tickets/$id");
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES (?, ?, ?, ?, datetime('now'))")
            ->execute([$id, $user['role'], $user['id'], $message]);

        $msgId = (int) $pdo->lastInsertId();

        $files = $_FILES['attachments'] ?? [];
        $count = 0;

        if (!empty($files['name'][0])) {
            foreach ($files['name'] as $i => $name) {
                if ($count >= 5)
                    break;
                if ($files['error'][$i] !== UPLOAD_ERR_OK)
                    continue;

                $tmp = [
                    'name' => $name,
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];

                $newName = upload_image($tmp, TICKET_UPLOAD_PATH, 'att');

                if ($newName) {
                    $pdo->prepare("INSERT INTO ticket_attachments (ticket_message_id, file_path, created_at) VALUES (?, ?, datetime('now'))")
                        ->execute([$msgId, "tickets/$newName"]);

                    $count++;
                }
            }
        }

        $pdo->prepare("UPDATE tickets SET status = 'open', updated_at = datetime('now') WHERE id = ?")->execute([$id]);
        mark_other_unseen('tickets', (int) $id, $user['role'] === 'customer' ? 'customer' : 'admin');

        $pdo->commit();

        flash('success', 'پاسخ ارسال شد.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('error', 'خطا در ارسال پاسخ.');
    }

    redirect("/tickets/$id");
});

route('POST', '/tickets/(\d+)/close', ['admin', 'superadmin'], function ($id) use ($pdo) {
    require_roles(['admin', 'superadmin']);
    verify_csrf_or_die();

    $pdo->prepare("UPDATE tickets SET status = 'closed', updated_at = datetime('now') WHERE id = ?")->execute([$id]);

    flash('success', 'تیکت بسته شد.');
    redirect("/tickets/$id");
});

route('POST', '/tickets/(\d+)/reopen', ['admin', 'superadmin'], function ($id) use ($pdo) {
    require_roles(['admin', 'superadmin']);
    verify_csrf_or_die();

    $pdo->prepare("UPDATE tickets SET status = 'open', updated_at = datetime('now') WHERE id = ?")->execute([$id]);

    flash('success', 'تیکت بازگشایی شد.');
    redirect("/tickets/$id");
});
