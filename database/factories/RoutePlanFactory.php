<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoutePlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'title'                 => 'Москва — Ростов-на-Дону',
            'origin'                => 'Москва',
            'origin_point'          => ['lat' => 55.7558, 'lng' => 37.6173, 'label' => 'Москва'],
            'destination'           => 'Ростов-на-Дону',
            'destination_point'     => ['lat' => 47.2357, 'lng' => 39.7015, 'label' => 'Ростов-на-Дону'],
            'via_point'             => null,
            'via_points'            => [],
            'start_time'            => now()->addDay(),
            'vehicle_type'          => 'Тягач + полуприцеп',
            'cargo_type'            => 'Обычный груз',
            'cargo_weight_t'        => 12.0,
            'vehicle_curb_weight_t' => 15.5,
            'gross_weight_t'        => 27.85,
            'start_fuel_l'          => 420,
            'tank_capacity_l'       => 600,
            'consumption_l_per_100' => 29.0,
            'reserve_percent'       => 15,
            'cruise_speed_kmh'      => 85,
            'planning_mode'         => 'Безопасный',
            'distance_km'           => 1070,
            'drive_time_minutes'    => 820,
            'fuel_needed_l'         => 310.30,
            'range_km'              => 390,
            'stops_count'           => 3,
            'recommendations'       => 'Тестовый маршрут.',
            'polyline_json'         => '[[55.75,37.62],[51.67,39.18],[47.24,39.70]]',
            'routing_provider'      => 'test',
        ];
    }
}
