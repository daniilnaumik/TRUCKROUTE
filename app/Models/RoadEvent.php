<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RoadEvent extends Model
{
    protected $fillable = [
        'title',
        'type',
        'highway',
        'location',
        'description',
        'status',
        'importance',
        'delay_minutes',
        'lat',
        'lng',
        'confidence_score',
        'image',
        'gallery',
        'video_url',
        'reported_at',
        'expires_at',
        'created_by_user_id',
    ];

    protected $hidden = ['location_point'];

    protected $casts = [
        'reported_at' => 'datetime',
        'expires_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'gallery' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (RoadEvent $event): void {
            self::syncLocationPoint($event);
        });

        static::updated(function (RoadEvent $event): void {
            if ($event->wasChanged(['lat', 'lng'])) {
                self::syncLocationPoint($event);
            }
        });
    }

    private static function syncLocationPoint(RoadEvent $event): void
    {
        if ($event->lat === null || $event->lng === null) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::update(
            'UPDATE road_events SET location_point = ST_GeomFromText(?) WHERE id = ?',
            [sprintf('POINT(%.7f %.7f)', (float) $event->lng, (float) $event->lat), $event->id],
        );
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function votes()
    {
        return $this->hasMany(EventVote::class);
    }

    public function votesUp(): int
    {
        return (int) $this->votes()->where('vote', '>', 0)->count();
    }

    public function votesDown(): int
    {
        return (int) $this->votes()->where('vote', '<', 0)->count();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'checking'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeVisible($query)
    {
        $threshold = (int) config('events.confidence.hide_threshold', 0);
        return $query->active()->where('confidence_score', '>=', $threshold);
    }

    public function scopeWithinBbox($query, float $west, float $south, float $east, float $north)
    {
        return $query
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$south, $north])
            ->whereBetween('lng', [$west, $east]);
    }

    public function scopeWithinRadius($query, float $lat, float $lng, float $radiusMeters)
    {
        $degApprox = $radiusMeters / 111_000;

        $query = $query
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$lat - $degApprox, $lat + $degApprox])
            ->whereBetween('lng', [$lng - $degApprox, $lng + $degApprox]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $query;
        }

        return $query->whereRaw(
            'ST_Distance_Sphere(location_point, POINT(?, ?)) <= ?',
            [$lng, $lat, $radiusMeters],
        );
    }
}
