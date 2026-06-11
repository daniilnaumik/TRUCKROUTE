<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteRecommendation extends Model
{
    protected $fillable = [
        'route_plan_id',
        'service_object_id',
        'type',
        'order_index',
        'distance_from_start_km',
        'detour_km',
        'eta_at',
        'fuel_before_l',
        'suggested_fuel_l',
        'note',
    ];

    protected $casts = [
        'eta_at' => 'datetime',
        'fuel_before_l' => 'decimal:2',
        'suggested_fuel_l' => 'decimal:2',
        'detour_km' => 'decimal:1',
    ];

    public function routePlan()
    {
        return $this->belongsTo(RoutePlan::class);
    }

    public function serviceObject()
    {
        return $this->belongsTo(ServiceObject::class);
    }
}
