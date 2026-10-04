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

require_once __DIR__ . '/routes_tickets_create.php';
require_once __DIR__ . '/routes_tickets_view.php';