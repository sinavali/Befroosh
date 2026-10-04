<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function safe_redirect_back(string $default = '/'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($ref)) {
        $parsed = parse_url($ref);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if ((!isset($parsed['host']) || $parsed['host'] === $currentHost) && isset($parsed['path'])) {
            $path = $parsed['path'] . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            redirect($path);
        }
    }
    redirect($default);
}

function verify_csrf_or_die(): void
{
    if (!verify_csrf()) {
        flash('error', 'توکن امنیتی منقضی شده یا نامعتبر است.');
        safe_redirect_back('/');
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }

    return null;
}

function route(string $method, string $pattern, array $roles, callable $handler): void
{
    $GLOBALS['routes'][] = [
        'method' => $method,
        'pattern' => '#^' . $pattern . '$#',
        'roles' => $roles,
        'handler' => $handler,
    ];
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        return null;
    }

    $_SESSION['last_activity'] = time();

    if (isset($_SESSION['impersonated_admin_id']) && $user['role'] === 'superadmin') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' AND active = 1 AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['impersonated_admin_id']]);
        $imp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($imp) {
            $imp['role'] = 'admin';
            $imp['is_impersonated'] = true;
            $imp['real_user_id'] = $user['id'];
            return $imp;
        }
    }

    return $user;
}

function real_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function require_real_superadmin(): array
{
    $user = real_user();

    if (!$user) {
        redirect('/login');
    }

    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        error_page(403, 'دسترسی غیرمجاز', 'فقط مدیر ارشد به این بخش دسترسی دارد.');
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        error_page(403, 'دسترسی غیرمجاز', 'شما اجازه دسترسی به این صفحه را ندارید.');
    }

    return $user;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($pages, $page));
    $offset = ($page - 1) * $perPage;

    return ['page' => $page, 'pages' => $pages, 'offset' => $offset, 'perPage' => $perPage];
}

function en_to_fa_digits(string $s): string
{
    return str_replace(
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        $s
    );
}

function fa_to_en_digits(string $s): string
{
    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $s
    );
}

function format_irr(float $amount): string
{
    return en_to_fa_digits(number_format($amount, 0, '.', '،')) . ' ریال';
}

