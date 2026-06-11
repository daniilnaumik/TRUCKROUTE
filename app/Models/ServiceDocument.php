<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceDocument extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'version',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
