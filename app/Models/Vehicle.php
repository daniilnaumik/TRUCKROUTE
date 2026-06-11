<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fleet_id',
        'title',
        'type',
        'model',
        'fuel_type',
        'allowed_fuel',
        'tank_capacity_l',
        'consumption_l_per_100',
        'cruise_speed_kmh',
        'curb_weight_t',
        'restrictions',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'curb_weight_t' => 'decimal:2',
        'consumption_l_per_100' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }
}
