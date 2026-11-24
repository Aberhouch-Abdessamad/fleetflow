<?php

namespace App\Support;

final class Geo
{
    /** Great-circle distance in km. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @param list<array{0:float,1:float}> $points [lat,lng] */
    public static function pathKm(array $points): float
    {
        $sum = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $sum += self::distanceKm($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }

        return round($sum, 2);
    }

    public static function valid(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180
            && ! ($lat == 0.0 && $lng == 0.0); // null island = device error
    }
}
