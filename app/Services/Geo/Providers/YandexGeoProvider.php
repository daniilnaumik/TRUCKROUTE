<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Геокодер Яндекс.Карт. Требует API-ключ; при его отсутствии все методы возвращают null
 * и логируют предупреждение — это разрешает graceful fallback в фабрике.
 */
class YandexGeoProvider implements GeoProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $language = 'ru_RU',
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function name(): string
    {
        return 'yandex';
    }

    public function hasKey(): bool
    {
        return is_string($this->apiKey) && $this->apiKey !== '';
    }

    public function geocode(string $address): ?GeoPoint
    {
        if (!$this->hasKey()) {
            return null;
        }
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        return Cache::remember(
            'geo:yandex:fwd:'.md5($address.'|'.$this->language),
            now()->addHour(),
            function () use ($address): ?GeoPoint {
                try {
                    $response = Http::timeout($this->timeoutSeconds)->get($this->baseUrl, [
                        'apikey' => $this->apiKey,
                        'geocode' => $address,
                        'format' => 'json',
                        'lang' => $this->language,
                        'results' => 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Yandex geocode HTTP error', ['error' => $e->getMessage()]);
                    throw new GeoProviderException('Yandex geocoder unavailable: '.$e->getMessage(), 0, $e);
                }

                if (!$response->ok()) {
                    return null;
                }

                $featureMembers = $response->json('response.GeoObjectCollection.featureMember', []);
                if (empty($featureMembers)) {
                    return null;
                }
                $point = $featureMembers[0]['GeoObject']['Point']['pos'] ?? null;
                $label = $featureMembers[0]['GeoObject']['metaDataProperty']['GeocoderMetaData']['text'] ?? null;
                if (!is_string($point)) {
                    return null;
                }
                // Яндекс возвращает 'lng lat' через пробел.
                [$lng, $lat] = array_map('floatval', explode(' ', $point) + [0.0, 0.0]);
                return new GeoPoint(lat: $lat, lng: $lng, label: $label);
            }
        );
    }

    public function reverse(float $lat, float $lng): ?string
    {
        if (!$this->hasKey()) {
            return null;
        }
        return Cache::remember(
            'geo:yandex:rev:'.round($lat, 5).':'.round($lng, 5).':'.$this->language,
            now()->addDay(),
            function () use ($lat, $lng): ?string {
                try {
                    $response = Http::timeout($this->timeoutSeconds)->get($this->baseUrl, [
                        'apikey' => $this->apiKey,
                        // Яндексу — lng,lat через запятую.
                        'geocode' => $lng.','.$lat,
                        'format' => 'json',
                        'lang' => $this->language,
                        'results' => 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Yandex reverse HTTP error', ['error' => $e->getMessage()]);
                    return null;
                }
                if (!$response->ok()) {
                    return null;
                }
                $featureMembers = $response->json('response.GeoObjectCollection.featureMember', []);
                return $featureMembers[0]['GeoObject']['metaDataProperty']['GeocoderMetaData']['text'] ?? null;
            }
        );
    }
}
