<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\DTO\RouteGeometry;
use App\Services\Geo\Exceptions\RoutingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Маршрутизация через OSRM (Open Source Routing Machine).
 * По умолчанию использует публичный сервер; для нагрузки выше демо нужен self-hosted.
 */
class OsrmRoutingProvider implements RoutingProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $profile = 'driving',
        private readonly int $timeoutSeconds = 8,
    ) {
    }

    public function name(): string
    {
        return 'osrm';
    }

    public function route(GeoPoint $from, GeoPoint $to, array $via = []): RouteGeometry
    {
        $points = array_merge([$from], $via, [$to]);

        // OSRM формат: /route/v1/{profile}/{lng,lat;lng,lat;...}
        $coordsString = collect($points)
            ->map(fn (GeoPoint $p) => $p->lng.','.$p->lat)
            ->implode(';');

        $url = rtrim($this->baseUrl, '/')."/route/v1/{$this->profile}/{$coordsString}";

        $cacheKey = 'geo:osrm:'.md5($url);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($url, $points): RouteGeometry {
            try {
                $response = Http::timeout($this->timeoutSeconds)->get($url, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                ]);
            } catch (\Throwable $e) {
                Log::warning('OSRM HTTP error', ['error' => $e->getMessage()]);
                throw new RoutingException('Routing provider unavailable: '.$e->getMessage(), 0, $e);
            }

            if (!$response->ok()) {
                throw new RoutingException('OSRM responded with HTTP '.$response->status());
            }

            $data = $response->json();
            if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'][0])) {
                throw new RoutingException('OSRM has not built a route: '.($data['code'] ?? 'unknown'));
            }

            $route = $data['routes'][0];
            $coords = $route['geometry']['coordinates'] ?? [];

            // GeoJSON отдаёт [lng, lat] — конвертим в наши GeoPoint.
            $polyline = array_map(
                fn (array $coord) => new GeoPoint(lat: (float) $coord[1], lng: (float) $coord[0]),
                $coords
            );

            // Если полилиния по какой-то причине пустая — хотя бы вернём опорные точки.
            if (empty($polyline)) {
                $polyline = $points;
            }

            return new RouteGeometry(
                distance_m: (int) round($route['distance'] ?? 0),
                duration_s: (int) round($route['duration'] ?? 0),
                polyline: $polyline,
                provider: 'osrm',
            );
        });
    }
}
