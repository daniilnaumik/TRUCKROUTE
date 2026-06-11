<?php

namespace App\Services\Events;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Support\Haversine;

/**
 * Проверяет, попадает ли событие в коридор маршрута.
 * Считаем минимальное расстояние от точки события до сэмплированной полилинии
 * (каждая N-я точка для скорости — на дальнобойных трассах это ~1 км между сэмплами,
 * чего более чем достаточно для коридора 5 км).
 */
class RouteEventMatcher
{
    public function distanceToRouteMeters(RoutePlan $plan, RoadEvent $event): ?float
    {
        if ($event->lat === null || $event->lng === null) {
            return null;
        }

        $polyline = $plan->polyline();
        if (empty($polyline)) {
            return null;
        }

        $step = max(1, (int) config('events.route_subscription.polyline_sample_step', 20));
        $min = PHP_FLOAT_MAX;

        for ($i = 0, $n = count($polyline); $i < $n; $i += $step) {
            [$lat, $lng] = $polyline[$i];
            $d = Haversine::distanceMeters((float) $event->lat, (float) $event->lng, (float) $lat, (float) $lng);
            if ($d < $min) {
                $min = $d;
            }
        }

        // Контрольно — обязательно проверим последнюю точку, иначе на коротких маршрутах
        // с step > длины массива можно пропустить хвост.
        $last = end($polyline);
        if ($last) {
            $d = Haversine::distanceMeters((float) $event->lat, (float) $event->lng, (float) $last[0], (float) $last[1]);
            if ($d < $min) {
                $min = $d;
            }
        }

        return $min === PHP_FLOAT_MAX ? null : $min;
    }

    public function matchesCorridor(RoutePlan $plan, RoadEvent $event): bool
    {
        $corridorM = (float) config('events.route_subscription.corridor_km', 5.0) * 1000;
        $d = $this->distanceToRouteMeters($plan, $event);
        return $d !== null && $d <= $corridorM;
    }
}
