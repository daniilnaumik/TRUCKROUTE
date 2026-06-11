<?php

namespace App\Http\Requests\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // admin через публичный API нельзя, но driver/provider/fleet — можно.
            'role' => ['nullable', Rule::in([User::ROLE_DRIVER, User::ROLE_PROVIDER, User::ROLE_FLEET])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'     => 'Этот email уже зарегистрирован.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
            'password.min'     => 'Пароль должен быть не короче 8 символов.',
            'role.in'          => 'Недопустимая роль. Выберите: driver, provider или fleet.',
        ];
    }
}
