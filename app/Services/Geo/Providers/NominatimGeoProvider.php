<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Геокодер на базе OpenStreetMap Nominatim. Бесплатный, без ключа.
 * Требование OSM: содержательный User-Agent + не более 1 RPS, поэтому
 * на час кладём результаты в кэш.
 */
class NominatimGeoProvider implements GeoProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly string $language = 'ru',
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function name(): string
    {
        return 'nominatim';
    }

    public function geocode(string $address): ?GeoPoint
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        return Cache::remember(
            'geo:nominatim:fwd:'.md5($address.'|'.$this->language),
            now()->addHour(),
            function () use ($address): ?GeoPoint {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Accept-Language' => $this->language,
                    ])
                        ->timeout($this->timeoutSeconds)
                        ->get(rtrim($this->baseUrl, '/').'/search', [
                            'q' => $address,
                            'format' => 'jsonv2',
                            'limit' => 1,
                            'addressdetails' => 0,
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('Nominatim geocode HTTP error', ['error' => $e->getMessage()]);
                    throw new GeoProviderException('Geocoder is unavailable: '.$e->getMessage(), 0, $e);
                }

                if (!$response->ok()) {
                    return null;
                }

                $first = $response->json(0);
                if (!$first || !isset($first['lat'], $first['lon'])) {
                    return null;
                }

                return new GeoPoint(
                    lat: (float) $first['lat'],
                    lng: (float) $first['lon'],
                    label: $first['display_name'] ?? null,
                );
            }
        );
    }

    public function reverse(float $lat, float $lng): ?string
    {
        return Cache::remember(
            'geo:nominatim:rev:'.round($lat, 5).':'.round($lng, 5).':'.$this->language,
            now()->addDay(),
            function () use ($lat, $lng): ?string {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Accept-Language' => $this->language,
                    ])
                        ->timeout($this->timeoutSeconds)
                        ->get(rtrim($this->baseUrl, '/').'/reverse', [
                            'lat' => $lat,
                            'lon' => $lng,
                            'format' => 'jsonv2',
                            'zoom' => 14,
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('Nominatim reverse HTTP error', ['error' => $e->getMessage()]);
                    return null;
                }

                if (!$response->ok()) {
                    return null;
                }

                $data = $response->json();
                return $data['display_name'] ?? null;
            }
        );
    }
}
