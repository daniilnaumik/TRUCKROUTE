<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiclesApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    private function vehicleData(array $overrides = []): array
    {
        return array_merge([
            'title'                 => 'Мой грузовик',
            'type'                  => 'Тягач + полуприцеп',
            'fuel_type'             => 'Дизель',
            'tank_capacity_l'       => 600,
            'consumption_l_per_100' => 29.0,
            'cruise_speed_kmh'      => 85,
            'curb_weight_t'         => 15.5,
        ], $overrides);
    }

    /** @test */
    public function driver_can_create_vehicle(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/vehicles', $this->vehicleData());

        $res->assertStatus(201)
            ->assertJsonPath('title', 'Мой грузовик')
            ->assertJsonPath('tank_capacity_l', 600);

        $this->assertDatabaseHas('vehicles', ['user_id' => $user->id, 'title' => 'Мой грузовик']);
    }

    /** @test */
    public function driver_can_list_own_vehicles_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Vehicle::factory()->count(2)->create(['user_id' => $owner->id]);
        Vehicle::factory()->count(3)->create(['user_id' => $other->id]);

        $res = $this->actingAs($owner, 'sanctum')
            ->getJson(self::API.'/vehicles');

        $res->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    /** @test */
    public function driver_can_activate_vehicle(): void
    {
        $user = User::factory()->create();
        $v1   = Vehicle::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $v2   = Vehicle::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API."/vehicles/{$v1->id}/activate")
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('vehicles', ['id' => $v1->id, 'is_active' => 1]);
    }

    /** @test */
    public function driver_cannot_access_another_users_vehicle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $v     = Vehicle::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson(self::API."/vehicles/{$v->id}")
            ->assertForbidden();
    }

    /** @test */
    public function vehicle_creation_validates_required_fields(): void
    {
        app()->setLocale('ru');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/vehicles', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'tank_capacity_l', 'consumption_l_per_100'])
            ->assertJsonPath('errors.title.0', 'Поле название транспорта обязательно для заполнения.');
    }

    /** @test */
    public function guest_cannot_access_vehicles(): void
    {
        $this->getJson(self::API.'/vehicles')->assertUnauthorized();
    }
}
