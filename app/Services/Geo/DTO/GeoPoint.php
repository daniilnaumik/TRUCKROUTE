<?php

namespace App\Services\Geo\DTO;

/**
 * Простая иммутабельная точка с географическими координатами.
 * lat ∈ [-90, 90], lng ∈ [-180, 180]. Третье поле — необязательное название/адрес.
 */
final class GeoPoint
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?string $label = null,
    ) {
        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException("lat out of range: {$lat}");
        }
        if ($lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException("lng out of range: {$lng}");
        }
    }

    /**
     * @return array{lat: float, lng: float, label: string|null}
     */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng, 'label' => $this->label];
    }
}
