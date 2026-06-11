<?php

namespace Tests\Unit;

use App\Services\RouteCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RouteCalculatorTest extends TestCase
{
    private RouteCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new RouteCalculator();
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'origin'               => 'Москва',
            'destination'          => 'Ростов-на-Дону',
            'via_point'            => null,
            'start_time'           => '2026-06-01 07:00:00',
            'distance_km'          => 1000,
            'tank_capacity_l'      => 600,
            'start_fuel_l'         => 450,
            'consumption_l_per_100'=> 28.0,
            'cruise_speed_kmh'     => 85,
            'vehicle_type'         => 'Тягач + полуприцеп',
            'vehicle_curb_weight_t'=> 15.5,
            'cargo_weight_t'       => 12.0,
            'cargo_flag'           => 'Обычный',
            'cargo_requirements'   => '',
            'reserve_percent'      => 15,
            'planning_mode'        => 'Безопасный',
            'preferred_fuel_brand' => 'Любые',
            'lodging_type'         => 'Стоянка',
            'no_toll_roads'        => 'Нет',
            'continuous_drive_hours'=> 4,
            'include_rest_stop'    => false,
        ], $overrides);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Weight profile
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_calculates_gross_weight_from_vehicle_cargo_and_fuel(): void
    {
        $result = $this->calc->calculate($this->basePayload(), collect(), collect());

        // vehicle 15.5t + cargo 12t + 450L diesel (≈0.378t) ≈ 27.88t
        $this->assertGreaterThan(27.0, $result['gross_weight_t']);
        $this->assertLessThan(28.5, $result['gross_weight_t']);
    }

    /** @test */
    public function gross_weight_is_vehicle_only_when_no_cargo(): void
    {
        $result = $this->calc->calculate(
            $this->basePayload(['cargo_weight_t' => 0, 'start_fuel_l' => 0]),
            collect(), collect()
        );
        $this->assertEqualsWithDelta(15.5, $result['vehicle_curb_weight_t'], 0.01);
        $this->assertEqualsWithDelta(15.5, $result['gross_weight_t'], 0.01);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Fuel calculations
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_calculates_fuel_needed_correctly(): void
    {
        $result = $this->calc->calculate($this->basePayload(['distance_km' => 500]), collect(), collect());

        // Base consumption ≈ 28 l/100, weight multiplier applies
        // At minimum: 500km × 0.28 = 140L; effective will be slightly more
        $this->assertGreaterThan(135.0, (float) $result['fuel_needed_l']);
        $this->assertLessThan(200.0, (float) $result['fuel_needed_l']);
    }

    /** @test */
    public function reserve_liters_is_percentage_of_tank(): void
    {
        $result = $this->calc->calculate(
            $this->basePayload(['tank_capacity_l' => 600, 'reserve_percent' => 15]),
            collect(), collect()
        );
        $this->assertEqualsWithDelta(90.0, (float) $result['reserve_l'], 0.01);
    }

    /** @test */
    public function range_km_respects_reserve(): void
    {
        $payload = $this->basePayload([
            'start_fuel_l'         => 600,
            'tank_capacity_l'      => 600,
            'reserve_percent'      => 15,
            'consumption_l_per_100'=> 30.0,
            'cargo_weight_t'       => 0,
            'start_time'           => null,
        ]);
        $result = $this->calc->calculate($payload, collect(), collect());

        // Usable fuel = 600 - 90 = 510L at ~30L/100km = 1700km max
        $this->assertGreaterThan(1200, $result['range_km']);
        $this->assertLessThan(2000, $result['range_km']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Effective consumption multipliers
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function refrigerator_type_increases_consumption(): void
    {
        $normal = $this->calc->calculate($this->basePayload(['vehicle_type' => 'Тягач + полуприцеп', 'distance_km' => 500]), collect(), collect());
        $ref    = $this->calc->calculate($this->basePayload(['vehicle_type' => 'Рефрижератор', 'distance_km' => 500]), collect(), collect());

        $this->assertGreaterThan((float) $normal['fuel_needed_l'], (float) $ref['fuel_needed_l']);
    }

    /** @test */
    public function dangerous_cargo_increases_consumption(): void
    {
        $normal  = $this->calc->calculate($this->basePayload(['cargo_flag' => 'Обычный', 'distance_km' => 500]), collect(), collect());
        $dangerous = $this->calc->calculate($this->basePayload(['cargo_flag' => 'Опасный', 'distance_km' => 500]), collect(), collect());

        $this->assertGreaterThan((float) $normal['fuel_needed_l'], (float) $dangerous['fuel_needed_l']);
    }

    /** @test */
    public function no_toll_roads_increases_effective_consumption(): void
    {
        $normal = $this->calc->calculate($this->basePayload(['no_toll_roads' => 'Нет', 'distance_km' => 500]), collect(), collect());
        $detour = $this->calc->calculate($this->basePayload(['no_toll_roads' => 'Да',  'distance_km' => 500]), collect(), collect());

        $this->assertGreaterThan((float) $normal['fuel_needed_l'], (float) $detour['fuel_needed_l']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Drive time and arrival
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function drive_time_is_reasonable_for_given_distance_and_speed(): void
    {
        $result = $this->calc->calculate(
            $this->basePayload(['distance_km' => 850, 'cruise_speed_kmh' => 85]),
            collect(), collect()
        );

        // 850km / 85 = 10h = 600min minimum (no stops)
        $this->assertGreaterThanOrEqual(600, $result['drive_time_minutes']);
    }

    /** @test */
    public function arrival_time_is_after_start_time(): void
    {
        $result = $this->calc->calculate($this->basePayload(), collect(), collect());

        $this->assertGreaterThan(
            strtotime('2026-06-01 07:00:00'),
            $result['arrival_time']->timestamp
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Output structure
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function result_contains_all_required_keys(): void
    {
        $result = $this->calc->calculate($this->basePayload(), collect(), collect());

        foreach ([
            'distance_km', 'drive_time_minutes', 'arrival_time',
            'fuel_needed_l', 'range_km', 'reserve_l',
            'effective_consumption_l_per_100', 'vehicle_curb_weight_t', 'gross_weight_t',
            'stops_count', 'recommendations', 'recommendation_points', 'image',
        ] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    /** @test */
    public function stops_count_matches_recommendation_points_count(): void
    {
        $result = $this->calc->calculate($this->basePayload(), collect(), collect());
        $this->assertCount($result['stops_count'], $result['recommendation_points']);
    }
}
