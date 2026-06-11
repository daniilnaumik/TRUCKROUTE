<?php

namespace App\Notifications\Channels;

use App\Models\Device;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Канал доставки нотификаций через Firebase Cloud Messaging (HTTP v1 совместимый legacy endpoint).
 *
 * Принцип graceful degradation: если FCM_SERVER_KEY не задан или у пользователя нет
 * зарегистрированных устройств — канал логирует и тихо выходит, не ломая нотификацию.
 * Это позволяет уже сейчас держать 'fcm' в Notification::via() и подключить мобилку
 * без правок бекенда — достаточно положить ключ в .env.
 *
 * Нотификации должны реализовать метод `toFcm($notifiable): array` со структурой:
 *   ['title' => ..., 'body' => ..., 'data' => [...]]
 */
class FcmChannel
{
    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send';
    private const EXPO_ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $serverKey = (string) config('services.fcm.server_key', '');
        $payload = $notification->toFcm($notifiable);

        if (!is_array($payload) || empty($payload)) {
            return;
        }

        // Сбор всех актуальных токенов пользователя.
        $tokens = Device::query()
            ->where('user_id', $notifiable->getKey())
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::debug('FcmChannel: no devices for user', ['user_id' => $notifiable->getKey()]);
            return;
        }

        $expoTokens = array_values(array_filter(
            $tokens,
            fn (string $token) => str_starts_with($token, 'ExponentPushToken[')
                || str_starts_with($token, 'ExpoPushToken['),
        ));
        $fcmTokens = array_values(array_diff($tokens, $expoTokens));

        if ($expoTokens !== []) {
            $this->sendToExpo($expoTokens, $payload);
        }

        if ($fcmTokens === []) {
            return;
        }

        if ($serverKey === '') {
            Log::info('FcmChannel: FCM_SERVER_KEY not set — would send', [
                'user_id' => $notifiable->getKey(),
                'tokens_count' => count($fcmTokens),
                'payload' => $payload,
            ]);
            return;
        }

        $body = [
            'registration_ids' => $fcmTokens,
            'notification' => [
                'title' => $payload['title'] ?? 'TruckRoute',
                'body' => $payload['body'] ?? '',
            ],
            'data' => $payload['data'] ?? [],
            'priority' => $payload['priority'] ?? 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(5)->post(self::ENDPOINT, $body);

            if (!$response->ok()) {
                Log::warning('FcmChannel: non-OK response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            // Чистим протухшие токены, чтобы не слать в них снова.
            $results = $response->json('results', []);
            foreach ($results as $i => $res) {
                $error = $res['error'] ?? null;
                if (in_array($error, ['NotRegistered', 'InvalidRegistration'], true) && isset($fcmTokens[$i])) {
                    Device::where('fcm_token', $fcmTokens[$i])->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FcmChannel: HTTP error', ['error' => $e->getMessage()]);
        }
    }

    private function sendToExpo(array $tokens, array $payload): void
    {
        $messages = array_map(fn (string $token) => [
            'to' => $token,
            'title' => $payload['title'] ?? 'TruckRoute',
            'body' => $payload['body'] ?? '',
            'data' => $payload['data'] ?? [],
            'sound' => 'default',
            'priority' => $payload['priority'] ?? 'high',
        ], $tokens);

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->post(self::EXPO_ENDPOINT, $messages);

            if (! $response->ok()) {
                Log::warning('FcmChannel: Expo push non-OK response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            foreach ($response->json('data', []) as $index => $result) {
                if (($result['status'] ?? null) !== 'error') {
                    continue;
                }

                $error = $result['details']['error'] ?? null;
                if ($error === 'DeviceNotRegistered' && isset($tokens[$index])) {
                    Device::where('fcm_token', $tokens[$index])->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FcmChannel: Expo push HTTP error', ['error' => $e->getMessage()]);
        }
    }
}
