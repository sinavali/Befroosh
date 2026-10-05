<?php
declare(strict_types=1);

/**
 * routes_reports.php
 * Internal Incident & Audit System Reporting for Superadmins and Platform Admins
 */

route('GET', '/reports/system(?:\.php)?', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);

    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $severity = $_GET['severity'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    $where = "WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $where .= " AND r.status = ?";
        $params[] = $status;
    }

    if ($category !== '') {
        $where .= " AND r.category = ?";
        $params[] = $category;
    }

    if ($severity !== '') {
        $where .= " AND r.severity = ?";
        $params[] = $severity;
    }

    if ($search !== '') {
        $where .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM system_reports r $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pag = paginate($total, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT r.*, u.nickname AS reporter_name, s.name AS shop_name
        FROM system_reports r
        LEFT JOIN users u ON u.id = r.reporter_id
        LEFT JOIN shops s ON s.id = r.shop_id
        $where
        ORDER BY r.id DESC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $shops = all_active_shops();

    layout_start('گزارشات و بازرسی سیستم', $user);
    ?>
    <div class="page-header">
        <div class="page-title-wrap">
            <div class="page-icon"><?= icon('report', 18) ?></div>
            <div>
                <h1>گزارشات و بازرسی‌های سیستم</h1>
                <div class="page-sub">ثبت، پیگیری و بازرسی موارد تخلف، خطاها و هماهنگی‌های مدیریتی (ویژه مدیران کل)</div>
            </div>
        </div>
        <button class="btn btn-primary" data-modal="#newReportModal"><?= icon('plus', 14) ?> ثبت گزارش جدید</button>
    </div>

    <!-- FILTER BAR -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="filter-bar" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; align-items:end;">
                <div class="form-group mb-0">
                    <label>جستجو:</label>
                    <input class="input" type="text" name="search" placeholder="جستجو در عنوان یا متن..." value="<?= e($search) ?>" style="width:100%;">
                </div>
                <div class="form-group mb-0">
                    <label>وضعیت:</label>
                    <select class="select" name="status" style="width:100%;">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>باز</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>در حال بررسی</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>حل شده</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>بسته شده</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label>اولویت:</label>
                    <select class="select" name="severity" style="width:100%;">
                        <option value="">همه اولویت‌ها</option>
                        <option value="low" <?= $severity === 'low' ? 'selected' : '' ?>>عادی / کم</option>
                        <option value="normal" <?= $severity === 'normal' ? 'selected' : '' ?>>متوسط</option>
                        <option value="high" <?= $severity === 'high' ? 'selected' : '' ?>>مهم</option>
                        <option value="critical" <?= $severity === 'critical' ? 'selected' : '' ?>>بحرانی</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label>دسته‌بندی:</label>
                    <select class="select" name="category" style="width:100%;">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <option value="general" <?= $category === 'general' ? 'selected' : '' ?>>عمومی</option>
                        <option value="financial" <?= $category === 'financial' ? 'selected' : '' ?>>مالی / پرداخت</option>
                        <option value="compliance" <?= $category === 'compliance' ? 'selected' : '' ?>>تخلف / قوانین</option>
                        <option value="technical" <?= $category === 'technical' ? 'selected' : '' ?>>فنی / سیستم</option>
                    </select>
                </div>
                <div class="form-group mb-0" style="display:flex; gap:8px;">
                    <button class="btn btn-outline" style="flex:1;"><?= icon('search', 14) ?> فیلتر</button>
                    <?php if ($search || $status || $category || $severity): ?>
                        <a class="btn btn-ghost" href="/reports/system">پاک‌کردن</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- REPORTS LIST -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>عنوان گزارش</th>
                        <th>فروشگاه مربوطه</th>
                        <th>دسته</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>ثبت‌کننده</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="9"><?= empty_state('هیچ گزارشی یافت نشد') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                            <?php
                            $sevColor = match($r['severity']) {
                                'critical' => 'rose',
                                'high' => 'amber',
                                'normal' => 'blue',
                                default => 'gray'
                            };
                            $sevLabel = match($r['severity']) {
                                'critical' => 'بحرانی',
                                'high' => 'مهم',
                                'normal' => 'متوسط',
                                default => 'عادی'
                            };
                            $statColor = match($r['status']) {
                                'resolved' => 'emerald',
                                'in_progress' => 'amber',
                                'closed' => 'gray',
                                default => 'rose'
                            };
                            $statLabel = match($r['status']) {
                                'resolved' => 'حل شده',
                                'in_progress' => 'در حال بررسی',
                                'closed' => 'بسته شده',
                                default => 'باز'
                            };
                            ?>
                            <tr>
                                <td>#<?= en_to_fa_digits((string)$r['id']) ?></td>
                                <td>
                                    <strong><?= e($r['title']) ?></strong>
                                    <div style="font-size:0.75rem; color:#6b7280; max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?= e($r['description']) ?>
                                    </div>
                                </td>
                                <td><?= e($r['shop_name'] ?: 'سراسری / نامشخص') ?></td>
                                <td><code><?= e($r['category']) ?></code></td>
                                <td><span class="badge badge-<?= $sevColor ?>"><?= $sevLabel ?></span></td>
                                <td><span class="badge badge-<?= $statColor ?>"><?= $statLabel ?></span></td>
                                <td><?= e($r['reporter_name']) ?></td>
                                <td class="date-cell"><?= format_jalali($r['created_at']) ?></td>
                                <td>
                                    <form method="post" action="/reports/system/<?= (int)$r['id'] ?>/status" style="display:inline-flex; gap:4px;">
                                        <?= csrf_field() ?>
                                        <?php if ($r['status'] !== 'resolved'): ?>
                                            <button name="status" value="resolved" class="btn btn-outline btn-sm" title="تغییر وضعیت به حل شده">تایید حل</button>
                                        <?php else: ?>
                                            <button name="status" value="open" class="btn btn-ghost btn-sm" title="بازگشایی مجدد">بازگشایی</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pagination_html('/reports/system', $pag['page'], $pag['pages']) ?>

    <!-- NEW REPORT MODAL -->
    <div class="modal-backdrop" id="newReportModal">
        <div class="modal" style="max-width:540px;">
            <form method="post" action="/reports/system/create">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>ثبت گزارش بازرسی / رخداد جدید</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>عنوان گزارش *</label>
                        <input class="input" name="title" required placeholder="مثال: مغایرت فیش واریزی در سفارش #ABC">
                    </div>

                    <div class="form-grid mb-2" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>فروشگاه مربوطه</label>
                            <select class="select" name="shop_id">
                                <option value="">سراسری / نامشخص</option>
                                <?php foreach ($shops as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>دسته‌بندی</label>
                            <select class="select" name="category">
                                <option value="general">عمومی</option>
                                <option value="financial">مالی / پرداخت</option>
                                <option value="compliance">تخلف / قوانین</option>
                                <option value="technical">فنی / سیستم</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label>سطح اولویت / فوریت</label>
                        <select class="select" name="severity">
                            <option value="normal">متوسط</option>
                            <option value="low">عادی / کم</option>
                            <option value="high">مهم</option>
                            <option value="critical">بحرانی</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>توضیحات و جزئیات رخداد *</label>
                        <textarea class="textarea" name="description" rows="4" required placeholder="مشروح گزارش را به صورت مستند وارد نمایید..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><?= icon('check', 14) ?> ثبت گزارش</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    layout_end();
});

// Create report action
route('POST', '/reports/system/create', ['admin', 'superadmin'], function () use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    verify_csrf_or_die();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shopId = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    $category = trim($_POST['category'] ?? 'general');
    $severity = trim($_POST['severity'] ?? 'normal');

    if (!$title || !$description) {
        flash('error', 'عنوان و شرح گزارش الزامی هستند.');
        redirect('/reports/system');
    }

    $stmt = $pdo->prepare("
        INSERT INTO system_reports (reporter_id, shop_id, title, description, category, severity, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'open', datetime('now'))
    ");
    $stmt->execute([$user['id'], $shopId, $title, $description, $category, $severity]);

    flash('success', 'گزارش بازرسی با موفقیت ثبت شد.');
    redirect('/reports/system');
});

// Update report status
route('POST', '/reports/system/(\d+)/status', ['admin', 'superadmin'], function ($id) use ($pdo) {
    $user = require_roles(['admin', 'superadmin']);
    verify_csrf_or_die();

    $id = (int)$id;
    $newStatus = trim($_POST['status'] ?? 'open');
    if (!in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $newStatus = 'open';
    }

    $resolvedAt = ($newStatus === 'resolved' || $newStatus === 'closed') ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("UPDATE system_reports SET status = ?, resolved_at = ? WHERE id = ?");
    $stmt->execute([$newStatus, $resolvedAt, $id]);

    flash('success', 'وضعیت گزارش به‌روزرسانی شد.');
    redirect('/reports/system');
});
