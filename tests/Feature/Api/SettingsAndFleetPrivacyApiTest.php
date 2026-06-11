<?php

namespace Tests\Feature\Api;

use App\Models\Fleet;
use App\Models\RouteAssignment;
use App\Models\RoutePlan;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\FleetAssignmentIssued;
use App\Services\RouteBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class SettingsAndFleetPrivacyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_and_old_password_stops_working(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/settings/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNotNull($user->settings()->value('last_password_change_at'));
    }

    public function test_fleet_owner_sees_driver_history_only_after_explicit_consent(): void
    {
        $owner = User::factory()->create(['role' => 'fleet']);
        $driver = User::factory()->driver()->create();
        $fleet = Fleet::create(['owner_id' => $owner->id, 'name' => 'Тестовый парк']);
        $fleet->drivers()->attach($driver->id);
        RoutePlan::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/fleets/{$fleet->id}/drivers/{$driver->id}/routes")
            ->assertForbidden();

        $route = $driver->routePlans()->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/routes/{$route->id}")
            ->assertForbidden();

        UserSetting::updateOrCreate(
            ['user_id' => $driver->id],
            ['share_route_history_with_fleet' => true],
        );

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/fleets/{$fleet->id}/drivers/{$driver->id}/routes")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/routes/{$route->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $route->id);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/routes/{$route->id}")
            ->assertForbidden();
    }

    public function test_accepting_assignment_attaches_a_ready_route(): void
    {
        $owner = User::factory()->create(['role' => 'fleet']);
        $driver = User::factory()->driver()->create();
        $fleet = Fleet::create(['owner_id' => $owner->id, 'name' => 'Тестовый парк']);
        $fleet->drivers()->attach($driver->id);

        $assignment = RouteAssignment::create([
            'fleet_id' => $fleet->id,
            'driver_user_id' => $driver->id,
            'issued_by_user_id' => $owner->id,
            'origin' => 'Брест',
            'origin_point' => ['lat' => 52.0976, 'lng' => 23.7341],
            'destination' => 'Минск',
            'destination_point' => ['lat' => 53.9023, 'lng' => 27.5619],
            'status' => 'issued',
        ]);
        $plan = RoutePlan::factory()->create(['user_id' => $driver->id]);

        $this->mock(RouteBuildService::class, function (MockInterface $mock) use ($plan) {
            $mock->shouldReceive('build')->once()->andReturn($plan);
        });

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/assignments/{$assignment->id}/accept")
            ->assertOk()
            ->assertJsonPath('route_plan_id', $plan->id);

        $this->assertDatabaseHas('route_assignments', [
            'id' => $assignment->id,
            'status' => 'accepted',
            'route_plan_id' => $plan->id,
        ]);
    }

    public function test_assignment_requires_planned_start_and_returns_field_error(): void
    {
        $owner = User::factory()->create(['role' => 'fleet']);
        $driver = User::factory()->driver()->create();
        $fleet = Fleet::create(['owner_id' => $owner->id, 'name' => 'Тестовый парк']);
        $fleet->drivers()->attach($driver->id);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/fleets/{$fleet->id}/assignments", [
                'driver_user_id' => $driver->id,
                'origin' => 'Брест',
                'destination' => 'Минск',
                'vehicle_source' => 'driver',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_start_at']);
    }

    public function test_driver_receives_notification_and_can_open_assignment(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => 'fleet']);
        $driver = User::factory()->driver()->create();
        $fleet = Fleet::create(['owner_id' => $owner->id, 'name' => 'Тестовый парк']);
        $fleet->drivers()->attach($driver->id);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/fleets/{$fleet->id}/assignments", [
                'driver_user_id' => $driver->id,
                'origin' => 'Брест',
                'destination' => 'Минск',
                'planned_start_at' => now()->addDay()->toIso8601String(),
                'vehicle_source' => 'driver',
            ])
            ->assertCreated();

        $assignmentId = $response->json('data.id');

        Notification::assertSentTo($driver, FleetAssignmentIssued::class);

        $this->actingAs($driver, 'sanctum')
            ->getJson("/api/v1/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignmentId)
            ->assertJsonPath('data.vehicle_source', 'driver');
    }

    public function test_fleet_can_add_vehicle_and_assign_it_to_driver(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => 'fleet']);
        $driver = User::factory()->driver()->create();
        $fleet = Fleet::create(['owner_id' => $owner->id, 'name' => 'Тестовый парк']);
        $fleet->drivers()->attach($driver->id);

        $vehicleResponse = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/fleets/{$fleet->id}/vehicles", [
                'title' => 'Volvo автопарка',
                'type' => 'Тягач + полуприцеп',
                'model' => 'FH 460',
                'fuel_type' => 'Дизель',
                'tank_capacity_l' => 800,
                'consumption_l_per_100' => 28.5,
                'cruise_speed_kmh' => 85,
                'curb_weight_t' => 16,
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', 'fleet');

        $vehicleId = $vehicleResponse->json('data.id');

        $assignmentResponse = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/fleets/{$fleet->id}/assignments", [
                'driver_user_id' => $driver->id,
                'origin' => 'Брест',
                'destination' => 'Минск',
                'planned_start_at' => now()->addDay()->toIso8601String(),
                'vehicle_source' => 'fleet',
                'vehicle_id' => $vehicleId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle_source', 'fleet')
            ->assertJsonPath('data.vehicle.id', $vehicleId);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicleId,
            'fleet_id' => $fleet->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('route_assignments', [
            'id' => $assignmentResponse->json('data.id'),
            'vehicle_source' => 'fleet',
            'vehicle_id' => $vehicleId,
        ]);
    }
}
