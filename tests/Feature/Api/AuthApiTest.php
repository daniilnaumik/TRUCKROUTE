<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    // ──────────────────────────────────────────────────────────────────────
    // Register
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function driver_can_register_and_receives_token(): void
    {
        $res = $this->postJson(self::API.'/auth/register', [
            'name'                  => 'Иван Тестов',
            'email'                 => 'ivan@test.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token', 'token_type']);

        $this->assertEquals('driver', $res->json('user.role'));
        $this->assertNotEmpty($res->json('token'));
    }

    /** @test */
    public function register_validates_required_fields(): void
    {
        $this->postJson(self::API.'/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@test.local']);

        $this->postJson(self::API.'/auth/register', [
            'name'                  => 'Дубль',
            'email'                 => 'dup@test.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Login
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $res = $this->postJson(self::API.'/auth/login', [
            'email'    => $user->email,
            'password' => 'secret',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    /** @test */
    public function login_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('right')]);

        $this->postJson(self::API.'/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    /** @test */
    public function blocked_user_cannot_login(): void
    {
        $user = User::factory()->create(['status' => 'blocked', 'password' => bcrypt('pass')]);

        $this->postJson(self::API.'/auth/login', [
            'email' => $user->email, 'password' => 'pass',
        ])->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Me + Logout
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(self::API.'/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    /** @test */
    public function unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson(self::API.'/auth/me')->assertUnauthorized();
    }

    /** @test */
    public function user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(self::API.'/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Выход выполнен.');
    }
}
