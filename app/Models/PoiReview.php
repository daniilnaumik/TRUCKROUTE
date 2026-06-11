<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoiReview extends Model
{
    protected $fillable = [
        'service_object_id',
        'user_id',
        'rating',
        'body',
        'owner_reply',
        'owner_replied_at',
        'owner_reply_user_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'owner_replied_at' => 'datetime',
    ];

    public function serviceObject(): BelongsTo
    {
        return $this->belongsTo(ServiceObject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_reply_user_id');
    }
}
