<?php

namespace App\Services;

use App\Models\ServiceObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Поиск POI (АЗС/стоянок/ночлега/СТО) по гео-параметрам.
 * Использует ST_Distance_Sphere(POINT(lng,lat), POINT(?,?)) с bbox-предфильтром,
 * чтобы индекс (lat,lng) реально работал, а тригонометрия считалась только для близких строк.
 */
class PoiSearchService
{
    /**
     * Поиск в радиусе вокруг точки. Возвращает коллекцию POI с псевдо-атрибутом distance_m.
     *
     * @param array<int, string>|null $types  Фильтр по типу ("АЗС","Стоянка","Ночлег","СТО"). Null = все.
     */
    public function searchAroundPoint(
        float $lat,
        float $lng,
        float $radiusKm,
        ?array $types = null,
        ?string $brand = null,
        ?bool $verified = null,
        int $limit = 50
    ): Collection {
        $radiusKm = min(
            (float) config('geo.defaults.max_radius_km', 200),
            max(0.1, $radiusKm)
        );
        $radiusMeters = $radiusKm * 1000;
        $limit = min((int) config('geo.defaults.max_results', 100), max(1, $limit));

        // Грубая оценка bbox: 1° lat ≈ 111 км; 1° lng ≈ 111 * cos(lat) км.
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / max(1.0, 111.0 * cos(deg2rad($lat)));

        $west = $lng - $lngDelta;
        $east = $lng + $lngDelta;
        $south = $lat - $latDelta;
        $north = $lat + $latDelta;

        return $this->baseQuery($types, $brand, $verified)
            ->withinBbox($west, $south, $east, $north)
            ->select('service_objects.*')
            ->selectRaw(
                'ST_Distance_Sphere(POINT(lng, lat), POINT(?, ?)) AS distance_m',
                [$lng, $lat]
            )
            ->having('distance_m', '<=', $radiusMeters)
            ->orderBy('distance_m')
            ->limit($limit)
            ->get();
    }

    /**
     * Поиск в прямоугольнике (для viewport карты на фронте).
     *
     * @param array<int, string>|null $types
     */
    public function searchInBbox(
        float $west,
        float $south,
        float $east,
        float $north,
        ?array $types = null,
        ?string $brand = null,
        ?bool $verified = null,
        int $limit = 100
    ): Collection {
        $limit = min((int) config('geo.defaults.max_results', 100), max(1, $limit));

        return $this->baseQuery($types, $brand, $verified)
            ->withinBbox($west, $south, $east, $north)
            ->limit($limit)
            ->get();
    }

    /**
     * Поиск POI вдоль полилинии маршрута: для каждой опорной точки берём радиус
     * и объединяем результаты. Дёшево и работает без spatial-индекса; для боевой
     * нагрузки заменим на ST_Distance к LINESTRING.
     *
     * @param array<int, array{lat: float, lng: float}> $polyline
     * @param array<int, string>|null $types
     */
    public function searchAlongRoute(array $polyline, float $corridorKm = 5.0, ?array $types = null, int $limitPerPoint = 15): Collection
    {
        if (empty($polyline)) {
            return new Collection();
        }

        // Прореживаем полилинию — берём каждую N-ю точку, чтобы не делать сотни запросов.
        $step = max(1, (int) ceil(count($polyline) / 20));
        $samples = [];
        foreach ($polyline as $i => $p) {
            if ($i % $step === 0) {
                $samples[] = $p;
            }
        }
        $samples[] = $polyline[array_key_last($polyline)];
        $samples = collect($samples)
            ->unique(fn (array $point) => round((float) $point['lat'], 6).':'.round((float) $point['lng'], 6))
            ->values()
            ->all();

        $merged = new Collection();
        foreach ($samples as $p) {
            $batch = $this->searchAroundPoint(
                lat: (float) $p['lat'],
                lng: (float) $p['lng'],
                radiusKm: $corridorKm,
                types: $types,
                limit: $limitPerPoint,
            );
            foreach ($batch as $row) {
                if (!$merged->contains('id', $row->id)) {
                    $merged->push($row);
                }
            }
        }

        return $merged->values();
    }

    /**
     * @param array<int, string>|null $types
     */
    private function baseQuery(?array $types, ?string $brand, ?bool $verified): Builder
    {
        $q = ServiceObject::query();

        if (!empty($types)) {
            $q->whereIn('type', $types);
        }
        if ($brand !== null && $brand !== '' && $brand !== 'Любые') {
            $q->where('brand', 'like', '%'.$brand.'%');
        }
        if ($verified !== null) {
            $q->where('verified', $verified);
        }

        return $q;
    }
}
