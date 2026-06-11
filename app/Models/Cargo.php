<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cargo extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'flag',
        'weight_t',
        'requirements',
    ];

    protected $casts = [
        'weight_t' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
