<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\Exceptions\GeoProviderException;
use App\Services\Geo\Exceptions\RoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Тонкие эндпоинты-обёртки над GeoProvider/RoutingProvider — удобны для фронта,
 * чтобы не светить нашими ключами/User-Agent и кэшировать на бэке.
 * Боевая маршрутизация делается через POST /api/v1/routes (Итерация 3),
 * здесь — только базовые операции.
 */
class GeoController extends Controller
{
    public function __construct(
        private readonly GeoProvider $geo,
        private readonly RoutingProvider $router,
    ) {
    }

    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $point = $this->geo->geocode($data['q']);
        } catch (GeoProviderException $e) {
            return response()->json(['message' => 'Геокодер недоступен.', 'provider' => $this->geo->name()], 503);
        }

        // Возвращаем массив `results` — фронт (web + mobile) ждёт именно такой формат.
        $results = $point
            ? [['label' => $point->label ?? $data['q'], 'lat' => $point->lat, 'lng' => $point->lng]]
            : [];

        return response()->json([
            'provider' => $this->geo->name(),
            'results'  => $results,
            // Совместимость со старыми клиентами:
            'point'    => $point?->toArray(),
        ]);
    }

    /**
     * Конфиг для фронта: JS API ключ Яндекса, провайдер тайлов, тип геокодера.
     * Безопасно публиковать — JS API ключ привязан к доменам в кабинете разработчика.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'geocoder' => config('geo.geocoder.driver'),
            'routing'  => config('geo.routing.driver'),
            'tiles'    => config('geo.tiles.driver'),
            'yandex'   => [
                'js_api_key' => config('geo.tiles.yandex.api_key'),
            ],
        ]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $label = $this->geo->reverse((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'provider' => $this->geo->name(),
            'label' => $label,
        ]);
    }

    public function route(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'array'],
            'from.lat' => ['required', 'numeric', 'between:-90,90'],
            'from.lng' => ['required', 'numeric', 'between:-180,180'],
            'to' => ['required', 'array'],
            'to.lat' => ['required', 'numeric', 'between:-90,90'],
            'to.lng' => ['required', 'numeric', 'between:-180,180'],
            'via' => ['nullable', 'array'],
            'via.*.lat' => ['required_with:via', 'numeric', 'between:-90,90'],
            'via.*.lng' => ['required_with:via', 'numeric', 'between:-180,180'],
        ]);

        $via = collect($data['via'] ?? [])
            ->map(fn (array $p) => new GeoPoint((float) $p['lat'], (float) $p['lng']))
            ->all();

        try {
            $geometry = $this->router->route(
                new GeoPoint((float) $data['from']['lat'], (float) $data['from']['lng']),
                new GeoPoint((float) $data['to']['lat'], (float) $data['to']['lng']),
                $via,
            );
        } catch (RoutingException $e) {
            return response()->json([
                'message' => 'Роутинг недоступен.',
                'provider' => $this->router->name(),
                'reason' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json($geometry->toArray());
    }
}
