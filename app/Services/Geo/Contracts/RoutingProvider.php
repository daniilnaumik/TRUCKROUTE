<?php

namespace App\Services\Geo\Contracts;

use App\Services\Geo\DTO\GeoPoint;
use App\Services\Geo\DTO\RouteGeometry;

interface RoutingProvider
{
    /**
     * Строит маршрут from → via... → to. Поднимает RoutingException, если провайдер недоступен.
     *
     * @param array<int, GeoPoint> $via
     */
    public function route(GeoPoint $from, GeoPoint $to, array $via = []): RouteGeometry;

    public function name(): string;
}
