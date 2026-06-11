<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'title'                 => $this->faker->words(2, true),
            'type'                  => $this->faker->randomElement([
                'Тягач + полуприцеп', 'Фургон', 'Одиночка', 'Рефрижератор',
            ]),
            'fuel_type'             => 'Дизель',
            'allowed_fuel'          => 'Дизель + AdBlue',
            'tank_capacity_l'       => $this->faker->randomElement([450, 500, 600, 650, 700]),
            'consumption_l_per_100' => $this->faker->randomFloat(1, 24, 35),
            'cruise_speed_kmh'      => $this->faker->randomElement([80, 82, 85]),
            'curb_weight_t'         => $this->faker->randomFloat(1, 12, 18),
            'restrictions'          => null,
            'image'                 => null,
            'is_active'             => false,
        ];
    }
}
