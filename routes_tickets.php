<?php
declare(strict_types=1);

route('GET', '/tickets(?:\.php)?', ['customer', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();

    $isStaff = in_array($user['role'], ['admin', 'superadmin'], true);

    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 15;

    $my = 0;

    if ($isStaff) {
        $my = isset($_GET['my']) ? (int) $_GET['my'] : 0;
    }

    [$sort, $dir] = get_sort(['id', 'subject', 'created_at', 'updated_at'], 'updated_at');

    $where = "WHERE 1=1";
    $params = [];

    if ($user['role'] === 'customer') {
        $where .= " AND t.customer_id = ?";
        $params[] = $user['id'];
    } elseif ($user['role'] === 'admin') {
        $where .= " AND t.shop_id = ?";
        $params[] = active_shop_id();
    }

    if ($status !== '') {
        $where .= " AND t.status = ?";
        $params[] = $status;
    }

    if ($isStaff && $my) {
        $where .= " AND EXISTS (
            SELECT 1
            FROM ticket_messages tm_mine
            WHERE tm_mine.ticket_id = t.id
              AND tm_mine.sender_id = ?
        )";
        $params[] = $user['id'];
    }

    if ($search !== '') {
        $where .= " AND (
            t.subject LIKE ?
            OR t.body LIKE ?
            OR u.nickname LIKE ?
            OR u.username LIKE ?
            OR EXISTS (
                SELECT 1
                FROM ticket_messages tm_search
                WHERE tm_search.ticket_id = t.id
                  AND tm_search.message LIKE ?
            )
        )";

        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.customer_id $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $sortSql = match ($sort) {
        'subject' => 't.subject',
        'created_at' => 't.created_at',
        'updated_at' => 't.updated_at',
        default => 't.id',
    };

    $stmt = $pdo->prepare("
        SELECT t.*, u.nickname AS customer_nickname
        FROM tickets t
        JOIN users u ON u.id = t.customer_id
        $where
        ORDER BY {$sortSql} {$dir}, t.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lastAdminMap = [];

    if ($tickets) {
        $ticketIds = array_column($tickets, 'id');
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

        $stmt = $pdo->prepare("
            SELECT x.ticket_id, u.nickname
            FROM ticket_messages x
            JOIN users u ON u.id = x.sender_id
            WHERE x.sender_type IN ('admin','superadmin')
              AND x.id IN (
                  SELECT MAX(y.id)
                  FROM ticket_messages y
                  WHERE y.sender_type IN ('admin','superadmin')
                    AND y.ticket_id IN ($placeholders)
                  GROUP BY y.ticket_id
              )
        ");

        $stmt->execute($ticketIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lastAdminMap[(int) $row['ticket_id']] = $row['nickname'];
        }
    }

    layout_start('تیکت‌ها', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('tickets', 18) ?></div>
            <div>
                <h1>تیکت‌ها</h1>
                <div class="page-sub">پیگیری درخواست‌ها و پشتیبانی</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/tickets/create"><?= icon('plus', 14) ?> تیکت جدید</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجو در موضوع، متن، پیام‌ها..."
                    value="<?= e($search) ?>">

                <select class="select" name="status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>باز</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>بسته</option>
                </select>

                <?php if ($isStaff): ?>
                    <input type="hidden" name="my" value="0">
                    <label class="form-check">
                        <input type="checkbox" name="my" value="1" <?= $my ? 'checked' : '' ?>>
                        فقط تیکت‌های من
                        <?= tip('فقط تیکت‌هایی که شما حداقل یک پیام در آن‌ها ارسال کرده‌اید نمایش داده می‌شوند.') ?>
                    </label>
                <?php endif; ?>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>

                <?php if ($search !== '' || $status !== '' || ($isStaff && isset($_GET['my']))): ?>
                    <a class="btn btn-ghost" href="/tickets">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= sort_link('/tickets', 'موضوع', 'subject', $sort, $dir) ?></th>
                        <?php if ($user['role'] !== 'customer'): ?>
                            <th>مشتری</th><?php endif; ?>
                        <th>وضعیت</th>
                        <th><?= sort_link('/tickets', 'آخرین بروزرسانی', 'updated_at', $sort, $dir) ?></th>
                        <?php if ($isStaff): ?>
                            <th>آخرین پاسخ مدیر</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$tickets): ?>
                        <tr>
                            <td colspan="<?= $user['role'] === 'customer' ? 4 : ($isStaff ? 6 : 5) ?>">
                                <?= empty_state('تیکتی یافت نشد') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php $seenField = $user['role'] === 'customer' ? 'seen_by_customer' : 'seen_by_admin'; ?>
                            <tr>
                                <td>
                                    <strong><?= e($t['subject']) ?></strong>
                                    <?php if (!$t[$seenField]): ?><span class="badge badge-rose">جدید</span><?php endif; ?>
                                </td>

                                <?php if ($user['role'] !== 'customer'): ?>
                                    <td><?= e($t['customer_nickname']) ?></td>
                                <?php endif; ?>

                                <td><?= ticket_badge($t['status']) ?></td>
                                <td class="date-cell"><?= format_jalali($t['updated_at']) ?></td>

                                <?php if ($isStaff): ?>
                                    <td><?= e($lastAdminMap[(int) $t['id']] ?? '—') ?></td>
                                <?php endif; ?>

                                <td><a class="btn btn-outline btn-sm" href="/tickets/<?= (int) $t['id'] ?>">مشاهده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/tickets', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

route('GET|POST', '/tickets/create', ['customer', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_login();
    $isAdmin = in_array($user['role'], ['admin', 'superadmin'], true);

    $customerId = $isAdmin ? (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0) : (int) $user['id'];

    if ($isAdmin && !$customerId) {
        $customers = $pdo->query("SELECT id, nickname, username FROM users WHERE role = 'customer' AND active = 1 AND deleted_at IS NULL ORDER BY nickname")->fetchAll(PDO::FETCH_ASSOC);

        layout_start('انتخاب مشتری', $user);
        ?>
        <div class="page-header">
            <div class="page-title-wrap">
                <div class="page-icon"><?= icon('tickets', 18) ?></div>
                <div>
                    <h1>تیکت جدید</h1>
                    <div class="page-sub">ابتدا مشتری مورد نظر را انتخاب کنید</div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width:640px">
            <div class="card-body">
                <form method="get">
                    <div class="form-group">
                        <label>مشتری</label>
                        <select class="select" name="customer_id" required>
                            <option value="">انتخاب مشتری...</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['nickname']) ?> (<?= e($c['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary">ادامه</button>
                </form>
            </div>
        </div>
        <?php
        layout_end();
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        flash('error', 'مشتری انتخاب‌شده معتبر نیست.');
        redirect('/tickets/create');
    }

    $stmt = $pdo->prepare("SELECT id, uuid, status, created_at FROM orders WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_die();

        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $orderId = (int) ($_POST['order_id'] ?? 0);

        if (!$subject || !$body) {
            $error = 'موضوع و متن تیکت ضروری است.';
        } else {
            $pdo->beginTransaction();

            try {
                $seenByCustomer = $user['role'] === 'customer' ? 1 : 0;
                $seenByAdmin = $user['role'] === 'customer' ? 0 : 1;
                $ticketShopId = active_shop_id() ?? 1;

                if ($orderId) {
                    $sShop = $pdo->prepare("SELECT shop_id FROM orders WHERE id = ?");
                    $sShop->execute([$orderId]);
                    $fShop = $sShop->fetchColumn();
                    if ($fShop) $ticketShopId = (int)$fShop;
                }

                $pdo->prepare("INSERT INTO tickets (shop_id, customer_id, subject, body, status, created_by_type, created_by_id, seen_by_customer, seen_by_admin, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?, datetime('now'), datetime('now'))")
                    ->execute([$ticketShopId, $customerId, $subject, $body, $user['role'], $user['id'], $seenByCustomer, $seenByAdmin]);

                $ticketId = (int) $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES (?, ?, ?, ?, datetime('now'))")
                    ->execute([$ticketId, $user['role'], $user['id'], $body]);

                $msgId = (int) $pdo->lastInsertId();

                if ($orderId) {
                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ?");
                    $stmt->execute([$orderId, $customerId]);

                    if ($stmt->fetch()) {
                        $pdo->prepare("INSERT INTO ticket_order_relations (ticket_id, order_id) VALUES (?, ?)")
                            ->execute([$ticketId, $orderId]);
                    }
                }

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

                $pdo->commit();

                flash('success', 'تیکت ایجاد شد.');
                redirect('/tickets/' . $ticketId);
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $error = 'خطا در ایجاد تیکت.';
            }
        }
    }

    layout_start('تیکت جدید', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('tickets', 18) ?></div>
            <div>
                <h1>تیکت جدید</h1>
                <div class="page-sub">مشتری: <strong><?= e($customer['nickname']) ?></strong></div>
            </div>
        </div>
        <a class="btn btn-outline" href="/tickets">بازگشت</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label>موضوع <?= tip('یک عنوان کوتاه و گویا برای درخواست.') ?></label>
                    <input class="input" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>متن تیکت <?= tip('شرح کامل درخواست یا مشکل.') ?></label>
                    <textarea class="textarea" name="body" required><?= e($_POST['body'] ?? '') ?></textarea>
                </div>

                <?php if ($orders): ?>
                    <div class="form-group">
                        <label>مرتبط با سفارش <?= tip('در صورت نیاز، یک سفارش مرتبط را انتخاب کنید.') ?></label>
                        <select class="select" name="order_id">
                            <option value="">بدون ارتباط با سفارش</option>
                            <?php foreach ($orders as $o): ?>
                                <option value="<?= (int) $o['id'] ?>">
                                    [<?= format_jalali($o['created_at']) ?>]-[<?= e($o['uuid']) ?>]-[<?= order_status_fa($o['status']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>پیوست‌ها <?= tip('حداکثر ۵ تصویر، هر کدام حداکثر ۲ مگابایت.') ?></label>
                    <input class="input" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><?= icon('send', 14) ?> ایجاد تیکت</button>
            </div>
        </div>
    </form>
    <?php
    layout_end();
});

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