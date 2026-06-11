<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\DTO\RouteGeometry;
use App\Services\Geo\Exceptions\RoutingException;
use Illuminate\Support\Facades\Log;

/**
 * Декоратор: пробует основной роутер (OSRM/Яндекс),
 * при недоступности — строит приближённый маршрут по прямой (haversine)
 * с поправочным дорожным коэффициентом. Гарантирует, что маршрут построится офлайн.
 */
class FallbackRoutingProvider implements RoutingProvider
{
    /** Дороги длиннее прямой — типовой коэффициент извилистости. */
    private const ROAD_FACTOR = 1.27;

    /** Средняя скорость для оценки времени, км/ч (грузовик). */
    private const AVG_SPEED_KMH = 75.0;

    /** Точек интерполяции на сегмент — для плавной линии на карте. */
    private const SEGMENT_POINTS = 12;

    public function __construct(private readonly RoutingProvider $inner)
    {
    }

    public function name(): string
    {
        return $this->inner->name().'+haversine';
    }

    public function route(GeoPoint $from, GeoPoint $to, array $via = []): RouteGeometry
    {
        try {
            return $this->inner->route($from, $to, $via);
        } catch (RoutingException $e) {
            Log::info('Routing provider unavailable, using haversine fallback', ['error' => $e->getMessage()]);
            return $this->straightLine($from, $to, $via);
        }
    }

    /**
     * Приближённый маршрут: прямые сегменты между опорными точками,
     * дистанция = сумма haversine × ROAD_FACTOR, время = дистанция / средняя скорость.
     *
     * @param array<int, GeoPoint> $via
     */
    private function straightLine(GeoPoint $from, GeoPoint $to, array $via): RouteGeometry
    {
        $waypoints = array_merge([$from], $via, [$to]);

        $polyline   = [];
        $straightM  = 0.0;

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $a = $waypoints[$i];
            $b = $waypoints[$i + 1];
            $straightM += $this->haversineMeters($a->lat, $a->lng, $b->lat, $b->lng);

            // Интерполируем точки сегмента (без дубля стартовой, кроме первого)
            for ($s = ($i === 0 ? 0 : 1); $s <= self::SEGMENT_POINTS; $s++) {
                $t   = $s / self::SEGMENT_POINTS;
                $lat = $a->lat + ($b->lat - $a->lat) * $t;
                $lng = $a->lng + ($b->lng - $a->lng) * $t;
                $polyline[] = new GeoPoint(lat: $lat, lng: $lng);
            }
        }

        $roadM      = (int) round($straightM * self::ROAD_FACTOR);
        $durationS  = (int) round(($roadM / 1000) / self::AVG_SPEED_KMH * 3600);

        return new RouteGeometry(
            distance_m: $roadM,
            duration_s: $durationS,
            polyline:   $polyline,
            provider:   'haversine',
        );
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }
}
