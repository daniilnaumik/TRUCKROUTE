<?php

namespace App\Jobs;

use App\Models\TripSession;
use App\Notifications\ProximityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Checks whether the driver is approaching an upcoming route recommendation
 * and sends a push notification if within the configured radius.
 *
 * Triggered by TripController@location every time the mobile app POSTs GPS.
 */
class ProximityAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly TripSession $session)
    {
    }

    public function handle(): void
    {
        $session = $this->session->fresh(['user.settings', 'routePlan.recommendationsList.serviceObject']);

        // Guard: session ended or no position
        if (!$session || !$session->isActive() || !$session->last_lat || !$session->last_lng) {
            return;
        }

        $user  = $session->user;
        $plan  = $session->routePlan;

        if (!$user || !$plan) {
            return;
        }

        // Notification radius from user settings (default 15 km)
        $radiusKm = min(2.5, (float) ($user->settings?->notification_radius_km ?? 2.5));

        $alreadyNotified = $session->notified_recommendation_ids ?? [];
        $newlyNotified   = [];

        foreach ($plan->recommendationsList as $rec) {
            if (in_array($rec->id, $alreadyNotified, true)) {
                continue;
            }

            $poi = $rec->serviceObject;
            if (!$poi || !$poi->lat || !$poi->lng) {
                continue;
            }

            $distanceKm = $this->haversineKm(
                $session->last_lat,
                $session->last_lng,
                $poi->lat,
                $poi->lng,
            );

            if ($distanceKm <= $radiusKm) {
                // Send notification
                $user->notify(new ProximityAlert($rec, $poi, $distanceKm));
                $newlyNotified[] = $rec->id;
            }
        }

        if ($newlyNotified) {
            $session->update([
                'notified_recommendation_ids' => array_merge($alreadyNotified, $newlyNotified),
            ]);
        }
    }

    /**
     * Haversine formula — distance between two GPS points in km.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R  = 6371.0; // Earth radius km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a   = sin($dLat / 2) ** 2
             + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }
}
