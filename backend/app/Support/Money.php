<?php

namespace App\Support;

/**
 * Exact decimal money arithmetic on integer minor units (no floats, no bcmath dependency).
 * All amounts are DECIMAL(12,2) strings at the boundaries.
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        $s = is_string($amount) ? trim($amount) : number_format((float) $amount, 4, '.', '');
        $negative = str_starts_with($s, '-');
        $s = ltrim($s, '-+');
        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '0');
        $frac = str_pad($frac, 3, '0');
        $cents = ((int) $int) * 100 + (int) substr($frac, 0, 2);
        if ((int) $frac[2] >= 5) {
            $cents++; // half-up on the third decimal
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function add(string|int|float|null ...$amounts): string
    {
        return self::fromCents(array_sum(array_map(self::toCents(...), $amounts)));
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    /** amount × quantity, where quantity may have up to 3 decimals. Rounded half-up to cents. */
    public static function mul(string|int|float $amount, string|int|float $quantity): string
    {
        $a = self::scaled($amount, 4);   // amount × 10^4
        $q = self::scaled($quantity, 3); // quantity × 10^3
        $p = $a * $q;                    // × 10^7 → cents = p / 10^5
        $sign = $p < 0 ? -1 : 1;
        $p = abs($p);
        $cents = intdiv($p, 100000) + (($p % 100000) >= 50000 ? 1 : 0);

        return self::fromCents($sign * $cents);
    }

    /** Exact fixed-point integer of a decimal string with $places decimals (truncating beyond). */
    private static function scaled(string|int|float $v, int $places): int
    {
        $s = is_string($v) ? trim($v) : number_format((float) $v, $places, '.', '');
        $neg = str_starts_with($s, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($s, '-+'), 2), 2, '');
        $n = ((int) $int) * (10 ** $places) + (int) str_pad(substr($frac, 0, $places), $places, '0');

        return $neg ? -$n : $n;
    }

    /** amount × rate% (rate like "20.00") rounded half-up. */
    public static function percent(string|int|float $amount, string|int|float $rate): string
    {
        $r = (int) round(((float) $rate) * 100); // basis points ×1
        $v = self::toCents($amount) * $r; // cents × 10000

        return self::fromCents((int) round($v / 10000));
    }

    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }
}
