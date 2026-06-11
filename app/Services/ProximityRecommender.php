<?php

namespace App\Services;

use App\Models\ServiceObject;
use App\Models\TripSession;

/**
 * Suggests 1-2 additional POI that are not already in the route,
 * based on driver's current position and route plan.
 */
class ProximityRecommender
{
    public function suggest(TripSession $session, float $lat, float $lng, int $count = 2): array
    {
        $rejectedIds = $session->rejected_stop_ids ?? [];
        $acceptedIds = $session->accepted_stop_ids ?? [];

        // IDs already in the planned route
        $routePoiIds = [];
        if ($session->route_plan_id) {
            $routePoiIds = $session->routePlan
                ->recommendationsList()
                ->pluck('service_object_id')
                ->toArray();
        }

        $excludeIds = array_unique(array_merge($rejectedIds, $routePoiIds));

        // Approx 50km bbox (1° lat ≈ 111km)
        $latDelta = 50 / 111.0;
        $lngDelta = 50 / max(1.0, 111.0 * cos(deg2rad($lat)));

        $candidates = ServiceObject::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('verified', true)
            ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->when(!empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('rating')
            ->orderBy('detour_km')
            ->limit($count + 10)
            ->get();

        return $candidates
            ->map(fn ($poi) => [
                'poi' => $poi,
                'distance_km' => $this->haversineKm($lat, $lng, $poi->lat, $poi->lng),
            ])
            ->filter(fn ($item) => $item['distance_km'] <= 50.0)
            ->sortBy('distance_km')
            ->take($count)
            ->values()
            ->map(function ($item) {
                $p = $item['poi'];

                return [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'type'      => $p->type,
                    'lat'       => $p->lat,
                    'lng'       => $p->lng,
                    'distance_km' => round($item['distance_km'], 1),
                    'rating'    => $p->rating,
                    'brand'     => $p->brand,
                    'fuel_price' => $p->fuel_price !== null ? (float) $p->fuel_price : null,
                    'has_truck_parking' => (bool) $p->has_truck_parking,
                    'detour_km' => (float) $p->detour_km,
                    'services'  => $p->services,
                    'location'  => $p->location,
                ];
            })
            ->toArray();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }
}
