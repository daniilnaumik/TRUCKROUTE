<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fleet extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'inn',
        'phone',
        'base_city',
        'address',
        'description',
        'avatar',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function drivers()
    {
        return $this->belongsToMany(User::class, 'fleet_drivers')
            ->withPivot('role_in_fleet')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(RouteAssignment::class);
    }

    public function completedAssignments()
    {
        return $this->hasMany(RouteAssignment::class)->where('status', 'completed');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
