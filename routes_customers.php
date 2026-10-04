<?php
declare(strict_types=1);

/**
 * routes_customers.php
 * Customer Accounts & Customer Delivery Addresses Management
 * Supports mandatory 10-digit mobile phone number, first/last name, and national code
 */

// Customers listing
route('GET', '/customers(?:\.php)?', ['shop_owner', 'shop_manager', 'admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['shop_owner', 'shop_manager', 'admin', 'superadmin']);

    $search = trim($_GET['search'] ?? '');
    $active = $_GET['active'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    [$sort, $dir] = get_sort(['id', 'username', 'nickname', 'phone', 'created_at'], 'id');

    $where = "WHERE role = 'customer' AND deleted_at IS NULL";
    $params = [];

    if ($search !== '') {
        $where .= " AND (username LIKE ? OR nickname LIKE ? OR phone LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($active !== '') {
        $where .= " AND active = ?";
        $params[] = (int)$active;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY $sort $dir LIMIT {$pag['perPage']} OFFSET {$pag['offset']}");
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    layout_start('مدیریت مشتریان', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('customers', 18) ?></div>
            <div>
                <h1>مدیریت مشتریان</h1>
                <div class="page-sub">مدیریت حساب‌های کاربری مشتریان، تلفن همراه و آدرس‌های تحویل</div>
            </div>
        </div>
        <a class="btn btn-primary" href="/customers/create"><?= icon('plus', 14) ?> افزودن مشتری جدید</a>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar" method="get">
                <input class="input" type="text" name="search" placeholder="جستجوی نام، موبایل، نام کاربری..." value="<?= e($search) ?>" style="min-width:260px;">

                <select class="select" name="active">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>غیرفعال</option>
                </select>

                <button class="btn btn-outline"><?= icon('search', 14) ?> فیلتر</button>
                <?php if ($search !== '' || $active !== ''): ?>
                    <a class="btn btn-ghost" href="/customers">حذف فیلترها</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- CUSTOMERS TABLE -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام و نام خانوادگی</th>
                        <th>شماره موبایل</th>
                        <th>نام کاربری</th>
                        <th>کد ملی</th>
                        <th>وضعیت</th>
                        <th>تاریخ عضویت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="8"><?= empty_state('مشتری یافت نشد') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>#<?= en_to_fa_digits((string)$c['id']) ?></td>
                            <td><strong><?= e($c['nickname']) ?></strong></td>
                            <td dir="ltr" style="font-family:monospace;"><?= format_phone($c['phone']) ?></td>
                            <td><code><?= e($c['username']) ?></code></td>
                            <td><?= e($c['national_code'] ?: '—') ?></td>
                            <td>
                                <?= $c['active'] ? '<span class="badge badge-emerald">فعال</span>' : '<span class="badge badge-rose">غیرفعال</span>' ?>
                            </td>
                            <td class="date-cell"><?= format_jalali($c['created_at']) ?></td>
                            <td>
                                <div class="flex gap-1">
                                    <a class="btn btn-outline btn-sm" href="/customers/<?= (int)$c['id'] ?>/addresses"><?= icon('location', 13) ?> آدرس‌ها</a>
                                    <a class="btn btn-outline btn-sm" href="/customers/<?= (int)$c['id'] ?>/edit"><?= icon('edit', 13) ?> ویرایش</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/customers', $pag['page'], $pag['pages']) ?>
    <?php
    layout_end();
});

require_once __DIR__ . '/routes_customers_form.php';
require_once __DIR__ . '/routes_customers_addresses.php';