function tehran_timezone(): DateTimeZone
{
    static $tz = null;

    if ($tz === null) {
        try {
            $tz = new DateTimeZone('Asia/Tehran');
        } catch (Throwable $ex) {
            $tz = new DateTimeZone('+03:30');
        }
    }

    return $tz;
}

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

    $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100)
        + (int) (($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];

    $jy = -1595 + (33 * (int) ($days / 12053));
    $days %= 12053;

    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    $jm = ($days < 186) ? 1 + (int) ($days / 31) : 7 + (int) (($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;

    $days = -355668 + (365 * $jy) + ((int) ($jy / 33)) * 8 + (int) ((($jy % 33) + 3) / 4)
        + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

    $gy = 400 * (int) ($days / 146097);
    $days %= 146097;

    if ($days > 36524) {
        $gy += 100 * (int) (--$days / 36524);
        $days %= 36524;

        if ($days >= 365) {
            $days++;
        }
    }

    $gy += 4 * (int) ($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $gy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    $gd = $days + 1;

    $sal_a = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    if (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) {
        $sal_a[2] = 29;
    }

    $gm = 1;

    while ($gm <= 12 && $gd > $sal_a[$gm]) {
        $gd -= $sal_a[$gm];
        $gm++;
    }

    return [$gy, $gm, $gd];
}

function jalali_month_days(int $jy, int $jm): int
{
    if ($jm <= 6) {
        return 31;
    }

    if ($jm <= 11) {
        return 30;
    }

    return in_array($jy % 33, [1, 5, 9, 13, 17, 22, 26, 30], true) ? 30 : 29;
}

function parse_jalali_input(?string $s): ?array
{
    if ($s === null) {
        return null;
    }

    $s = trim(fa_to_en_digits($s));

    if (!preg_match('#^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$#', $s, $m)) {
        return null;
    }

    $jy = (int) $m[1];
    $jm = (int) $m[2];
    $jd = (int) $m[3];

    if ($jm < 1 || $jm > 12) {
        return null;
    }

    if ($jd < 1 || $jd > jalali_month_days($jy, $jm)) {
        return null;
    }

    return [$jy, $jm, $jd];
}

function format_jalali(?string $utc): string
{
    if (!$utc) {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $dt = $dt->setTimezone(tehran_timezone());
    } catch (Throwable $ex) {
        return '—';
    }

    [$jy, $jm, $jd] = gregorian_to_jalali(
        (int) $dt->format('Y'),
        (int) $dt->format('n'),
        (int) $dt->format('j')
    );

    return en_to_fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd))
        . ' ' . en_to_fa_digits($dt->format('H:i'));
}

function format_jalali_date(?string $utc): string
{
    if (!$utc) {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $dt = $dt->setTimezone(tehran_timezone());
    } catch (Throwable $ex) {
        return '—';
    }

    [$jy, $jm, $jd] = gregorian_to_jalali(
        (int) $dt->format('Y'),
        (int) $dt->format('n'),
        (int) $dt->format('j')
    );

    return en_to_fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
}

function jalali_today(bool $fa = true): string
{
    $dt = new DateTimeImmutable('now', tehran_timezone());

    [$jy, $jm, $jd] = gregorian_to_jalali(
        (int) $dt->format('Y'),
        (int) $dt->format('n'),
        (int) $dt->format('j')
    );

    $s = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

    return $fa ? en_to_fa_digits($s) : $s;
}

function jalali_to_utc(?string $jdate, bool $endOfDay = false): ?string
{
    $p = parse_jalali_input($jdate);

    if (!$p) {
        return null;
    }

    [$gy, $gm, $gd] = jalali_to_gregorian($p[0], $p[1], $p[2]);

    if (!checkdate($gm, $gd, $gy)) {
        return null;
    }

    $time = $endOfDay ? '23:59:59' : '00:00:00';

    try {
        $dt = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %s', $gy, $gm, $gd, $time),
            tehran_timezone()
        );

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $ex) {
        return null;
    }
}

function generate_uuid(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, 8);
}

function upload_image(array $file, string $destDir, string $prefix): ?string
{
    if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > 2_000_000) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if (!$finfo) {
        return null;
    }

    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return null;
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(5)) . '.jpg';
    $fullPath = $destDir . '/' . $filename;

    if (function_exists('imagecreatetruecolor')) {
        $src = null;

        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($file['tmp_name']);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($file['tmp_name']);
        }

        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $max = 1080;

        $canvas = null;

        if ($w > $max || $h > $max) {
            $ratio = min($max / $w, $max / $h);
            $nw = (int) ($w * $ratio);
            $nh = (int) ($h * $ratio);

            $canvas = imagecreatetruecolor($nw, $nh);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        } else {
            $canvas = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        }

        imagejpeg($canvas, $fullPath, 90);
        imagedestroy($canvas);
        imagedestroy($src);

        return $filename;
    }

    $ext = $allowed[$mime];
    $filename = $prefix . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $fullPath = $destDir . '/' . $filename;

    return move_uploaded_file($file['tmp_name'], $fullPath) ? $filename : null;
}

function delete_file(string $relPath): void
{
    $full = STORAGE_PATH . '/' . ltrim($relPath, '/');

    if (is_file($full)) {
        unlink($full);
    }
}

