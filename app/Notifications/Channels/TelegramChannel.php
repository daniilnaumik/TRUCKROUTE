<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $settings = $notifiable->relationLoaded('settings')
            ? $notifiable->settings
            : $notifiable->settings()->first();
        $chatId = $settings?->telegram_chat_id;
        $token = (string) config('services.telegram.bot_token', '');

        if (! $chatId || $token === '') {
            return;
        }

        $message = $notification->toTelegram($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        try {
            $response = Http::timeout(5)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ],
            );

            if (! $response->successful()) {
                Log::warning('TelegramChannel: non-OK response', [
                    'status' => $response->status(),
                    'user_id' => $notifiable->getKey(),
                ]);
            }
        } catch (\Throwable $error) {
            Log::warning('TelegramChannel: HTTP error', ['error' => $error->getMessage()]);
        }
    }
}
