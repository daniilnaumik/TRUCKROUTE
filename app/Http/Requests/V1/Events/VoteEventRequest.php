<?php

namespace App\Http\Requests\V1\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoteEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vote' => ['required', Rule::in([-1, 1])],
        ];
    }

    public function messages(): array
    {
        return [
            'vote.in' => 'vote должен быть -1 (опровергаю) или 1 (подтверждаю).',
        ];
    }
}
