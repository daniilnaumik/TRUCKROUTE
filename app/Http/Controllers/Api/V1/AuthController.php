<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Requests\V1\Auth\RegisterRequest;
use App\Http\Requests\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Регистрация водителя. Возвращает токен сразу, чтобы клиент мог логиниться без второго запроса.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'] ?? User::ROLE_DRIVER,
            'status' => 'active',
        ]);

        $token = $user->createToken($this->deviceName($request))->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Логин по email/password. Каждый клиент (web/mobile) получает свой именованный токен,
     * чтобы можно было отзывать сессии устройств по-отдельности.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => ['Аккаунт заблокирован.'],
            ]);
        }

        $token = $user->createToken($this->deviceName($request))->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Текущий пользователь по Bearer-токену.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * PATCH auth/profile — обновление имени, телефона, аватара.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * Отзывает только тот токен, которым пришёл запрос (одно устройство).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Выход выполнен.',
        ]);
    }

    private function deviceName(Request $request): string
    {
        $explicit = $request->input('device_name');

        if (is_string($explicit) && $explicit !== '') {
            return mb_substr($explicit, 0, 100);
        }

        $userAgent = (string) $request->userAgent();

        return $userAgent !== '' ? mb_substr($userAgent, 0, 100) : 'unknown-device';
    }
}
