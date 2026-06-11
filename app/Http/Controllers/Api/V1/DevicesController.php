<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Регистрация устройств пользователя для пуш-уведомлений.
 * Поведение idempotent: один и тот же fcm_token не задвоится, при необходимости
 * переезжает к новому пользователю (когда юзер перелогинился на чужом девайсе).
 */
class DevicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->devices()->latest()->get();
        return response()->json(['data' => $devices]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'min:10', 'max:512'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $device = Device::updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['data' => $device], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403, 'Это устройство принадлежит другому пользователю.');
        }
        $device->delete();
        return response()->json(['message' => 'Устройство отвязано.']);
    }
}
