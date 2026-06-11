<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticle extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'content',
        'image', 'gallery', 'tags',
        'author_id', 'status', 'published_at',
    ];

    protected $casts = [
        'gallery'      => 'array',
        'tags'         => 'array',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }
}
