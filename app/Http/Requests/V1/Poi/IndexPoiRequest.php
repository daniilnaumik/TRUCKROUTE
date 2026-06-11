<?php

namespace App\Http\Requests\V1\Poi;

use Illuminate\Foundation\Http\FormRequest;

class IndexPoiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasBbox = $this->filled('bbox');
        $hasPoint = $this->filled('lat') || $this->filled('lng');

        return [
            'lat' => [$hasBbox ? 'prohibited' : 'nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => [$hasBbox ? 'prohibited' : 'nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:'.config('geo.defaults.max_radius_km', 200)],
            'bbox' => [$hasPoint ? 'prohibited' : 'nullable', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'type' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:50'],
            'verified' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required_with' => 'Передайте lat вместе с lng.',
            'lng.required_with' => 'Передайте lng вместе с lat.',
            'bbox.prohibited' => 'Используйте либо точку (lat/lng), либо bbox, не оба режима сразу.',
            'lat.prohibited' => 'Используйте либо точку (lat/lng), либо bbox, не оба режима сразу.',
            'lng.prohibited' => 'Используйте либо точку (lat/lng), либо bbox, не оба режима сразу.',
            'bbox.regex' => 'bbox должен быть строкой "west,south,east,north".',
        ];
    }

    /**
     * @return array<int, string>|null
     */
    public function types(): ?array
    {
        $raw = $this->input('type');
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $types = array_filter(array_map('trim', explode(',', $raw)));
        return $types === [] ? null : array_values($types);
    }

    /**
     * @return array{west: float, south: float, east: float, north: float}|null
     */
    public function bbox(): ?array
    {
        $raw = $this->input('bbox');
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        [$west, $south, $east, $north] = array_map('floatval', explode(',', $raw));
        if ($west >= $east || $south >= $north) {
            return null;
        }

        return compact('west', 'south', 'east', 'north');
    }
}
