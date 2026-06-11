<?php

namespace Tests\Feature\Api;

use App\Models\PoiReview;
use App\Models\ServiceObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoiReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_card_contains_full_details_and_reviews(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $poi = $this->makePoi($provider, [
            'working_hours' => ['monday' => '08:00–22:00'],
            'contacts' => ['phone' => '+375 29 000-00-00'],
            'price_details' => [['name' => 'Дизель', 'price' => 2.45, 'unit' => 'BYN/л']],
            'promotions' => [['title' => 'Кофе в подарок']],
            'truck_access' => ['allowed' => true, 'max_height_m' => 4.5],
        ]);
        PoiReview::create([
            'service_object_id' => $poi->id,
            'user_id' => $driver->id,
            'rating' => 5,
            'body' => 'Удобный подъезд.',
        ]);

        $this->getJson("/api/v1/poi/{$poi->id}")
            ->assertOk()
            ->assertJsonPath('data.working_hours.monday', '08:00–22:00')
            ->assertJsonPath('data.contacts.phone', '+375 29 000-00-00')
            ->assertJsonPath('data.price_details.0.name', 'Дизель')
            ->assertJsonPath('data.promotions.0.title', 'Кофе в подарок')
            ->assertJsonPath('data.truck_access.allowed', true)
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews_count', 1);
    }

    public function test_user_can_create_and_update_single_review(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $poi = $this->makePoi($provider);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/poi/{$poi->id}/reviews", [
                'rating' => 4,
                'body' => 'Хорошая стоянка.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);

        $review = PoiReview::firstOrFail();

        $this->actingAs($driver, 'sanctum')
            ->patchJson("/api/v1/poi/{$poi->id}/reviews/{$review->id}", [
                'rating' => 5,
                'body' => 'Отличная стоянка.',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseCount('poi_reviews', 1);
        $this->assertEquals(5.0, (float) $poi->fresh()->rating);
    }

    public function test_provider_can_reply_to_review_but_other_user_cannot(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $otherProvider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $poi = $this->makePoi($provider);
        $review = PoiReview::create([
            'service_object_id' => $poi->id,
            'user_id' => $driver->id,
            'rating' => 4,
            'body' => 'Нужна разметка.',
        ]);

        $this->actingAs($otherProvider, 'sanctum')
            ->postJson("/api/v1/poi/{$poi->id}/reviews/{$review->id}/reply", [
                'reply' => 'Исправим.',
            ])
            ->assertForbidden();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/v1/poi/{$poi->id}/reviews/{$review->id}/reply", [
                'reply' => 'Спасибо, обновили разметку.',
            ])
            ->assertOk()
            ->assertJsonPath('data.owner_reply', 'Спасибо, обновили разметку.');
    }

    public function test_provider_cannot_review_own_object(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $poi = $this->makePoi($provider);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/v1/poi/{$poi->id}/reviews", [
                'rating' => 5,
                'body' => 'Лучший объект.',
            ])
            ->assertUnprocessable();
    }

    private function makePoi(User $provider, array $attributes = []): ServiceObject
    {
        return ServiceObject::create(array_merge([
            'provider_id' => $provider->id,
            'name' => 'Стоянка М1',
            'type' => 'Стоянка',
            'location' => 'М1, 250 км',
            'description' => 'Охраняемая стоянка.',
            'services' => 'Душ, кафе, парковка',
            'status' => 'verified',
            'verified' => true,
            'rating' => 4.5,
        ], $attributes));
    }
}
