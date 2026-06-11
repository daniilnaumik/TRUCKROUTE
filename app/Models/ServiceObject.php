<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceObject extends Model
{
    protected $fillable = [
        'name',
        'type',
        'highway',
        'km_marker',
        'brand',
        'fuel_price',
        'has_truck_parking',
        'detour_km',
        'location',
        'lat',
        'lng',
        'provider_id',
        'description',
        'services',
        'status',
        'verified',
        'rating',
        'image',
        'view_count',
        'selections_count',
        'gallery',
        'video_url',
        'tags',
        'content',
        'working_hours',
        'contacts',
        'price_details',
        'promotions',
        'truck_access',
    ];

    protected $casts = [
        'fuel_price'       => 'decimal:2',
        'has_truck_parking' => 'boolean',
        'detour_km'        => 'decimal:1',
        'lat'              => 'float',
        'lng'              => 'float',
        'verified'         => 'boolean',
        'rating'           => 'float',
        'gallery'          => 'array',
        'tags'             => 'array',
        'working_hours'    => 'array',
        'contacts'         => 'array',
        'price_details'    => 'array',
        'promotions'       => 'array',
        'truck_access'     => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PoiReview::class)->latest();
    }

    public function scopeWithinBbox($query, float $west, float $south, float $east, float $north)
    {
        return $query
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$south, $north])
            ->whereBetween('lng', [$west, $east]);
    }
}
