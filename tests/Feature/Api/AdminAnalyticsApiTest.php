<?php

namespace Tests\Feature\Api;

use App\Models\RoutePlan;
use App\Models\TripSession;
use App\Models\User;
use App\Models\UserActivityDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_product_analytics(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);

        UserActivityDay::create([
            'user_id' => $driver->id,
            'activity_date' => today(),
            'platform' => 'android',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        $plan = RoutePlan::create($this->routePayload($driver));
        TripSession::create([
            'user_id' => $driver->id,
            'route_plan_id' => $plan->id,
            'status' => 'ended',
            'started_at' => now()->subMinutes(60),
            'ended_at' => now(),
            'actual_fuel_used_l' => 80,
            'actual_distance_km' => 200,
            'notified_recommendation_ids' => [11, 12],
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $driver->id,
            'data' => '{}',
            'read_at' => now(),
            'created_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/analytics?days=30')
            ->assertOk()
            ->assertJsonPath('data.audience.dau', 1)
            ->assertJsonPath('data.routes.built', 1)
            ->assertJsonPath('data.routes.started', 1)
            ->assertJsonPath('data.routes.completed', 1)
            ->assertJsonPath('data.routes.start_conversion_percent', 100)
            ->assertJsonPath('data.recommendations.shown', 2)
            ->assertJsonPath('data.notifications.sent', 1)
            ->assertJsonPath('data.notifications.read', 1)
            ->assertJsonPath('data.savings.time_minutes', 60)
            ->assertJsonPath('data.savings.fuel_liters', 20);
    }

    public function test_non_admin_cannot_read_product_analytics(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DRIVER]));

        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
    }

    public function test_authenticated_request_records_daily_activity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->assertDatabaseHas('user_activity_days', [
            'user_id' => $user->id,
            'activity_date' => today()->toDateString(),
        ]);
    }

    private function routePayload(User $user): array
    {
        return [
            'user_id' => $user->id,
            'title' => 'Минск — Брест',
            'origin' => 'Минск',
            'destination' => 'Брест',
            'vehicle_type' => 'Тягач + полуприцеп',
            'cargo_type' => 'Обычный',
            'start_fuel_l' => 300,
            'tank_capacity_l' => 600,
            'consumption_l_per_100' => 30,
            'reserve_percent' => 15,
            'cruise_speed_kmh' => 80,
            'planning_mode' => 'Экономичный',
            'distance_km' => 200,
            'drive_time_minutes' => 120,
            'fuel_needed_l' => 100,
            'range_km' => 850,
            'stops_count' => 1,
            'recommendations' => 'Тестовый расчёт',
        ];
    }
}
