<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventVote extends Model
{
    protected $fillable = ['road_event_id', 'user_id', 'vote'];

    protected $casts = [
        'vote' => 'integer',
    ];

    public function roadEvent()
    {
        return $this->belongsTo(RoadEvent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
