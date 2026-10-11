<?php
declare(strict_types=1);

/**
 * layout_components.php
 * Reusable UI Components: Pagination, Empty States, Badges, Tables
 */

require_once __DIR__ . '/layout_icons.php';

function empty_state(string $title, string $desc = '', string $iconName = 'orders'): string
{
    return '<div class="empty-state" style="text-align:center; padding:40px 20px;">
        <div class="empty-state-icon" style="color:#94a3b8; margin-bottom:12px;">' . icon($iconName, 36) . '</div>
        <h3 style="font-size:1.05rem; color:#334155; margin-bottom:6px;">' . e($title) . '</h3>
        ' . ($desc ? '<p style="color:#64748b; font-size:0.88rem; max-width:400px; margin:0 auto;">' . e($desc) . '</p>' : '') . '
    </div>';
}

function render_status_badge(string $status): string
{
    $map = [
        'submitted' => ['ثبت‌شده', 'badge-amber'],
        'paid' => ['پرداخت‌شده', 'badge-blue'],
        'shipped' => ['ارسال‌شده', 'badge-purple'],
        'completed' => ['تکمیل‌شده', 'badge-emerald'],
        'canceled' => ['لغو‌شده', 'badge-rose'],
        'placed' => ['ثبت اولیه', 'badge-amber'],
        'finalised' => ['نهایی‌شده', 'badge-blue'],
        'open' => ['باز', 'badge-emerald'],
        'closed' => ['بسته‌شده', 'badge-muted'],
    ];

    $item = $map[$status] ?? [$status, 'badge-muted'];
    return '<span class="badge ' . $item[1] . '">' . e($item[0]) . '</span>';
}

function render_pagination(int $currentPage, int $totalPages, string $baseUrl, array $queryParams = []): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $buildUrl = function (int $page) use ($baseUrl, $queryParams): string {
        $params = $queryParams;
        $params['page'] = $page;
        $qs = http_build_query($params);
        return $baseUrl . ($qs ? '?' . $qs : '');
    };

    $fa = function (int $n): string {
        return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);
    };

    $html = '<nav class="pagination-nav" aria-label="ناوبری صفحات" style="display:flex; justify-content:center; align-items:center; gap:6px; margin-top:24px; flex-wrap:wrap;">';

    // Prev Button
    if ($currentPage > 1) {
        $html .= '<a href="' . e($buildUrl($currentPage - 1)) . '" class="page-btn prev-btn" style="padding:6px 14px; border:1px solid var(--border-subtle); background:#fff; border-radius:8px; font-size:0.84rem; font-weight:600; color:var(--ink); display:inline-flex; align-items:center; gap:4px;">' . icon('chevron-right', 14) . ' قبلی</a>';
    } else {
        $html .= '<span class="page-btn disabled" style="padding:6px 14px; border:1px solid var(--border); background:var(--surface-well); border-radius:8px; font-size:0.84rem; color:var(--placeholder); cursor:not-allowed; display:inline-flex; align-items:center; gap:4px;">' . icon('chevron-right', 14) . ' قبلی</span>';
    }

    $pages = [];
    $pages[] = 1;

    $rangeStart = max(2, $currentPage - 2);
    $rangeEnd = min($totalPages - 1, $currentPage + 2);

    if ($rangeStart > 2) {
        $pages[] = '...';
    }

    for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
        $pages[] = $i;
    }

    if ($rangeEnd < $totalPages - 1) {
        $pages[] = '...';
    }

    if ($totalPages > 1) {
        $pages[] = $totalPages;
    }

    foreach ($pages as $p) {
        if ($p === '...') {
            $html .= '<span style="padding:6px 10px; color:var(--placeholder); font-weight:bold;">…</span>';
        } elseif ($p === $currentPage) {
            $html .= '<span class="page-btn active" style="padding:6px 14px; background:var(--primary); color:#fff; border:1px solid var(--primary); border-radius:8px; font-size:0.86rem; font-weight:700; min-width:38px; text-align:center;">' . $fa($p) . '</span>';
        } else {
            $html .= '<a href="' . e($buildUrl((int)$p)) . '" class="page-btn" style="padding:6px 14px; background:#fff; color:var(--ink); border:1px solid var(--border-subtle); border-radius:8px; font-size:0.86rem; font-weight:600; min-width:38px; text-align:center;">' . $fa((int)$p) . '</a>';
        }
    }

    // Next Button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . e($buildUrl($currentPage + 1)) . '" class="page-btn next-btn" style="padding:6px 14px; border:1px solid var(--border-subtle); background:#fff; border-radius:8px; font-size:0.84rem; font-weight:600; color:var(--ink); display:inline-flex; align-items:center; gap:4px;">بعدی ' . icon('chevron-left', 14) . '</a>';
    } else {
        $html .= '<span class="page-btn disabled" style="padding:6px 14px; border:1px solid var(--border); background:var(--surface-well); border-radius:8px; font-size:0.84rem; color:var(--placeholder); cursor:not-allowed; display:inline-flex; align-items:center; gap:4px;">بعدی ' . icon('chevron-left', 14) . '</span>';
    }

    $html .= '</nav>';
    return $html;
}

function card_start(?string $title = null, string $extraClass = '', ?string $actions = null): void
{
    echo '<div class="card ' . e($extraClass) . '">';
    if ($title !== null || $actions !== null) {
        echo '<div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--border); background:#fff;">';
        if ($title !== null) {
            echo '<h3 style="font-size:0.95rem; font-weight:600; margin:0; color:var(--ink);">' . e($title) . '</h3>';
        }
        if ($actions !== null) {
            echo '<div>' . $actions . '</div>';
        }
        echo '</div>';
    }
    echo '<div class="card-body" style="padding:18px 20px;">';
}

function card_end(): void
{
    echo '</div></div>';
}
