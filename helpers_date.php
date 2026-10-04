<?php
declare(strict_types=1);

/**
 * helpers_date.php
 * Jalali (Solar Hijri) calendar calculation, conversion, parsing, and formatting
 */

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
