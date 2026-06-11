<?php

namespace App\Services;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Models\ServiceObject;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\DTO\RouteGeometry;
use App\Services\Geo\Exceptions\GeoProviderException;
use App\Services\Geo\Exceptions\RoutingException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Оркестратор построения маршрута:
 *   1. Нормализует входные точки (строка → GeoPoint через GeoProvider, либо принимает координаты как есть).
 *   2. Строит реальную полилинию через RoutingProvider (OSRM по умолчанию).
 *   3. Подбирает POI вдоль маршрута (PoiSearchService + резервный legacy-путь по corridor matching).
 *   4. Передаёт всё в существующий RouteCalculator с подменённым distance_km/полилинией.
 *   5. Сохраняет RoutePlan + RouteRecommendation в одной транзакции.
 *
 * Здесь нет HTTP-логики — это позволяет вызвать оркестратор и из контроллера, и из job (recalculate),
 * и из Console-команды (например, для перепланирования по событию).
 */
class RouteBuildService
{
    public function __construct(
        private readonly GeoProvider $geo,
        private readonly RoutingProvider $router,
        private readonly RouteCalculator $calculator,
        private readonly PoiSearchService $poiSearch,
    ) {
    }

    /**
     * Главная точка входа.
     *
     * @param array<string, mixed> $input  валидированный пейлоад StoreRouteRequest
     */
    public function build(User $user, array $input): RoutePlan
    {
        $vehicle = $this->resolveVehicle($user, $input);

        // 1. Нормализация точек: либо строка адреса → геокод, либо {lat,lng}.
        $origin = $this->normalizePoint($input['origin'], 'origin');
        $destination = $this->normalizePoint($input['destination'], 'destination');
        $via = collect($input['via'] ?? [])
            ->map(fn ($p) => $this->normalizePoint($p, 'via'))
            ->all();

        // 2. Реальный маршрут.
        try {
            $geometry = $this->router->route($origin, $destination, $via);
        } catch (RoutingException $e) {
            throw new RuntimeException(
                'Не удалось построить маршрут через провайдера '.$this->router->name().': '.$e->getMessage(),
                503,
                $e,
            );
        }

        // 3. POI вдоль маршрута. Если хоть один найден через polyline-поиск — берём этот результат,
        // иначе откатываемся на старый corridor matching (по highway/km_marker).
        $polylineArray = array_map(
            fn (GeoPoint $p) => ['lat' => $p->lat, 'lng' => $p->lng],
            $geometry->polyline,
        );
        $poiAlong = $this->poiSearch->searchAlongRoute(
            polyline: $polylineArray,
            corridorKm: 10.0,
            types: ['АЗС', 'Стоянка', 'Ночлег', 'СТО'],
            limitPerPoint: 4,
        );
        $serviceObjects = $poiAlong
            ->map(function (ServiceObject $object) use ($geometry) {
                $object->setAttribute(
                    'km_marker',
                    max(1, $this->distanceAlongGeometryKm($geometry, (float) $object->lat, (float) $object->lng)),
                );
                $object->setAttribute(
                    'detour_km',
                    round($this->distanceToGeometryKm($geometry, (float) $object->lat, (float) $object->lng), 2),
                );
                $object->setAttribute('route_geometry_match', true);

                return $object;
            })
            ->sortBy('km_marker')
            ->values();

        // 4. Готовим payload для legacy-калькулятора. distance_km — реальный из OSRM, а не из config.
        $calculation = $this->calculator->calculate(
            $this->buildCalculatorPayload($input, $vehicle, $origin, $destination, $via, $geometry),
            RoadEvent::query()->active()->get(),
            $serviceObjects,
        );

        // 5. Сохранение.
        return DB::transaction(function () use ($user, $vehicle, $input, $origin, $destination, $via, $geometry, $calculation) {
            /** @var RoutePlan $plan */
            $plan = RoutePlan::create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicle?->id,
                'title' => $this->buildTitle($origin, $destination),
                'origin' => $origin->label ?: $this->coordsLabel($origin),
                'origin_point' => $this->pointToArray($origin),
                'destination' => $destination->label ?: $this->coordsLabel($destination),
                'destination_point' => $this->pointToArray($destination),
                'via_point' => $via[0]->label ?? null,   // legacy для Blade
                'via_points' => array_map(fn (GeoPoint $p) => $this->pointToArray($p), $via),
                'start_time' => isset($input['start_time']) ? Carbon::parse($input['start_time']) : null,
                'vehicle_type' => $vehicle?->type ?? $input['vehicle']['type'] ?? 'Тягач+полуприцеп',
                'cargo_type' => 'Вес груза: '.number_format((float) ($input['cargo']['weight_t'] ?? 0), 1, '.', ' ').' т',
                'cargo_weight_t' => $input['cargo']['weight_t'] ?? 0,
                'vehicle_curb_weight_t' => $calculation['vehicle_curb_weight_t'],
                'gross_weight_t' => $calculation['gross_weight_t'],
                'start_fuel_l' => min(
                    (int) ($input['start_fuel_l'] ?? 0),
                    (int) ($vehicle?->tank_capacity_l ?? $input['vehicle']['tank_capacity_l'] ?? 0),
                ),
                'tank_capacity_l' => (int) ($vehicle?->tank_capacity_l ?? $input['vehicle']['tank_capacity_l']),
                'consumption_l_per_100' => $vehicle?->consumption_l_per_100 ?? $input['vehicle']['consumption_l_per_100'],
                'effective_consumption_l_per_100' => $calculation['effective_consumption_l_per_100'],
                'reserve_percent' => (int) ($input['preferences']['reserve_percent'] ?? 15),
                'reserve_l' => $calculation['reserve_l'],
                'cruise_speed_kmh' => (int) ($vehicle?->cruise_speed_kmh ?? $input['vehicle']['cruise_speed_kmh'] ?? 85),
                'planning_mode' => $input['preferences']['planning_mode'] ?? 'Безопасный',
                'distance_km' => $calculation['distance_km'],
                'drive_time_minutes' => $calculation['drive_time_minutes'],
                'arrival_time' => $calculation['arrival_time'],
                'fuel_needed_l' => $calculation['fuel_needed_l'],
                'fuel_cost_rub' => $calculation['fuel_cost_rub'],
                'range_km' => $calculation['range_km'],
                'stops_count' => $calculation['stops_count'],
                'recommendations' => $calculation['recommendations'],
                'image' => $calculation['image'],
                'polyline_json' => json_encode(
                    array_map(fn (GeoPoint $p) => [$p->lat, $p->lng], $geometry->polyline),
                    JSON_UNESCAPED_UNICODE,
                ),
                'routing_provider' => $geometry->provider ?? $this->router->name(),
            ]);

            $selectedPoiIds = collect($input['selected_poi_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $routePoiIds = collect($input['route_poi_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $createdPoiIds = collect();

            foreach ($calculation['recommendation_points'] as $point) {
                $serviceObjectId = (int) ($point['service_object_id'] ?? 0);
                if ($serviceObjectId > 0 && $selectedPoiIds->contains($serviceObjectId)) {
                    $isRouteStop = $routePoiIds->contains($serviceObjectId);
                    $point['type'] = $isRouteStop ? 'route_stop' : 'optional_stop';
                    $point['detour_km'] = $isRouteStop ? 0 : ($point['detour_km'] ?? 0);
                    $point['note'] = $isRouteStop
                        ? 'Обязательный заезд: маршрут построен через эту точку.'
                        : 'Вариант рядом с маршрутом: водитель может заехать по ситуации.';
                }

                $plan->recommendationsList()->create($point);
                if ($serviceObjectId > 0) {
                    $createdPoiIds->push($serviceObjectId);
                }
            }

            foreach ($this->manualPoiRecommendations($input, $geometry, count($calculation['recommendation_points']), $createdPoiIds->all()) as $point) {
                $plan->recommendationsList()->create($point);
            }

            return $plan->fresh(['recommendationsList.serviceObject', 'vehicle']);
        });
    }

