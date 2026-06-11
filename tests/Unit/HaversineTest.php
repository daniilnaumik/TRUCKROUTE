<?php

namespace Tests\Unit;

use App\Jobs\ProximityAlertJob;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tests the Haversine distance formula used by ProximityAlertJob.
 * Uses reflection to access the private method.
 */
class HaversineTest extends TestCase
{
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Inline the formula here to keep test independent of job implementation
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }

    /** @test */
    public function distance_between_same_point_is_zero(): void
    {
        $dist = $this->haversineKm(55.75, 37.62, 55.75, 37.62);
        $this->assertEqualsWithDelta(0.0, $dist, 0.001);
    }

    /** @test */
    public function moscow_to_saint_petersburg_is_approximately_634km(): void
    {
        $dist = $this->haversineKm(55.7558, 37.6173, 59.9343, 30.3352);
        // Straight-line ~634 km
        $this->assertEqualsWithDelta(634, $dist, 10);
    }

    /** @test */
    public function distance_is_symmetric(): void
    {
        $ab = $this->haversineKm(55.75, 37.62, 48.87, 2.33);
        $ba = $this->haversineKm(48.87, 2.33, 55.75, 37.62);
        $this->assertEqualsWithDelta($ab, $ba, 0.001);
    }

    /** @test */
    public function proximity_alert_job_haversine_matches(): void
    {
        // Access the private method via reflection
        // Use reflection to call it
        $ref    = new ReflectionClass(ProximityAlertJob::class);
        $method = $ref->getMethod('haversineKm');
        $method->setAccessible(true);

        // Create a minimal instance without triggering Queueable magic
        $session = new \App\Models\TripSession(['last_lat' => 0, 'last_lng' => 0]);
        $instance = new ProximityAlertJob($session);

        $dist = $method->invoke($instance, 55.7558, 37.6173, 59.9343, 30.3352);
        $this->assertEqualsWithDelta(634, $dist, 10);
    }
}
