<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RouteCalculator
{
    private const FUEL_DENSITY_T_PER_L = 0.00084;

    public function calculate(array $data, Collection $events, Collection $objects): array
    {
        $distanceKm = (int) $data['distance_km'];
        $tankCapacity = (float) $data['tank_capacity_l'];
        $startFuel = min((float) $data['start_fuel_l'], $tankCapacity);
        $baseConsumption = (float) $data['consumption_l_per_100'];
        $weightProfile = $this->weightProfile($data, $startFuel);
        $effectiveConsumption = $this->effectiveConsumption($baseConsumption, $data, $weightProfile);
        $reservePercent = (float) $data['reserve_percent'];
        $speed = (int) $data['cruise_speed_kmh'];
        $continuousDriveHours = (float) ($data['continuous_drive_hours'] ?? 4);
        $startAt = !empty($data['start_time']) ? Carbon::parse($data['start_time']) : now();

        $corridors = $this->detectCorridors($data);
        $routeObjects = $this->routeObjects($objects, $corridors, $distanceKm);
        $fuelObjects = $this->fuelObjects($routeObjects, $data['preferred_fuel_brand'] ?? 'Любые');

        $reserveLiters = round($tankCapacity * ($reservePercent / 100), 2);
        $litersPerKm = $effectiveConsumption / 100;
        $rangeKm = (int) floor(max(0, $startFuel - $reserveLiters) / $litersPerKm);
        $fullTankRangeKm = (int) floor(max(0, $tankCapacity - $reserveLiters) / $litersPerKm);
        $recommendedLegKm = max(1, (int) floor($fullTankRangeKm * $this->modeFactor($data['planning_mode'])));

        $fuelNeeded = round($distanceKm * $litersPerKm, 2);
        $fuelPrice = $this->averageFuelPrice($fuelObjects);
        $fuelCost = $fuelPrice ? round($fuelNeeded * $fuelPrice, 2) : null;

        $movementMinutes = (int) ceil(($distanceKm / $speed) * 60);
        $matchedEvents = $this->matchEvents($data, $events, $corridors);
        $eventDelayMinutes = min(180, (int) $matchedEvents->sum(fn ($event) => (int) $event->delay_minutes));
        $includeRestStop = filter_var($data['include_rest_stop'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $fuelPoints = $this->buildFuelPoints(
            $fuelObjects,
            $distanceKm,
            $startFuel,
            $tankCapacity,
            $reserveLiters,
            $litersPerKm,
            $recommendedLegKm,
            $speed,
            $startAt
        );

        $restPoints = [];

        if ($includeRestStop) {
            $restPoints = !empty($data['selected_rest_object_id'])
                ? $this->buildSelectedRestPoint($routeObjects, (int) $data['selected_rest_object_id'], $speed, $startAt)
                : $this->buildRestPoints(
                    $routeObjects,
                    $distanceKm,
                    $speed,
                    $continuousDriveHours,
                    $startAt,
                    collect($fuelPoints)->pluck('service_object_id')->filter()->all()
                );
        }

        $shortRestMinutes = count($restPoints) * 20;
        $fuelStopMinutes = count($fuelPoints) * 15;
        $nightRequired = $this->needsNightStop(
            $startAt,
            $movementMinutes + $eventDelayMinutes + $shortRestMinutes + $fuelStopMinutes
        );
        $nightPoint = $includeRestStop && $nightRequired
            ? $this->buildNightPoint($routeObjects, $distanceKm, $speed, $startAt, $data['lodging_type'] ?? 'Стоянка')
            : null;
        $nightRestMinutes = $nightPoint ? 480 : 0;

        $points = collect($fuelPoints)
            ->merge($restPoints)
            ->when($nightPoint, fn (Collection $items) => $items->push($nightPoint))
            ->sortBy('distance_from_start_km')
            ->values()
            ->map(function (array $point, int $index) {
                $point['order_index'] = $index + 1;

                return $point;
            })
            ->all();

        $totalTripMinutes = $movementMinutes + $eventDelayMinutes + $shortRestMinutes + $fuelStopMinutes + $nightRestMinutes;
        $arrivalAt = $startAt->copy()->addMinutes($totalTripMinutes);

        return [
            'distance_km' => $distanceKm,
            'drive_time_minutes' => $totalTripMinutes,
            'arrival_time' => $arrivalAt,
            'fuel_needed_l' => $fuelNeeded,
            'fuel_cost_rub' => $fuelCost,
            'range_km' => $rangeKm,
            'reserve_l' => $reserveLiters,
            'effective_consumption_l_per_100' => $effectiveConsumption,
            'vehicle_curb_weight_t' => $weightProfile['vehicle_curb_weight_t'],
            'gross_weight_t' => $weightProfile['gross_weight_t'],
            'stops_count' => count($points),
            'recommendations' => $this->buildRecommendations(
                $data,
                collect($points),
                $matchedEvents,
                $weightProfile,
                $reserveLiters,
                $rangeKm,
                $fullTankRangeKm,
                $recommendedLegKm,
                $continuousDriveHours,
                $movementMinutes,
                $eventDelayMinutes,
                $fuelNeeded,
                $fuelCost
            ),
            'recommendation_points' => $points,
            'image' => $this->selectImage($data),
        ];
    }

    private function effectiveConsumption(float $baseConsumption, array $data, ?array $weightProfile = null): float
    {
        $multiplier = 1.0;
        $vehicleType = Str::lower((string) ($data['vehicle_type'] ?? ''));
        $cargoFlag = Str::lower((string) ($data['cargo_flag'] ?? ''));
        $requirements = Str::lower((string) ($data['cargo_requirements'] ?? ''));
        $weightProfile ??= $this->weightProfile($data);

        if (Str::contains($vehicleType, ['фургон', 'одиночка'])) {
            $multiplier -= 0.06;
        }

        if (Str::contains($vehicleType, ['реф', 'рефрижератор'])) {
            $multiplier += 0.08;
        }

        if (Str::contains($vehicleType, ['цистерна'])) {
            $multiplier += 0.05;
        }

        if (Str::contains($requirements, ['температур'])) {
            $multiplier += 0.05;
        }

        if (Str::contains($cargoFlag, ['опасный'])) {
            $multiplier += 0.04;
        }

        if (Str::contains($cargoFlag, ['негабарит'])) {
            $multiplier += 0.07;
        }

        if (($data['no_toll_roads'] ?? 'Нет') === 'Да') {
            $multiplier += 0.03;
        }

        $payloadWeight = $weightProfile['cargo_weight_t'] + $weightProfile['fuel_weight_t'];
        $weightMultiplier = 1 + min(0.35, $payloadWeight * 0.012);

        return round($baseConsumption * $multiplier * $weightMultiplier, 2);
    }

    private function weightProfile(array $data, ?float $startFuel = null): array
    {
        $vehicleCurbWeight = max(1.0, (float) ($data['vehicle_curb_weight_t'] ?? $this->defaultCurbWeight($data['vehicle_type'] ?? null)));
        $cargoWeight = max(0.0, (float) ($data['cargo_weight_t'] ?? 0));
        $fuelLiters = max(0.0, $startFuel ?? (float) ($data['start_fuel_l'] ?? 0));
        $fuelWeight = $fuelLiters * self::FUEL_DENSITY_T_PER_L;

        return [
            'vehicle_curb_weight_t' => round($vehicleCurbWeight, 2),
            'cargo_weight_t' => round($cargoWeight, 2),
            'fuel_weight_t' => round($fuelWeight, 2),
            'gross_weight_t' => round($vehicleCurbWeight + $cargoWeight + $fuelWeight, 2),
        ];
    }

    private function defaultCurbWeight(?string $type): float
    {
        $type = Str::lower((string) $type);

        return match (true) {
            Str::contains($type, 'фургон') => 7.5,
            Str::contains($type, 'одиночка') => 12.0,
            Str::contains($type, ['реф', 'рефрижератор']) => 17.0,
            Str::contains($type, 'цистерна') => 18.0,
            default => 15.5,
        };
    }

    private function routeObjects(Collection $objects, array $corridors, int $distanceKm): Collection
    {
        return $objects
            ->filter(fn ($object) => $object->km_marker !== null)
            ->filter(function ($object) use ($corridors) {
                if ((bool) ($object->route_geometry_match ?? false)) {
                    return true;
                }

                if ($corridors === []) {
                    return true;
                }

                return in_array((string) $object->highway, $corridors, true);
            })
            ->filter(fn ($object) => (int) $object->km_marker > 0 && (int) $object->km_marker <= $distanceKm)
            ->sortBy('km_marker')
            ->values();
    }

    private function fuelObjects(Collection $routeObjects, string $preferredBrand): Collection
    {
        $fuelObjects = $routeObjects->filter(fn ($object) => $object->type === 'АЗС')->values();

        if ($preferredBrand === 'Любые') {
            return $fuelObjects;
        }

        $preferred = $fuelObjects
            ->filter(fn ($object) => Str::contains(Str::lower((string) $object->brand), Str::lower($preferredBrand)))
            ->values();

        return $preferred->isNotEmpty() ? $preferred : $fuelObjects;
    }

    private function averageFuelPrice(Collection $fuelObjects): ?float
    {
        $prices = $fuelObjects->pluck('fuel_price')->filter();

        if ($prices->isEmpty()) {
            return null;
        }

        return round((float) $prices->avg(), 2);
    }

    private function buildFuelPoints(
        Collection $fuelObjects,
        int $distanceKm,
        float $startFuel,
        float $tankCapacity,
        float $reserveLiters,
        float $litersPerKm,
        int $recommendedLegKm,
        int $speed,
        Carbon $startAt
    ): array {
        if ($fuelObjects->isEmpty()) {
            return [];
        }

        $points = [];
        $currentKm = 0;
        $fuel = $startFuel;

        while ($currentKm < $distanceKm) {
            $fuelToFinish = ($distanceKm - $currentKm) * $litersPerKm;

            if ($fuel - $fuelToFinish >= $reserveLiters) {
                break;
            }

            $maxReach = $currentKm + (int) floor(max(0, $fuel - $reserveLiters) / $litersPerKm);

            if ($maxReach <= $currentKm + 10) {
                break;
            }

            $targetKm = min($distanceKm, $currentKm + $recommendedLegKm, max($currentKm + 1, $maxReach - 30));
            $station = $this->nearestObject($fuelObjects, $targetKm, $currentKm + 20, $maxReach);

            if (!$station) {
                break;
            }

            $stationKm = (int) $station->km_marker;
            $fuelBefore = max(0, $fuel - (($stationKm - $currentKm) * $litersPerKm));
            $suggestedFuel = max(0, $tankCapacity - $fuelBefore);

            $points[] = $this->pointData(
                'АЗС',
                $station,
                $stationKm,
                $speed,
                $startAt,
                $fuelBefore,
                $suggestedFuel,
                'Заправка до полного бака. Остаток перед точкой не должен быть ниже резерва.'
            );

            $currentKm = $stationKm;
            $fuel = $tankCapacity;
        }

        if ($points === []) {
            $control = $this->nearestObject($fuelObjects, (int) floor($distanceKm * 0.6), 1, $distanceKm);

            if ($control) {
                $controlKm = (int) $control->km_marker;
                $fuelBefore = max(0, $startFuel - ($controlKm * $litersPerKm));

                $points[] = $this->pointData(
                    'АЗС',
                    $control,
                    $controlKm,
                    $speed,
                    $startAt,
                    $fuelBefore,
                    null,
                    'Контрольная АЗС на маршруте. Дозаправка не обязательна, но точка подходит для резерва.'
                );
            }
        }

        return $points;
    }

    private function buildRestPoints(
        Collection $routeObjects,
        int $distanceKm,
        int $speed,
        float $continuousDriveHours,
        Carbon $startAt,
        array $usedObjectIds
    ): array {
        $restObjects = $routeObjects
            ->filter(fn ($object) => in_array($object->type, ['Стоянка', 'Ночлег', 'АЗС'], true))
            ->filter(fn ($object) => !in_array($object->id, $usedObjectIds, true))
            ->values();

        if ($restObjects->isEmpty()) {
            return [];
        }

        $points = [];
        $intervalKm = max(80, (int) floor($speed * $continuousDriveHours));
        $targetKm = $intervalKm;

        while ($targetKm < $distanceKm - 60) {
            $rest = $this->nearestObject($restObjects, $targetKm, max(1, $targetKm - 70), min($distanceKm, $targetKm + 80));

            if ($rest && !in_array($rest->id, array_column($points, 'service_object_id'), true)) {
                $points[] = $this->pointData(
                    'Отдых',
                    $rest,
                    (int) $rest->km_marker,
                    $speed,
                    $startAt,
                    null,
                    null,
                    'Короткий отдых 20 минут по лимиту непрерывного движения.'
                );
            }

            $targetKm += $intervalKm;
        }

        return $points;
    }

    private function buildSelectedRestPoint(Collection $routeObjects, int $selectedObjectId, int $speed, Carbon $startAt): array
    {
        $rest = $routeObjects->firstWhere('id', $selectedObjectId);

        if (!$rest) {
            return [];
        }

        return [
            $this->pointData(
                'Отдых',
                $rest,
                (int) $rest->km_marker,
                $speed,
                $startAt,
                null,
                null,
                'Остановка выбрана водителем перед построением маршрута.'
            ),
        ];
    }

    private function buildNightPoint(Collection $routeObjects, int $distanceKm, int $speed, Carbon $startAt, string $lodgingType): ?array
    {
        $nightObjects = $routeObjects
            ->filter(function ($object) use ($lodgingType) {
                if ($lodgingType === 'Стоянка') {
                    return in_array($object->type, ['Стоянка', 'Ночлег'], true);
                }

                return $object->type === 'Ночлег';
            })
            ->values();

        if ($nightObjects->isEmpty()) {
            return null;
        }

        $targetKm = min($distanceKm - 20, max(120, (int) floor($distanceKm * 0.78)));
        $night = $this->nearestObject($nightObjects, $targetKm, 1, $distanceKm);

        if (!$night) {
            return null;
        }

        return $this->pointData(
            'Ночлег',
            $night,
            (int) $night->km_marker,
            $speed,
            $startAt,
            null,
            null,
            'Ночная остановка 8 часов из-за длинного рейса или позднего времени прибытия.'
        );
    }

    private function nearestObject(Collection $objects, int $targetKm, int $minKm, int $maxKm): ?object
    {
        return $objects
            ->filter(fn ($object) => (int) $object->km_marker >= $minKm && (int) $object->km_marker <= $maxKm)
            ->sortBy(fn ($object) => abs((int) $object->km_marker - $targetKm) + ((float) $object->detour_km * 10))
            ->first();
    }

    private function pointData(
        string $type,
        object $object,
        int $distanceKm,
        int $speed,
        Carbon $startAt,
        ?float $fuelBefore,
        ?float $suggestedFuel,
        string $note
    ): array {
        return [
            'service_object_id' => $object->id,
            'type' => $type,
            'order_index' => 0,
            'distance_from_start_km' => $distanceKm,
            'detour_km' => (float) $object->detour_km,
            'eta_at' => $startAt->copy()->addMinutes((int) ceil(($distanceKm / $speed) * 60)),
            'fuel_before_l' => $fuelBefore !== null ? round($fuelBefore, 2) : null,
            'suggested_fuel_l' => $suggestedFuel !== null ? round($suggestedFuel, 2) : null,
            'note' => $note,
        ];
    }

    private function needsNightStop(Carbon $startAt, int $tripMinutesBeforeNight): bool
    {
        if ($tripMinutesBeforeNight > 600) {
            return true;
        }

        $arrivalHour = (int) $startAt->copy()->addMinutes($tripMinutesBeforeNight)->format('G');

        return $arrivalHour >= 22 || $arrivalHour < 6;
    }

    private function modeFactor(string $mode): float
    {
        return Str::contains(Str::lower($mode), 'эконом') ? 0.97 : 0.85;
    }

    private function matchEvents(array $data, Collection $events, array $corridors): Collection
    {
        if ($corridors === []) {
            $corridors = $this->detectCorridors($data);
        }

        if ($corridors === []) {
            return collect();
        }

        return $events
            ->filter(fn ($event) => in_array($event->status, ['active', 'checking'], true))
            ->filter(function ($event) use ($corridors) {
                $highway = Str::lower((string) $event->highway);

                foreach ($corridors as $corridor) {
                    $corridor = Str::lower($corridor);

                    if (Str::contains($highway, $corridor) || Str::contains($corridor, $highway)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function detectCorridors(array $data): array
    {
        $routeText = Str::lower(implode(' ', [
            $data['origin'] ?? '',
            $data['destination'] ?? '',
            $data['via_point'] ?? '',
        ]));

        $rules = [
            'М-4 Дон' => ['москва', 'воронеж', 'ростов', 'краснодар', 'дон'],
            'М-7 Волга' => ['казан', 'самар', 'ульяновск', 'волга'],
            'Р-22 Каспий' => ['тамбов', 'волгоград', 'каспий'],
            'А-108' => ['а-108', 'северный обход'],
        ];

        $corridors = [];

        foreach ($rules as $highway => $keywords) {
            if (Str::contains($routeText, $keywords)) {
                $corridors[] = $highway;
            }
        }

        return array_values(array_unique($corridors));
    }

    private function buildRecommendations(
        array $data,
        Collection $points,
        Collection $matchedEvents,
        array $weightProfile,
        float $reserveLiters,
        int $rangeKm,
        int $fullTankRangeKm,
        int $recommendedLegKm,
        float $continuousDriveHours,
        int $movementMinutes,
        int $eventDelayMinutes,
        float $fuelNeeded,
        ?float $fuelCost
    ): string {
        $lines = [];
        $priceText = $fuelCost ? ' Примерная стоимость топлива: ' . number_format($fuelCost, 0, '.', ' ') . ' руб.' : '';

        if ((float) $data['start_fuel_l'] > (float) $data['tank_capacity_l']) {
            $lines[] = 'Стартовое топливо больше объема бака, поэтому в расчете использован полный бак.';
        }

        $lines[] = sprintf(
            'Масса: фура %.1f т + груз %.1f т + топливо %.1f т = расчетная масса %.1f т. Чем тяжелее рейс, тем выше расчетный расход.',
            $weightProfile['vehicle_curb_weight_t'],
            $weightProfile['cargo_weight_t'],
            $weightProfile['fuel_weight_t'],
            $weightProfile['gross_weight_t']
        );

        $lines[] = sprintf(
            'Топливо: расчетный расход %.2f л / 100 км, на %d км потребуется %.1f л.%s',
            $this->effectiveConsumption((float) $data['consumption_l_per_100'], $data, $weightProfile),
            (int) $data['distance_km'],
            $fuelNeeded,
            $priceText
        );

        $lines[] = sprintf(
            'Резерв: %.0f л. Запас хода на старте до резерва: %d км, запас полного бака до резерва: %d км.',
            $reserveLiters,
            $rangeKm,
            $fullTankRangeKm
        );

        $lines[] = sprintf(
            'План: безопасный интервал между заправками до %d км, короткий отдых каждые %.1f ч.',
            $recommendedLegKm,
            $continuousDriveHours
        );

        if ($points->isNotEmpty()) {
            $lines[] = 'Подобрано точек по маршруту: ' . $points->count() . '. Они показаны ниже с километром, ETA и остатком топлива.';
        } else {
            $lines[] = 'В базе пока нет подходящих точек для этой трассы, поэтому нужен ручной контроль остановок.';
        }

        $lines[] = 'Чистое движение: ' . $this->formatMinutes($movementMinutes) . '.';

        if ($eventDelayMinutes > 0) {
            $events = $matchedEvents
                ->map(fn ($event) => $event->title . ' (' . $event->location . ')')
                ->implode('; ');
            $lines[] = sprintf('Дорожные события: учтена задержка до %d мин. %s.', $eventDelayMinutes, $events);
        } else {
            $lines[] = 'Дорожные события: критичных совпадений с маршрутом в базе сейчас нет.';
        }

        return implode("\n", $lines);
    }

    private function formatMinutes(int $minutes): string
    {
        return intdiv($minutes, 60) . ' ч ' . ($minutes % 60) . ' мин';
    }

    private function selectImage(array $data): string
    {
        $routeText = Str::lower(($data['origin'] ?? '') . ' ' . ($data['destination'] ?? '') . ' ' . ($data['via_point'] ?? ''));

        if (Str::contains($routeText, ['ростов', 'краснодар'])) {
            return 'road-sunset-long.jpg';
        }

        if (Str::contains($routeText, ['казан', 'самар'])) {
            return 'road-sunset-low.jpg';
        }

        if (Str::contains($routeText, ['воронеж', 'москва'])) {
            return 'road-green-forest.jpg';
        }

        return 'road-dark-forest.jpg';
    }
}
