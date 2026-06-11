<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteAssignment extends Model
{
    protected $fillable = [
        'fleet_id',
        'driver_user_id',
        'issued_by_user_id',
        'route_plan_id',
        'vehicle_source',
        'vehicle_id',
        'origin',
        'origin_point',
        'destination',
        'destination_point',
        'via_points',
        'planned_start_at',
        'comment',
        'status',
        'completed_at',
        'rating_stars',
        'rating_comment',
        'rated_by_user_id',
        'rated_at',
    ];

    protected $casts = [
        'origin_point' => 'array',
        'destination_point' => 'array',
        'via_points' => 'array',
        'planned_start_at' => 'datetime',
        'completed_at' => 'datetime',
        'rated_at' => 'datetime',
    ];

    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function ratedBy()
    {
        return $this->belongsTo(User::class, 'rated_by_user_id');
    }

    public function routePlan()
    {
        return $this->belongsTo(RoutePlan::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
