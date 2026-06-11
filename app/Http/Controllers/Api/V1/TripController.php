<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProximityAlertJob;
use App\Models\PoiRouteSelection;
use App\Models\ServiceObject;
use App\Models\TripSession;
use App\Services\ProximityRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * POST /api/v1/trip/start — begin an active trip session.
     * Ends any previously active session for this user first.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route_plan_id' => ['nullable', 'integer', 'exists:route_plans,id'],
        ]);

        $user = $request->user();

        // End any previous active session
        $user->tripSessions()
            ->where('status', 'active')
            ->update(['status' => 'ended', 'ended_at' => now()]);

        $session = $user->tripSessions()->create([
            'route_plan_id' => $data['route_plan_id'] ?? null,
            'status'        => 'active',
            'started_at'    => now(),
        ]);

        return response()->json(['data' => $session], 201);
    }

    /**
     * POST /api/v1/trip/location — update GPS position.
     * Returns upcoming stops and system suggestions for the frontend.
     */
    public function location(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'        => ['required', 'numeric', 'between:-90,90'],
            'lng'        => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'speed_kmh'  => ['nullable', 'numeric', 'min:0'],
            'notify'     => ['nullable', 'boolean'],
        ]);

        $user    = $request->user();
        $session = $user->tripSessions()->where('status', 'active')->latest()->first();

        if (!$session) {
            return response()->json(['message' => 'Нет активной поездки.'], 404);
        }

        $session->update([
            'last_lat'         => $data['lat'],
            'last_lng'         => $data['lng'],
            'last_location_at' => now(),
        ]);

        $upcoming          = [];
        $hasProximityAlert = false;

        if ($session->route_plan_id) {
            $plan           = $session->routePlan()->with('recommendationsList.serviceObject')->first();
            $alreadyNotified = $session->notified_recommendation_ids ?? [];
            $rejectedIds    = $session->rejected_stop_ids ?? [];
            $radiusKm       = min(2.5, (float) ($user->settings?->notification_radius_km ?? 2.5));

            foreach ($plan->recommendationsList as $rec) {
                $poi = $rec->serviceObject;
                if (!$poi || !$poi->lat || !$poi->lng) {
                    continue;
                }

                $distKm = $this->haversineKm($data['lat'], $data['lng'], $poi->lat, $poi->lng);

                if ($distKm <= 100) {
                    $upcoming[] = [
                        'recommendation_id'  => $rec->id,
                        'service_object_id'  => $poi->id,
                        'name'               => $poi->name,
                        'type'               => $poi->type,
                        'lat'                => $poi->lat,
                        'lng'                => $poi->lng,
                        'distance_km'        => round($distKm, 1),
                        'eta_at'             => $rec->eta_at?->toIso8601String(),
                        'detour_km'          => (float) $rec->detour_km,
                        'fuel_before_l'      => $rec->fuel_before_l,
                        'suggested_fuel_l'   => $rec->suggested_fuel_l,
                        'services'           => $poi->services,
                        'location'           => $poi->location,
                        'brand'              => $poi->brand,
                        'rating'             => $poi->rating !== null ? (float) $poi->rating : null,
                        'fuel_price'         => $poi->fuel_price !== null ? (float) $poi->fuel_price : null,
                        'has_truck_parking'  => (bool) $poi->has_truck_parking,
                        'is_notified'        => in_array($rec->id, $alreadyNotified, true),
                        'is_rejected'        => in_array($poi->id, $rejectedIds, true),
                    ];

                    if ($distKm <= $radiusKm && !in_array($rec->id, $alreadyNotified, true)) {
                        $hasProximityAlert = true;
                    }
                }
            }

            usort($upcoming, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

            // Fire async push notifications if approaching
            if ($hasProximityAlert && ($data['notify'] ?? true)) {
                ProximityAlertJob::dispatch($session)->onQueue('default');
            }
        }

        $suggestions = (new ProximityRecommender())->suggest($session, $data['lat'], $data['lng']);

        return response()->json([
            'message'             => 'Местоположение обновлено.',
            'upcoming'            => $upcoming,
            'system_suggestions'  => $suggestions,
            'has_proximity_alert' => $hasProximityAlert,
        ]);
    }

    /**
     * POST /api/v1/trip/stop-decision — driver accepts or rejects a nearby stop.
     */
    public function stopDecision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_object_id' => ['required', 'integer', 'exists:service_objects,id'],
            'action'            => ['required', 'in:accepted,rejected'],
        ]);

        $session = $request->user()->tripSessions()->where('status', 'active')->latest()->first();

        if (!$session) {
            return response()->json(['message' => 'Нет активной поездки.'], 404);
        }

        $poiId  = (int) $data['service_object_id'];
        $action = $data['action'];

        // Record in analytics table (deduplicate per session)
        $alreadyRecorded = PoiRouteSelection::where('trip_session_id', $session->id)
            ->where('service_object_id', $poiId)
            ->exists();

        if (!$alreadyRecorded) {
            PoiRouteSelection::create([
                'trip_session_id'   => $session->id,
                'service_object_id' => $poiId,
                'action'            => $action,
            ]);
        }

        // Update session stop lists
        $field   = $action === 'accepted' ? 'accepted_stop_ids' : 'rejected_stop_ids';
        $current = $session->$field ?? [];
        if (!in_array($poiId, $current, true)) {
            $current[] = $poiId;
            $session->update([$field => $current]);
        }

        if ($action === 'accepted') {
            ServiceObject::where('id', $poiId)->increment('selections_count');

            $poi = ServiceObject::find($poiId);
            return response()->json([
                'ok'      => true,
                'action'  => 'accepted',
                'waypoint' => [
                    'lat'   => $poi->lat,
                    'lng'   => $poi->lng,
                    'label' => $poi->name,
                ],
            ]);
        }

        return response()->json(['ok' => true, 'action' => 'rejected']);
    }

    /**
     * POST /api/v1/trip/end — finish the active trip.
     */
    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actual_fuel_used_l' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'actual_distance_km' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        $user = $request->user();
        $session = $user->tripSessions()
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($session) {
            $session->update([
                'status' => 'ended',
                'ended_at' => now(),
                'actual_fuel_used_l' => $data['actual_fuel_used_l'] ?? null,
                'actual_distance_km' => $data['actual_distance_km'] ?? null,
            ]);
        }

        return response()->json([
            'message' => $session ? 'Поездка завершена.' : 'Нет активной поездки.',
        ]);
    }

    /**
     * GET /api/v1/trip/current — return current active session, if any.
     */
    public function current(Request $request): JsonResponse
    {
        $session = $request->user()
            ->tripSessions()
            ->with('routePlan')
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$session) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $session]);
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
