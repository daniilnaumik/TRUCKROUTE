<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\TelegramChannel;

class NotificationChannels
{
    public static function for(object $notifiable, bool $includeEmail = true): array
    {
        $settings = $notifiable->relationLoaded('settings')
            ? $notifiable->settings
            : $notifiable->settings()->first();

        $channels = ['database'];

        if ($includeEmail && ($settings?->email_notifications ?? true)) {
            $channels[] = 'mail';
        }
        if ($settings?->push_notifications ?? true) {
            $channels[] = FcmChannel::class;
        }
        if (($settings?->telegram_notifications ?? false) && $settings?->telegram_chat_id) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }
}
