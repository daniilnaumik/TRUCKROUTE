<?php

namespace App\Http\Requests\V1\Events;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'highway' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'importance' => ['nullable', 'in:low,medium,high'],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'image' => ['nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['string', 'max:255'],
            'video_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
