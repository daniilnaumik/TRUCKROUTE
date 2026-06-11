<?php

namespace App\Support;

/**
 * Расстояние "по большому кругу" между двумя точками. Чистая математика без БД,
 * чтобы дешёво матчить event ↔ polyline и event ↔ event для дедупликации.
 */
final class Haversine
{
    public const EARTH_RADIUS_M = 6_371_000.0;

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latRad1 = deg2rad($lat1);
        $latRad2 = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($latRad1) * cos($latRad2) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
