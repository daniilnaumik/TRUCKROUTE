<?php

namespace App\Services\Geo\DTO;

/**
 * Результат построения маршрута: дистанция, длительность и полилиния точек.
 * polyline — массив GeoPoint вдоль маршрута, в порядке движения.
 */
final class RouteGeometry
{
    /**
     * @param array<int, GeoPoint> $polyline
     */
    public function __construct(
        public readonly int $distance_m,
        public readonly int $duration_s,
        public readonly array $polyline,
        public readonly ?string $provider = null,
    ) {
    }

    public function distanceKm(): float
    {
        return round($this->distance_m / 1000, 2);
    }

    public function durationMinutes(): int
    {
        return (int) ceil($this->duration_s / 60);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'distance_m' => $this->distance_m,
            'distance_km' => $this->distanceKm(),
            'duration_s' => $this->duration_s,
            'duration_min' => $this->durationMinutes(),
            'polyline' => array_map(fn (GeoPoint $p) => [$p->lat, $p->lng], $this->polyline),
            'provider' => $this->provider,
        ];
    }
}
