<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'incident_notifications',
        'privacy_policy_accepted',
        'data_processing_accepted',
        'notification_radius_km',
        'last_password_change_at',
        'share_route_history_with_fleet',
        'email_notifications',
        'push_notifications',
        'telegram_notifications',
        'telegram_chat_id',
    ];

    protected $casts = [
        'incident_notifications' => 'boolean',
        'privacy_policy_accepted' => 'boolean',
        'data_processing_accepted' => 'boolean',
        'share_route_history_with_fleet' => 'boolean',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'telegram_notifications' => 'boolean',
        'last_password_change_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
