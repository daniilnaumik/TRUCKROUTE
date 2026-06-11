<?php

namespace Tests\Feature\Api;

use App\Models\RoadEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    private function makeEvent(array $attrs = []): RoadEvent
    {
        return RoadEvent::create(array_merge([
            'title'        => 'ДТП на трассе',
            'type'         => 'ДТП',
            'location'     => 'М-4, 328 км',
            'description'  => 'Столкновение двух фур',
            'status'       => 'active',
            'importance'   => 'важно',
            'confidence_score' => 3,
            'reported_at'  => now(),
        ], $attrs));
    }

    /** @test */
    public function public_can_list_events(): void
    {
        $this->makeEvent();
        $this->makeEvent(['title' => 'Второе событие']);

        $this->getJson(self::API.'/events')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function public_can_view_single_event(): void
    {
        $event = $this->makeEvent();

        $this->getJson(self::API."/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    /** @test */
    public function events_can_be_filtered_by_status(): void
    {
        $this->makeEvent(['status' => 'active']);
        $this->makeEvent(['status' => 'closed']);

        $this->getJson(self::API.'/events?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function authenticated_driver_can_submit_event(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/events', [
                'title'       => 'Пробка',
                'type'        => 'Затор',
                'highway'     => 'М-7',
                'location'    => 'М-7, 41 км',
                'description' => 'Затор 6 км',
                'lat'         => 55.90,
                'lng'         => 37.80,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('road_events', ['title' => 'Пробка', 'created_by_user_id' => $user->id]);
    }

    /** @test */
    public function driver_can_vote_on_event(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent(['confidence_score' => 3]);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API."/events/{$event->id}/vote", ['vote' => 1])
            ->assertOk();

        $this->assertDatabaseHas('event_votes', ['road_event_id' => $event->id, 'user_id' => $user->id, 'vote' => 1]);
    }

    /** @test */
    public function guest_cannot_submit_event(): void
    {
        $this->postJson(self::API.'/events', ['title' => 'X'])->assertUnauthorized();
    }
}
