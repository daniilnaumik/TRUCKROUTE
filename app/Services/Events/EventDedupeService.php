<?php

namespace App\Services\Events;

use App\Models\RoadEvent;
use App\Support\Haversine;

/**
 * Поиск ближайшего "родителя" для нового события: тот же тип, активный, в радиусе R метров,
 * заведён в окне T минут. Если найден — новое создание превращается в +1 голос к нему.
 */
class EventDedupeService
{
    public function findDuplicate(string $type, float $lat, float $lng): ?RoadEvent
    {
        if (!config('events.dedupe.enabled', true)) {
            return null;
        }

        $radiusMeters = (float) config('events.dedupe.radius_meters', 500);
        $windowMinutes = (int) config('events.dedupe.time_window_minutes', 120);

        $candidates = RoadEvent::query()
            ->active()
            ->where('type', $type)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('reported_at', '>=', now()->subMinutes($windowMinutes))
            // Приблизительный bbox-pre-filter (~ радиус/111 км).
            ->whereBetween('lat', [$lat - $radiusMeters / 111000, $lat + $radiusMeters / 111000])
            ->get();

        foreach ($candidates as $candidate) {
            $d = Haversine::distanceMeters($lat, $lng, (float) $candidate->lat, (float) $candidate->lng);
            if ($d <= $radiusMeters) {
                return $candidate;
            }
        }

        return null;
    }
}
