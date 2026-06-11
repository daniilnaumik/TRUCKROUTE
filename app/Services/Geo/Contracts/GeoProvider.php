<?php

namespace App\Services\Geo\Contracts;

use App\Services\Geo\DTO\GeoPoint;

interface GeoProvider
{
    /**
     * Прямое геокодирование: текст адреса → координаты. Возвращает null, если не найдено.
     */
    public function geocode(string $address): ?GeoPoint;

    /**
     * Обратное геокодирование: координаты → человекочитаемый адрес. Null = не определён.
     */
    public function reverse(float $lat, float $lng): ?string;

    /**
     * Имя провайдера для логов/ответов клиенту.
     */
    public function name(): string;
}
