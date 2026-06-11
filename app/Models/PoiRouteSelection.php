<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoiRouteSelection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_session_id',
        'service_object_id',
        'action',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function tripSession(): BelongsTo
    {
        return $this->belongsTo(TripSession::class);
    }

    public function serviceObject(): BelongsTo
    {
        return $this->belongsTo(ServiceObject::class);
    }
}
