<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSession extends Model
{
    protected $fillable = [
        'user_id',
        'route_plan_id',
        'status',
        'started_at',
        'ended_at',
        'actual_fuel_used_l',
        'actual_distance_km',
        'last_lat',
        'last_lng',
        'last_location_at',
        'notified_recommendation_ids',
        'accepted_stop_ids',
        'rejected_stop_ids',
    ];

    protected $casts = [
        'started_at'                  => 'datetime',
        'ended_at'                    => 'datetime',
        'actual_fuel_used_l'           => 'float',
        'actual_distance_km'           => 'float',
        'last_location_at'            => 'datetime',
        'last_lat'                    => 'float',
        'last_lng'                    => 'float',
        'notified_recommendation_ids' => 'array',
        'accepted_stop_ids'           => 'array',
        'rejected_stop_ids'           => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routePlan(): BelongsTo
    {
        return $this->belongsTo(RoutePlan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
