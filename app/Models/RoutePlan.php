<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'title',
        'origin',
        'origin_point',
        'destination',
        'destination_point',
        'via_point',
        'via_points',
        'start_time',
        'vehicle_type',
        'cargo_type',
        'cargo_weight_t',
        'vehicle_curb_weight_t',
        'gross_weight_t',
        'start_fuel_l',
        'tank_capacity_l',
        'consumption_l_per_100',
        'effective_consumption_l_per_100',
        'reserve_percent',
        'reserve_l',
        'cruise_speed_kmh',
        'planning_mode',
        'distance_km',
        'drive_time_minutes',
        'arrival_time',
        'fuel_needed_l',
        'fuel_cost_rub',
        'range_km',
        'stops_count',
        'recommendations',
        'image',
        'polyline_json',
        'routing_provider',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'arrival_time' => 'datetime',
        'origin_point' => 'array',
        'destination_point' => 'array',
        'via_points' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recommendationsList()
    {
        return $this->hasMany(RouteRecommendation::class)->orderBy('order_index');
    }

    public function tripSessions()
    {
        return $this->hasMany(TripSession::class);
    }

    /**
     * Полилиния как массив [[lat,lng],...]. Декодируем из LONGTEXT по требованию,
     * чтобы не таскать гигабайт на каждом select-е.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function polyline(): array
    {
        if (!$this->polyline_json) {
            return [];
        }
        $decoded = json_decode($this->polyline_json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
