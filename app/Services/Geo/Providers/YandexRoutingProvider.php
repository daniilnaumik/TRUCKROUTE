<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\DTO\RouteGeometry;
use App\Services\Geo\Exceptions\RoutingException;

/**
 * Заглушка под Яндекс.Маршрутизатор. Полноценный API платный и требует доп. согласований
 * для грузового профиля, поэтому реализация под защиту диплома оставлена адаптером:
 * если ключа нет — поднимаем исключение, фабрика автоматически переключится на OSRM.
 */
class YandexRoutingProvider implements RoutingProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 8,
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

    public function route(GeoPoint $from, GeoPoint $to, array $via = []): RouteGeometry
    {
        throw new RoutingException(
            $this->hasKey()
                ? 'Yandex routing adapter is not implemented yet (key present, but adapter is a stub).'
                : 'Yandex routing requires YANDEX_API_KEY and is not configured.'
        );
    }
}
