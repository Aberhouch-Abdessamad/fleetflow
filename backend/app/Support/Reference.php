<?php

namespace App\Support;

/** Human-friendly, non-sequential reference numbers (e.g. DEL-261124-7KQ2MX). Uniqueness enforced by DB index. */
final class Reference
{
    public static function make(string $prefix): string
    {
        // Unambiguous alphabet (no 0/O/1/I/L).
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $rand = '';
        for ($i = 0; $i < 6; $i++) {
            $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $prefix.'-'.now()->format('ymd').'-'.$rand;
    }

    /** @param callable(string):bool $exists */
    public static function unique(string $prefix, callable $exists): string
    {
        do {
            $ref = self::make($prefix);
        } while ($exists($ref));

        return $ref;
    }
}