function json_snapshot(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

function json_unsnapshot(?string $json): array
{
    return $json ? (json_decode($json, true) ?: []) : [];
}

function update_seen(string $table, int $id, string $role): void
{
    global $pdo;

    $field = $role === 'customer' ? 'seen_by_customer' : 'seen_by_admin';
    $pdo->prepare("UPDATE $table SET $field = 1 WHERE id = ?")->execute([$id]);
}

function mark_other_unseen(string $table, int $id, string $who): void
{
    global $pdo;

    if ($who === 'customer') {
        $pdo->prepare("UPDATE $table SET seen_by_admin = 0 WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE $table SET seen_by_customer = 0 WHERE id = ?")->execute([$id]);
    }
}

function order_status_fa(string $status): string
{
    return [
        'submitted' => 'ثبت اولیه (رزرو)',
        'paid' => 'پرداخت شده و تایید شده',
        'shipped' => 'ارسال شده',
        'completed' => 'تحویل و تکمیل شده',
        'canceled' => 'لغو شده',
        // Backward compatibility
        'placed' => 'ثبت اولیه (رزرو)',
        'finalised' => 'پرداخت شده',
    ][$status] ?? $status;
}

function order_status_color(string $status): string
{
    return [
        'submitted' => 'blue',
        'placed' => 'blue',
        'paid' => 'emerald',
        'finalised' => 'emerald',
        'shipped' => 'purple',
        'completed' => 'emerald',
        'canceled' => 'rose',
    ][$status] ?? 'gray';
}

function order_badge(string $status): string
{
    return '<span class="badge badge-' . order_status_color($status) . '">' . order_status_fa($status) . '</span>';
}

function ticket_badge(string $status): string
{
    return $status === 'open'
        ? '<span class="badge badge-emerald">باز</span>'
        : '<span class="badge badge-gray">بسته</span>';
}

function role_fa(string $role): string
{
    return [
        'superadmin' => 'مدیر ارشد سامانه',
        'admin' => 'مدیر کل سامانه',
        'shop_owner' => 'مالک فروشگاه',
        'shop_manager' => 'مدیر فروشگاه',
        'customer' => 'مشتری',
    ][$role] ?? $role;
}

function tip(string $text, string $symbol = '?'): string
{
    return '<span class="tip" tabindex="0" data-tip="' . e($text) . '">' . e($symbol) . '</span>';
}

function icon(string $name, int $size = 17): string
{
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10" />',
        'orders' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />',
        'report' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
        'customers' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
        'tickets' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />',
        'products' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 4v16m8-8H4" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />',
        'filter' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />',
        'download' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />',
        'print' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M5 13l4 4L19 7" />',
        'x' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M6 18L18 6M6 6l12 12" />',
        'menu' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4 6h16M4 12h16M4 18h16" />',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
        'location' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />',
        'send' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />',
        'lock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
    ];

    $inner = $icons[$name] ?? $icons['orders'];

    return '<svg width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg>';
}

function empty_state(string $title, string $desc = '', string $iconName = 'orders'): string
{
    return '<div class="empty-state">
        <div class="empty-state-icon">' . icon($iconName, 30) . '</div>
        <h3>' . e($title) . '</h3>
        ' . ($desc ? '<p>' . e($desc) . '</p>' : '') . '
    </div>';
}

function pagination_html(string $base, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }

    $params = $_GET;
    unset($params['page']);

    $make = function (int $p, string $label, bool $active = false, bool $disabled = false) use ($base, $params): string {
        if ($disabled) {
            return '<span class="page-link disabled">' . $label . '</span>';
        }

        $params['page'] = $p;
        $url = $base . '?' . http_build_query($params);

        return '<a class="page-link' . ($active ? ' active' : '') . '" href="' . e($url) . '">' . $label . '</a>';
    };

    $html = '<div class="pagination">';
    $html .= $make(max(1, $page - 1), 'قبلی', false, $page <= 1);

    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);

    if ($start > 1) {
        $html .= $make(1, en_to_fa_digits('1'));

        if ($start > 2) {
            $html .= '<span class="page-link disabled">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $html .= $make($i, en_to_fa_digits((string) $i), $i === $page);
    }

    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<span class="page-link disabled">…</span>';
        }

        $html .= $make($pages, en_to_fa_digits((string) $pages));
    }

    $html .= $make(min($pages, $page + 1), 'بعدی', false, $page >= $pages);
    $html .= '</div>';

    return $html;
}

