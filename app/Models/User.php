<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_DRIVER = 'driver';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_FLEET = 'fleet';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_DRIVER,
        self::ROLE_PROVIDER,
        self::ROLE_FLEET,
        self::ROLE_ADMIN,
    ];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'status',
        'avatar',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function routePlans()
    {
        return $this->hasMany(RoutePlan::class);
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function eventVotes()
    {
        return $this->hasMany(EventVote::class);
    }

    public function reportedEvents()
    {
        return $this->hasMany(RoadEvent::class, 'created_by_user_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function ownedFleets()
    {
        return $this->hasMany(Fleet::class, 'owner_id');
    }

    public function fleets()
    {
        return $this->belongsToMany(Fleet::class, 'fleet_drivers')
            ->withPivot('role_in_fleet')
            ->withTimestamps();
    }

    public function assignmentsAsDriver()
    {
        return $this->hasMany(RouteAssignment::class, 'driver_user_id');
    }

    public function cargos()
    {
        return $this->hasMany(Cargo::class);
    }

    public function favoritePois()
    {
        return $this->belongsToMany(ServiceObject::class, 'user_poi_favorites')->withTimestamps();
    }

    public function tripSessions()
    {
        return $this->hasMany(TripSession::class);
    }

    public function activityDays()
    {
        return $this->hasMany(UserActivityDay::class);
    }

    public function activeTripSession()
    {
        return $this->hasOne(TripSession::class)->where('status', 'active')->latest();
    }
}