    /**
     * Полная пересборка маршрута на основе уже сохранённого RoutePlan
     * (например, после изменения событий или предпочтений).
     */
    public function rebuild(RoutePlan $plan, array $overrides = []): RoutePlan
    {
        $input = [
            'origin' => $plan->origin_point ?: $plan->origin,
            'destination' => $plan->destination_point ?: $plan->destination,
            'via' => $plan->via_points ?: [],
            'start_time' => optional($plan->start_time)->toIso8601String(),
            'start_fuel_l' => $plan->start_fuel_l,
            'preferences' => [
                'reserve_percent' => $plan->reserve_percent,
                'planning_mode' => $plan->planning_mode,
                'continuous_drive_hours' => 4,
                'lodging_type' => 'Стоянка',
                'preferred_fuel_brand' => 'Любые',
                'include_rest_stop' => true,
                'no_toll_roads' => 'Нет',
            ],
            'vehicle_id' => $plan->vehicle_id,
            'cargo' => [
                'weight_t' => (float) $plan->cargo_weight_t,
                'flag' => 'Обычный',
                'requirements' => 'Без особых требований',
            ],
        ];

        // overrides поверх — для частичного пересчёта (например, поменять planning_mode).
        $input = array_replace_recursive($input, $overrides);

        $user = $plan->user;
        if (!$user) {
            throw new RuntimeException('У маршрута отсутствует пользователь — пересчёт невозможен.');
        }

        $newPlan = $this->build($user, $input);

        // Старый план удаляем — пересчёт это явное действие пользователя.
        $plan->delete();

        return $newPlan;
    }

