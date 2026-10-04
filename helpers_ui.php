<?php
declare(strict_types=1);

/**
 * helpers_ui.php
 * UI presentation helpers, badges, pagination HTML, sorting links, and Persian number words
 */

function order_status_fa(string $status): string
{
    return [
        'submitted' => 'ثبت اولیه (رزرو)',
        'paid' => 'پرداخت شده و تایید شده',
        'shipped' => 'ارسال شده',
        'completed' => 'تحویل و تکمیل شده',
        'canceled' => 'لغو شده',
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


function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($pages, $page));
    $offset = ($page - 1) * $perPage;

    return ['page' => $page, 'pages' => $pages, 'offset' => $offset, 'perPage' => $perPage];
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
