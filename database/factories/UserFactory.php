<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Create user with driver role. */
    public function driver(): static
    {
        return $this->state(['role' => 'driver', 'status' => 'active']);
    }

    /** Create user with admin role. */
    public function admin(): static
    {
        return $this->state(['role' => 'admin', 'status' => 'active']);
    }

    /** Create user with provider role. */
    public function provider(): static
    {
        return $this->state(['role' => 'provider', 'status' => 'active']);
    }
}
