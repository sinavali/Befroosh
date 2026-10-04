<?php
declare(strict_types=1);

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
