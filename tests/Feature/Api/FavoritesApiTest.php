<?php

namespace Tests\Feature\Api;

use App\Models\ServiceObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    private function makePoi(array $attrs = []): ServiceObject
    {
        return ServiceObject::create(array_merge([
            'name'     => 'АЗС Тест',
            'type'     => 'АЗС',
            'location' => 'М-4, 180 км',
            'description' => 'Тестовая АЗС',
            'services'    => 'Дизель, кафе',
            'status'      => 'verified',
            'verified'    => true,
            'rating'      => 4.5,
        ], $attrs));
    }

    /** @test */
    public function authenticated_user_can_add_poi_to_favorites(): void
    {
        $user = User::factory()->create();
        $poi  = $this->makePoi();

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API."/favorites/{$poi->id}")
            ->assertOk()
            ->assertJsonPath('is_favorite', true);

        $this->assertDatabaseHas('user_poi_favorites', [
            'user_id'           => $user->id,
            'service_object_id' => $poi->id,
        ]);
    }

    /** @test */
    public function adding_same_poi_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $poi  = $this->makePoi();

        $act = $this->actingAs($user, 'sanctum');
        $act->postJson(self::API."/favorites/{$poi->id}")->assertOk();
        $act->postJson(self::API."/favorites/{$poi->id}")->assertOk();

        $this->assertDatabaseCount('user_poi_favorites', 1);
    }

    /** @test */
    public function user_can_list_favorite_pois(): void
    {
        $user = User::factory()->create();
        $p1   = $this->makePoi(['name' => 'POI 1']);
        $p2   = $this->makePoi(['name' => 'POI 2']);

        $user->favoritePois()->attach([$p1->id, $p2->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::API.'/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function user_can_remove_poi_from_favorites(): void
    {
        $user = User::factory()->create();
        $poi  = $this->makePoi();
        $user->favoritePois()->attach($poi->id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson(self::API."/favorites/{$poi->id}")
            ->assertOk()
            ->assertJsonPath('is_favorite', false);

        $this->assertDatabaseMissing('user_poi_favorites', [
            'user_id'           => $user->id,
            'service_object_id' => $poi->id,
        ]);
    }

    /** @test */
    public function ids_endpoint_returns_only_ids(): void
    {
        $user = User::factory()->create();
        $poi  = $this->makePoi();
        $user->favoritePois()->attach($poi->id);

        $res = $this->actingAs($user, 'sanctum')
            ->getJson(self::API.'/favorites/ids');

        $res->assertOk();
        $ids = $res->json('data');
        $this->assertContains($poi->id, $ids);
    }

    /** @test */
    public function guest_cannot_access_favorites(): void
    {
        $this->getJson(self::API.'/favorites')->assertUnauthorized();
    }
}