function get_sort(array $allowed, string $default = 'id', string $defaultDir = 'desc'): array
{
    $sort = $_GET['sort'] ?? $default;

    if (!in_array($sort, $allowed, true)) {
        $sort = $default;
    }

    $dir = (($_GET['dir'] ?? $defaultDir) === 'asc') ? 'asc' : 'desc';

    return [$sort, $dir];
}

function sort_link(string $base, string $label, string $field, string $currentSort, string $currentDir): string
{
    $params = $_GET;
    $params['sort'] = $field;
    $params['dir'] = ($field === $currentSort && $currentDir === 'desc') ? 'asc' : 'desc';

    unset($params['page']);

    $arrow = '';

    if ($field === $currentSort) {
        $arrow = $currentDir === 'desc' ? ' ↓' : ' ↑';
    }

    $url = $base . '?' . http_build_query($params);

    return '<a class="sort-link' . ($field === $currentSort ? ' active' : '') . '" href="' . e($url) . '">' . e($label) . $arrow . '</a>';
}

/* ============================================================
   RATE LIMITING & THROTTLING
============================================================ */
function check_rate_limit(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
{
    global $pdo;
    $now = time();

    if (mt_rand(1, 100) === 1) {
        try {
            $pdo->prepare("DELETE FROM rate_limits WHERE reset_at < ?")->execute([$now]);
        } catch (Throwable $e) {}
    }

    try {
        $stmt = $pdo->prepare("SELECT hits, reset_at FROM rate_limits WHERE key = ?");
        $stmt->execute([$key]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            if ((int)$record['reset_at'] < $now) {
                $pdo->prepare("UPDATE rate_limits SET hits = 1, reset_at = ? WHERE key = ?")
                    ->execute([$now + $windowSeconds, $key]);
                return true;
            }

            if ((int)$record['hits'] >= $maxAttempts) {
                return false;
            }

            $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE key = ?")->execute([$key]);
            return true;
        }

        $pdo->prepare("INSERT INTO rate_limits (key, hits, reset_at) VALUES (?, 1, ?)")
            ->execute([$key, $now + $windowSeconds]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function throttle_request(string $action = 'general', int $maxAttempts = 120, int $windowSeconds = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = "throttle:{$action}:{$ip}";
    if (!check_rate_limit($key, $maxAttempts, $windowSeconds)) {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>درخواست‌های بیش از حد</title><style>body{font-family:Tahoma,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:480px;text-align:center;color:#1f2937;}h1{color:#dc2626;font-size:1.4rem;margin-bottom:1rem;}p{line-height:1.6;color:#4b5563;}</style></head><body><div class="card"><h1>تعداد درخواست‌های بیش از حد مجاز (۴۲۹)</h1><p>لطفاً چند لحظه صبر کرده و مجدداً تلاش نمایید.</p></div></body></html>';
        exit;
    }
}

/* ============================================================
   MULTI-TENANT & SHOP HELPERS
============================================================ */
function active_shop_id(): ?int
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    if ($user['role'] === 'superadmin') {
        if (!empty($_SESSION['active_shop_id'])) {
            return (int)$_SESSION['active_shop_id'];
        }
        return 1;
    }
    return !empty($user['shop_id']) ? (int)$user['shop_id'] : 1;
}

function current_shop(): ?array
{
    $shopId = active_shop_id();
    if (!$shopId) {
        return null;
    }
    return get_shop($shopId);
}

function get_shop(int $id): ?array
{
    global $pdo;
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
    $stmt->execute([$id]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);
    $cache[$id] = $shop ?: null;
    return $cache[$id];
}

function all_active_shops(): array
{
    global $pdo;
    return $pdo->query("SELECT * FROM shops WHERE active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function require_shop_role(array $roles, ?int $targetShopId = null): array
{
    $user = require_roles($roles);
    if ($user['role'] === 'superadmin') {
        return $user;
    }
    if ($targetShopId !== null && (int)($user['shop_id'] ?? 0) !== $targetShopId) {
        http_response_code(403);
        error_page(403, 'دسترسی غیرمجاز', 'شما به داده‌های این فروشگاه دسترسی ندارید.');
    }
    return $user;
}

/* ============================================================
   PAYMENT STATUSES & METHODS
============================================================ */
function payment_method_fa(?string $method): string
{
    return [
        'card_to_card' => 'کارت به کارت',
        'cash_on_delivery' => 'پرداخت در محل',
        'credit' => 'حساب دفتری / اعتباری',
        'other' => 'سایر روش‌ها',
    ][$method ?? ''] ?? ($method ?: 'کارت به کارت');
}

function payment_status_fa(?string $status): string
{
    return [
        'unpaid' => 'پرداخت نشده',
        'pending_verification' => 'در انتظار تایید فیش',
        'paid' => 'پرداخت شده و تایید شده',
        'rejected' => 'فیش رد شده',
    ][$status ?? ''] ?? ($status ?: 'نامشخص');
}

function payment_badge(?string $status): string
{
    $colors = [
        'unpaid' => 'rose',
        'pending_verification' => 'amber',
        'paid' => 'emerald',
        'rejected' => 'gray',
    ];
    $c = $colors[$status ?? ''] ?? 'gray';
    return '<span class="badge badge-' . $c . '">' . payment_status_fa($status) . '</span>';
}

/* ============================================================
   PERSIAN NUMBER TO WORDS (FOR FORMAL INVOICES)
============================================================ */
function number_to_fa_words(float|int $num): string
{
    $num = (int) round($num);
    if ($num === 0) return 'صفر';
    if ($num < 0) return 'منفی ' . number_to_fa_words(abs($num));

    $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    $teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    $tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    $hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    $scales = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'];

    $chunks = [];
    while ($num > 0) {
        $chunks[] = $num % 1000;
        $num = (int) ($num / 1000);
    }

    $words = [];
    foreach ($chunks as $i => $chunk) {
        if ($chunk === 0) continue;
        $part = [];
        $h = (int) ($chunk / 100);
        $remainder = $chunk % 100;
        if ($h > 0) {
            $part[] = $hundreds[$h];
        }
        if ($remainder >= 10 && $remainder <= 19) {
            $part[] = $teens[$remainder - 10];
        } else {
            $t = (int) ($remainder / 10);
            $o = $remainder % 10;
            if ($t > 0) $part[] = $tens[$t];
            if ($o > 0) $part[] = $ones[$o];
        }
        $partStr = implode(' و ', $part);
        if ($i > 0 && isset($scales[$i])) {
            $partStr .= ' ' . $scales[$i];
        }
        array_unshift($words, $partStr);
    }

    return implode(' و ', $words);
}

/* ============================================================
   INVENTORY & ACCOUNTING HELPERS
============================================================ */
function inv_tx_type_fa(string $type): string
{
    return [
        'inward' => 'ورود به انبار (خرید)',
        'outward_order' => 'خروج بابت سفارش',
        'adjustment_plus' => 'تعدیل افزایشی',
        'adjustment_minus' => 'تعدیل کاهشی',
        'return_in' => 'مرجوعی به انبار',
        'write_off' => 'ضایعات / خروج از چرخه',
    ][$type] ?? $type;
}

function record_inventory_tx(
    int $shopId,
    int $productId,
    string $type,
    float $quantity,
    ?float $unitCost = null,
    ?string $referenceType = null,
    ?int $referenceId = null,
    ?string $notes = null,
    ?int $userId = null
): void {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO inventory_transactions (shop_id, product_id, type, quantity, unit_cost, reference_type, reference_id, notes, created_by_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$shopId, $productId, $type, $quantity, $unitCost, $referenceType, $referenceId, $notes, $userId]);

    $delta = in_array($type, ['inward', 'adjustment_plus', 'return_in'], true) ? $quantity : -$quantity;
    $upd = $pdo->prepare("UPDATE products SET stock_quantity = MAX(0, stock_quantity + ?) WHERE id = ?");
    $upd->execute([$delta, $productId]);
}

function record_accounting_entry(
    int $shopId,
    string $entryType,
    ?int $orderId,
    float $debit,
    float $credit,
    string $account,
    string $description,
    ?int $userId = null,
    ?string $date = null
): void {
    global $pdo;
    $date = $date ?: gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (shop_id, entry_date, entry_type, order_id, debit, credit, account, description, created_by_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$shopId, $date, $entryType, $orderId, $debit, $credit, $account, $description, $userId]);
}

/* ============================================================
   PHONE NORMALIZATION & FORMATTING
============================================================ */
function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/[^\d]/', '', fa_to_en_digits($phone));
    if (str_starts_with($digits, '98') && strlen($digits) === 12) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return $digits;
    }
    return null;
}

function format_phone(?string $phone): string
{
    if (!$phone) return '—';
    $norm = normalize_phone($phone) ?: preg_replace('/[^\d]/', '', $phone);
    if (strlen($norm) === 10) {
        return '۰' . en_to_fa_digits(substr($norm, 0, 3) . ' ' . substr($norm, 3, 3) . ' ' . substr($norm, 6));
    }
    return en_to_fa_digits($phone);
}

/* ============================================================
   BANK CARDS & MULTI-PAYMENT HELPERS
============================================================ */
function format_card_number(?string $card): string
{
    if (!$card) return '—';
    $digits = preg_replace('/[^\d]/', '', fa_to_en_digits($card));
    if (strlen($digits) === 16) {
        return chunk_split($digits, 4, ' ');
    }
    return en_to_fa_digits($card);
}

function get_shop_active_cards(int $shopId): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM shop_bank_cards WHERE shop_id = ? AND active = 1 ORDER BY id ASC");
    $stmt->execute([$shopId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   ROLES & PERMISSION HELPERS
============================================================ */
function is_superadmin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return ($user['role'] ?? '') === 'superadmin';
}

function is_platform_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return in_array($user['role'] ?? '', ['superadmin', 'admin'], true);
}

function is_shop_owner(?array $user = null, ?int $shopId = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    if ($user['role'] === 'superadmin') return true;
    if ($user['role'] !== 'shop_owner') return false;
    if ($shopId === null) return true;
    return (int)($user['shop_id'] ?? 0) === $shopId;
}

function is_shop_manager(?array $user = null, ?int $shopId = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    if (in_array($user['role'], ['superadmin', 'admin', 'shop_owner'], true)) return true;
    if ($user['role'] !== 'shop_manager') return false;
    if ($shopId === null) return true;
    return (int)($user['shop_id'] ?? 0) === $shopId;
}

function can_manage_shop(?array $user, int $shopId): bool
{
    if (!$user) return false;
    if (in_array($user['role'], ['superadmin', 'admin'], true)) return true;
    if (in_array($user['role'], ['shop_owner', 'shop_manager'], true) && (int)($user['shop_id'] ?? 0) === $shopId) {
        return true;
    }
    return false;
}

function can_impersonate(?array $user = null): bool
{
    $user = $user ?? current_user();
    return in_array($user['role'] ?? '', ['superadmin', 'admin'], true);
}

/* ============================================================
   UTF-8 SLUG GENERATOR
============================================================ */
function make_utf8_slug(string $title, int $max = 150): string
{
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', trim($title));
    $slug = trim($slug, '-');
    return mb_substr($slug, 0, $max, 'UTF-8') ?: 'item';
}

/* ============================================================
   RESERVATION EXPIRY RELEASE (AUTO RESTORE TO INVENTORY)
============================================================ */
function release_expired_reservations(): int
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT id, shop_id, uuid FROM orders 
        WHERE status = 'submitted' 
        AND reservation_expires_at IS NOT NULL 
        AND reservation_expires_at < ?
    ");
    $stmt->execute([$now]);
    $expiredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($expiredOrders as $order) {
        $items = $pdo->prepare("SELECT product_id, quantity, unit_cost_price FROM order_items WHERE order_id = ?");
        $items->execute([$order['id']]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
            record_inventory_tx(
                (int)$order['shop_id'],
                (int)$it['product_id'],
                'return_in',
                (float)$it['quantity'],
                (float)$it['unit_cost_price'],
                'reservation_expired',
                (int)$order['id'],
                'لغو خودکار به دلیل انقضای مهلت رزرو کالا'
            );
        }

        $pdo->prepare("
            UPDATE orders 
            SET status = 'canceled', cancellation_reason = 'انقضای مهلت رزرو ۴ روزه و عدم پرداخت', canceled_at = datetime('now'), updated_at = datetime('now') 
            WHERE id = ?
        ")->execute([$order['id']]);
        $count++;
    }
    return $count;
}

/* ============================================================
   PURCHASE LIMITS VALIDATION (PER ORDER & PER 30-DAY MONTH)
============================================================ */
function check_product_purchase_limits(int $customerId, int $productId, float $requestedQty): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, max_per_order, max_per_month, stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        return ['ok' => false, 'error' => 'محصول یافت نشد.'];
    }

    if ((float)$prod['stock_quantity'] < $requestedQty) {
        return ['ok' => false, 'error' => "موجودی کالای «{$prod['title']}» در انبار کافی نیست (موجودی فعلی: {$prod['stock_quantity']})."];
    }

    $maxPerOrder = (float)$prod['max_per_order'];
    if ($maxPerOrder > 0 && $requestedQty > $maxPerOrder) {
        return ['ok' => false, 'error' => "حداکثر تعداد قابل سفارش برای کالای «{$prod['title']}» در هر سفارش {$maxPerOrder} عدد می‌باشد."];
    }

    $maxPerMonth = (float)$prod['max_per_month'];
    if ($maxPerMonth > 0) {
        $pastMonth = date('Y-m-d H:i:s', time() - (30 * 86400));
        $sumStmt = $pdo->prepare("
            SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.customer_id = ? AND oi.product_id = ? AND o.status != 'canceled' AND o.created_at >= ?
        ");
        $sumStmt->execute([$customerId, $productId, $pastMonth]);
        $alreadyBought = (float)$sumStmt->fetchColumn();

        if (($alreadyBought + $requestedQty) > $maxPerMonth) {
            $remaining = max(0, $maxPerMonth - $alreadyBought);
            return ['ok' => false, 'error' => "سقف خرید ماهانه شما برای کالای «{$prod['title']}» {$maxPerMonth} عدد است. شما در ۳۰ روز گذشته {$alreadyBought} عدد خریداری کرده‌اید و حداکثر می‌توانید {$remaining} عدد دیگر سفارش دهید."];
        }
    }

    return ['ok' => true, 'error' => null];
}

/* ============================================================
   CART & BOOKMARKS HELPERS
============================================================ */
function get_user_cart(int $userId, ?int $shopId = null): array
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("DELETE FROM cart_items WHERE expires_at < ?")->execute([$now]);

    $sql = "
        SELECT c.*, p.title AS product_title, p.price, p.image_path, p.stock_quantity, p.max_per_order, p.max_per_month, p.unit, p.tax_rate, s.name AS shop_name, s.slug AS shop_slug, s.default_shipping_cost, s.free_shipping_threshold
        FROM cart_items c
        JOIN products p ON p.id = c.product_id
        JOIN shops s ON s.id = c.shop_id
        WHERE c.user_id = ?
    ";
    $params = [$userId];
    if ($shopId !== null) {
        $sql .= " AND c.shop_id = ?";
        $params[] = $shopId;
    }
    $sql .= " ORDER BY c.shop_id ASC, c.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_cart_count(int $userId): int
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE user_id = ? AND expires_at >= ?");
    $stmt->execute([$userId, $now]);
    return (int)$stmt->fetchColumn();
}