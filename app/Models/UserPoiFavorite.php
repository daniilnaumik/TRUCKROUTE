<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPoiFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'service_object_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceObject(): BelongsTo
    {
        return $this->belongsTo(ServiceObject::class);
    }
}