    /**
     * Принимает либо строку адреса ("Москва"), либо ассоциатив [lat, lng, label?].
     * Геокодирование делается только для строк — это экономит RPS Nominatim.
     */
    private function normalizePoint(mixed $value, string $field): GeoPoint
    {
        if (is_array($value)) {
            if (!isset($value['lat'], $value['lng'])) {
                throw new InvalidArgumentException("Поле {$field} должно содержать lat и lng.");
            }
            return new GeoPoint(
                lat: (float) $value['lat'],
                lng: (float) $value['lng'],
                label: $value['label'] ?? $value['address'] ?? null,
            );
        }

        if (is_string($value) && $value !== '') {
            try {
                $point = $this->geo->geocode($value);
            } catch (GeoProviderException $e) {
                throw new RuntimeException(
                    "Геокодер недоступен для {$field}: ".$e->getMessage(),
                    503,
                    $e,
                );
            }
            if (!$point) {
                throw new InvalidArgumentException("Не удалось определить координаты для {$field}: '{$value}'.");
            }
            return $point;
        }

        throw new InvalidArgumentException("Поле {$field} должно быть строкой адреса или объектом {lat,lng}.");
    }

    private function resolveVehicle(User $user, array $input): ?Vehicle
    {
        if (!empty($input['vehicle_id'])) {
            /** @var Vehicle|null $vehicle */
            $vehicle = $user->vehicles()->whereKey($input['vehicle_id'])->first();
            if (!$vehicle) {
                throw new InvalidArgumentException('Указанная фура не найдена среди ваших фур.');
            }
            return $vehicle;
        }

        // Если переданы характеристики, но не сохраняем фуру — пусть будет null,
        // данные подставятся в RoutePlan напрямую из payload.
        return $user->vehicles()->where('is_active', true)->latest()->first();
    }

    /**
     * @param array<int, GeoPoint> $via
     */
    private function buildCalculatorPayload(
        array $input,
        ?Vehicle $vehicle,
        GeoPoint $origin,
        GeoPoint $destination,
        array $via,
        RouteGeometry $geometry,
    ): array {
        $prefs = $input['preferences'] ?? [];
        $vehiclePayload = $input['vehicle'] ?? [];
        $cargo = $input['cargo'] ?? [];

        return [
            // Точки — текстом, чтобы legacy detectCorridors() мог матчить трассы.
            'origin' => $origin->label ?: $this->coordsLabel($origin),
            'destination' => $destination->label ?: $this->coordsLabel($destination),
            'via_point' => $via[0]->label ?? null,

            'start_time' => $input['start_time'] ?? null,
            'preferred_fuel_brand' => $prefs['preferred_fuel_brand'] ?? 'Любые',
            'lodging_type' => $prefs['lodging_type'] ?? 'Стоянка',
            'night_budget_rub' => $prefs['night_budget_rub'] ?? null,
            'include_rest_stop' => $prefs['include_rest_stop'] ?? true,
            'selected_rest_object_id' => $prefs['selected_rest_object_id'] ?? null,
            'continuous_drive_hours' => $prefs['continuous_drive_hours'] ?? 4,

            'vehicle_model' => $vehicle->model ?? $vehiclePayload['model'] ?? 'Свой профиль',
            'vehicle_type' => $vehicle->type ?? $vehiclePayload['type'] ?? 'Тягач+полуприцеп',
            'fuel_type' => $vehicle->fuel_type ?? $vehiclePayload['fuel_type'] ?? 'Дизель',
            'allowed_fuel' => $vehicle->allowed_fuel ?? $vehiclePayload['allowed_fuel'] ?? 'Дизель + AdBlue',
            'tank_capacity_l' => (int) ($vehicle->tank_capacity_l ?? $vehiclePayload['tank_capacity_l']),
            'consumption_l_per_100' => (float) ($vehicle->consumption_l_per_100 ?? $vehiclePayload['consumption_l_per_100']),
            'cruise_speed_kmh' => (int) ($vehicle->cruise_speed_kmh ?? $vehiclePayload['cruise_speed_kmh'] ?? 85),
            'vehicle_curb_weight_t' => (float) ($vehicle->curb_weight_t ?? $vehiclePayload['curb_weight_t'] ?? 15.5),
            'restrictions' => $vehicle->restrictions ?? $vehiclePayload['restrictions'] ?? 'Без опасного груза',

            'cargo_weight_t' => (float) ($cargo['weight_t'] ?? 0),
            'cargo_flag' => $cargo['flag'] ?? 'Обычный',
            'cargo_requirements' => $cargo['requirements'] ?? 'Без особых требований',

            'no_toll_roads' => $prefs['no_toll_roads'] ?? 'Нет',
            'start_fuel_l' => (float) ($input['start_fuel_l'] ?? 0),
            'reserve_percent' => (int) ($prefs['reserve_percent'] ?? 15),
            'planning_mode' => $prefs['planning_mode'] ?? 'Безопасный',

            // Главное: реальный distance из OSRM, а не из config.
            'distance_km' => max(1, (int) round($geometry->distance_m / 1000)),
        ];
    }

