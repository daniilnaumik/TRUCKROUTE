<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\Exceptions\GeoProviderException;
use App\Services\Geo\LocalGazetteer;
use Illuminate\Support\Facades\Log;

/**
 * Декоратор: пробует основной геокодер (Nominatim/Яндекс),
 * при недоступности или пустом результате — ищет в офлайн-справочнике городов РФ.
 * Гарантирует, что построение маршрута не падает при отсутствии интернета.
 */
class FallbackGeoProvider implements GeoProvider
{
    public function __construct(
        private readonly GeoProvider $inner,
        private readonly LocalGazetteer $gazetteer,
    ) {
    }

    public function name(): string
    {
        return $this->inner->name().'+local';
    }

    public function geocode(string $address): ?GeoPoint
    {
        try {
            $point = $this->inner->geocode($address);
            if ($point) {
                return $point;
            }
        } catch (GeoProviderException $e) {
            Log::info('Geocoder unavailable, using local gazetteer', ['q' => $address]);
        }

        // Fallback — локальный справочник
        return $this->gazetteer->lookup($address);
    }

    public function reverse(float $lat, float $lng): ?string
    {
        try {
            $label = $this->inner->reverse($lat, $lng);
            if ($label) {
                return $label;
            }
        } catch (\Throwable $e) {
            // ignore — отдаём координаты как подпись
        }

        return sprintf('%.4f, %.4f', $lat, $lng);
    }
}
