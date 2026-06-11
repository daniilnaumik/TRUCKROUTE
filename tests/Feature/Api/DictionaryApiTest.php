<?php

namespace Tests\Feature\Api;

use App\Models\DictionaryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DictionaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_read_active_dictionaries(): void
    {
        DictionaryItem::create([
            'dictionary' => 'event_types',
            'value' => 'Тестовое событие',
            'label' => 'Тестовое событие',
            'sort_order' => 500,
            'is_active' => true,
        ]);

        DictionaryItem::create([
            'dictionary' => 'event_types',
            'value' => 'Скрытое событие',
            'label' => 'Скрытое событие',
            'sort_order' => 510,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/dictionaries?dictionary=event_types');

        $response->assertOk()
            ->assertJsonFragment(['value' => 'Тестовое событие'])
            ->assertJsonMissing(['value' => 'Скрытое событие']);
    }

    public function test_admin_can_manage_dictionary_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/dictionaries', [
                'dictionary' => 'cargo_types',
                'label' => 'Хрупкий',
                'description' => 'Требует аккуратной перевозки',
                'sort_order' => 90,
                'is_active' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.value', 'Хрупкий');

        $id = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/dictionaries/{$id}", [
                'label' => 'Хрупкий груз',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Хрупкий груз')
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/dictionaries/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('dictionary_items', ['id' => $id]);
    }

    public function test_non_admin_cannot_manage_dictionaries(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/admin/dictionaries', [
                'dictionary' => 'tags',
                'label' => 'Закрытый тег',
            ])
            ->assertForbidden();
    }
}