    private function buildTitle(GeoPoint $a, GeoPoint $b): string
    {
        return ($a->label ?: $this->coordsLabel($a)).' — '.($b->label ?: $this->coordsLabel($b));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualPoiRecommendations(array $input, RouteGeometry $geometry, int $baseCount, array $existingPoiIds = []): array
    {
        $selectedIds = collect($input['selected_poi_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn (int $id) => in_array($id, $existingPoiIds, true))
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return [];
        }

        $routePoiIds = collect($input['route_poi_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $objects = ServiceObject::whereIn('id', $selectedIds)->get()->keyBy('id');
        $points = [];
        $order = $baseCount + 1;

        foreach ($selectedIds as $id) {
            $object = $objects->get($id);
            if (!$object || $object->lat === null || $object->lng === null) {
                continue;
            }

            $isRouteStop = $routePoiIds->contains($id);
            $points[] = [
                'service_object_id' => $object->id,
                'type' => $isRouteStop ? 'route_stop' : 'optional_stop',
                'order_index' => $order++,
                'distance_from_start_km' => $this->distanceAlongGeometryKm($geometry, (float) $object->lat, (float) $object->lng),
                'detour_km' => $isRouteStop ? 0 : (float) ($object->detour_km ?? 0),
                'eta_at' => null,
                'fuel_before_l' => null,
                'suggested_fuel_l' => null,
                'note' => $isRouteStop
                    ? 'Обязательный заезд: маршрут построен через эту точку.'
                    : 'Вариант рядом с маршрутом: водитель может заехать по ситуации.',
            ];
        }

        return $points;
    }

    private function distanceAlongGeometryKm(RouteGeometry $geometry, float $lat, float $lng): int
    {
        $polyline = $geometry->polyline;
        if (count($polyline) < 2) {
            return 0;
        }

        $distanceKm = 0.0;
        $bestDistanceKm = PHP_FLOAT_MAX;
        $bestRouteKm = 0.0;

        for ($i = 1; $i < count($polyline); $i++) {
            $prev = $polyline[$i - 1];
            $curr = $polyline[$i];
            $segmentKm = $this->haversineKm($prev->lat, $prev->lng, $curr->lat, $curr->lng);
            $toPointKm = $this->haversineKm($curr->lat, $curr->lng, $lat, $lng);

            if ($toPointKm < $bestDistanceKm) {
                $bestDistanceKm = $toPointKm;
                $bestRouteKm = $distanceKm + $segmentKm;
            }

            $distanceKm += $segmentKm;
        }

        return max(0, (int) round($bestRouteKm));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function distanceToGeometryKm(RouteGeometry $geometry, float $lat, float $lng): float
    {
        if ($geometry->polyline === []) {
            return PHP_FLOAT_MAX;
        }

        return collect($geometry->polyline)
            ->map(fn (GeoPoint $point) => $this->haversineKm($point->lat, $point->lng, $lat, $lng))
            ->min();
    }

    private function coordsLabel(GeoPoint $p): string
    {
        return sprintf('%.4f, %.4f', $p->lat, $p->lng);
    }

    /**
     * @return array{lat: float, lng: float, label: string|null}
     */
    private function pointToArray(GeoPoint $p): array
    {
        return ['lat' => $p->lat, 'lng' => $p->lng, 'label' => $p->label];
    }
}
