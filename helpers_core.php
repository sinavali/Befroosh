<?php
declare(strict_types=1);

/**
 * helpers_core.php
 * Core string formatting, digit conversion, CSRF, flash, routing, and upload utilities
 */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function safe_html(?string $html): string
{
    if ($html === null || $html === '') {
        return '';
    }
    return strip_tags($html, '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span><div>');
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

function set_flash(string $type, string $message): void
{
    flash($type, $message);
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
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $s
    );
}

function format_irt(float|int $amount): string
{
    return number_format((float) $amount, 0, '.', ',') . ' تومان';
}

function format_irr(float|int $amount): string
{
    return format_irt($amount);
}

function clean_price_input(mixed $val): float
{
    if ($val === null || $val === '') {
        return 0.0;
    }
    $s = fa_to_en_digits((string) $val);
    $s = str_replace([',', ' '], '', $s);
    return (float) $s;
}

function generate_uuid(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, 8);
}

function make_utf8_slug(string $title, int $max = 150): string
{
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', trim($title));
    $slug = trim($slug, '-');
    return mb_substr($slug, 0, $max, 'UTF-8') ?: 'item';
}

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

function format_card_number(?string $card): string
{
    if (!$card) return '—';
    $digits = preg_replace('/[^\d]/', '', fa_to_en_digits($card));
    if (strlen($digits) === 16) {
        return chunk_split($digits, 4, ' ');
    }
    return en_to_fa_digits($card);
}

function json_snapshot(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

function json_unsnapshot(?string $json): array
{
    return $json ? (json_decode($json, true) ?: []) : [];
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
