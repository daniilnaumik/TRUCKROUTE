<?php

namespace Tests\Feature\Api;

use App\Models\RouteRecommendation;
use App\Models\RoutePlan;
use App\Models\ServiceObject;
use App\Models\TripSession;
use App\Models\User;
use App\Notifications\ProximityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    /** @test */
    public function driver_can_start_a_trip(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/start');

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('trip_sessions', ['user_id' => $user->id, 'status' => 'active']);
    }

    /** @test */
    public function starting_trip_ends_previous_active_session(): void
    {
        $user = User::factory()->create();
        $old  = TripSession::create(['user_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/start')
            ->assertStatus(201);

        $this->assertDatabaseHas('trip_sessions', ['id' => $old->id, 'status' => 'ended']);
        $this->assertDatabaseCount('trip_sessions', 2);
    }

    /** @test */
    public function driver_can_get_current_active_session(): void
    {
        $user = User::factory()->create();
        TripSession::create(['user_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::API.'/trip/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    /** @test */
    public function current_returns_null_when_no_active_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(self::API.'/trip/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    /** @test */
    public function driver_can_update_location(): void
    {
        $user = User::factory()->create();
        TripSession::create(['user_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/location', ['lat' => 55.75, 'lng' => 37.62])
            ->assertOk();

        $this->assertDatabaseHas('trip_sessions', [
            'user_id'  => $user->id,
            'last_lat' => 55.75,
            'last_lng' => 37.62,
        ]);
    }

    /** @test */
    public function location_update_without_active_session_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/location', ['lat' => 55.75, 'lng' => 37.62])
            ->assertNotFound();
    }

    /** @test */
    public function driver_can_end_trip(): void
    {
        $user = User::factory()->create();
        TripSession::create(['user_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/end')
            ->assertOk();

        $this->assertDatabaseHas('trip_sessions', ['user_id' => $user->id, 'status' => 'ended']);
    }

    /** @test */
    public function location_update_triggers_proximity_notification_when_within_radius(): void
    {
        Notification::fake();

        $user    = User::factory()->create();
        $setting = \App\Models\UserSetting::create([
            'user_id'                => $user->id,
            'notification_radius_km' => 15,
            'incident_notifications' => true,
        ]);

        // Москва coordinates
        $driverLat = 55.7558;
        $driverLng = 37.6173;

        // POI ~5 km away
        $poi = ServiceObject::create([
            'name' => 'АЗС Близко', 'type' => 'АЗС',
            'location' => 'М-4, 5 км', 'description' => 'Test',
            'services' => 'Дизель', 'status' => 'verified', 'verified' => true,
            'rating' => 4.5,
            'lat' => 55.773,   // ~1.9 km north
            'lng' => 37.617,
        ]);

        $plan = RoutePlan::factory()->create(['user_id' => $user->id]);

        $rec = RouteRecommendation::create([
            'route_plan_id'         => $plan->id,
            'service_object_id'     => $poi->id,
            'type'                  => 'fuel',
            'order_index'           => 1,
            'distance_from_start_km'=> 100,
            'detour_km'             => 0.5,
        ]);

        $session = TripSession::create([
            'user_id'       => $user->id,
            'route_plan_id' => $plan->id,
            'status'        => 'active',
            'started_at'    => now(),
            'last_lat'      => $driverLat,
            'last_lng'      => $driverLng,
        ]);

        // Run the job synchronously (QUEUE_CONNECTION=sync in testing)
        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/location', ['lat' => $driverLat, 'lng' => $driverLng])
            ->assertOk();

        Notification::assertSentTo($user, ProximityAlert::class);
    }

    /** @test */
    public function proximity_notification_not_sent_when_far_from_poi(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        \App\Models\UserSetting::create(['user_id' => $user->id, 'notification_radius_km' => 10, 'incident_notifications' => true]);

        $poi = ServiceObject::create([
            'name' => 'АЗС Далеко', 'type' => 'АЗС',
            'location' => 'М-4, far', 'description' => 'Test',
            'services' => 'Дизель', 'status' => 'verified', 'verified' => true,
            'rating' => 4.5,
            'lat' => 60.0,   // ~480 km away from Moscow
            'lng' => 37.62,
        ]);

        $plan = RoutePlan::factory()->create(['user_id' => $user->id]);
        RouteRecommendation::create([
            'route_plan_id' => $plan->id, 'service_object_id' => $poi->id,
            'type' => 'fuel', 'order_index' => 1, 'distance_from_start_km' => 500, 'detour_km' => 0,
        ]);

        TripSession::create([
            'user_id' => $user->id, 'route_plan_id' => $plan->id,
            'status' => 'active', 'started_at' => now(),
            'last_lat' => 55.75, 'last_lng' => 37.62,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/trip/location', ['lat' => 55.75, 'lng' => 37.62]);

        Notification::assertNotSentTo($user, ProximityAlert::class);
    }
}
