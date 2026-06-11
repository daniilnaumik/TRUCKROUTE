<?php

namespace App\Http\Requests\V1\Events;

use Illuminate\Foundation\Http\FormRequest;

class IndexEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bbox'     => ['nullable', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            // corridor — ID сохранённого маршрута; вернёт события в полосе corridor_km вдоль него.
            'corridor' => ['nullable', 'integer', 'min:1'],
            'highway'  => ['nullable', 'string', 'max:100'],
            'type'     => ['nullable', 'string', 'max:50'],
            'status'   => ['nullable', 'in:active,checking,rejected,expired,feed,all'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'limit'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
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
        return ($west < $east && $south < $north) ? compact('west', 'south', 'east', 'north') : null;
    }
}
