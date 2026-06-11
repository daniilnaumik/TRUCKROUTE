<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = $request->user()->settings()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'incident_notifications' => true,
                'privacy_policy_accepted' => false,
                'data_processing_accepted' => false,
                'notification_radius_km' => 50,
                'share_route_history_with_fleet' => false,
                'email_notifications' => true,
                'push_notifications' => true,
                'telegram_notifications' => false,
            ]
        );

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'incident_notifications' => ['sometimes', 'boolean'],
            'privacy_policy_accepted' => ['sometimes', 'boolean'],
            'data_processing_accepted' => ['sometimes', 'boolean'],
            'notification_radius_km' => ['sometimes', 'integer', 'min:5', 'max:500'],
            'share_route_history_with_fleet' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'push_notifications' => ['sometimes', 'boolean'],
            'telegram_notifications' => ['sometimes', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:100'],
        ]);

        if (($validated['telegram_notifications'] ?? false) && empty($validated['telegram_chat_id'])) {
            return response()->json([
                'message' => 'Для Telegram-уведомлений укажите Chat ID.',
                'errors' => ['telegram_chat_id' => ['Укажите Telegram Chat ID.']],
            ], 422);
        }

        $settings = $request->user()->settings()->firstOrCreate(
            ['user_id' => $request->user()->id]
        );
        $settings->update($validated);

        return response()->json(['data' => $settings, 'message' => 'Настройки сохранены.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Текущий пароль указан неверно.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
            'password.min' => 'Новый пароль должен быть не короче 8 символов.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $settings = $user->settings()->firstOrCreate(['user_id' => $user->id]);
        $settings->update(['last_password_change_at' => now()]);

        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        return response()->json([
            'message' => 'Пароль изменён. Остальные активные сессии завершены.',
            'changed_at' => $settings->last_password_change_at?->toIso8601String(),
        ]);
    }
}
