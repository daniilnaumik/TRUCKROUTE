<?php

namespace App\Http\Requests\V1\Poi;

use App\Models\DictionaryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderPoiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $poiTypes = DictionaryItem::query()
            ->active()
            ->where('dictionary', 'poi_categories')
            ->pluck('value')
            ->all();

        return [
            'name' => [$required, 'string', 'min:2', 'max:150'],
            'type' => [$required, 'string', Rule::in($poiTypes)],
            'location' => [$required, 'string', 'max:255'],
            'highway' => ['nullable', 'string', 'max:100'],
            'km_marker' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'brand' => ['nullable', 'string', 'max:80'],
            'fuel_price' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'has_truck_parking' => ['nullable', 'boolean'],
            'detour_km' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'services' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['string'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'content' => ['nullable', 'string'],
            'working_hours' => ['nullable', 'array'],
            'working_hours.*' => ['nullable', 'string', 'max:100'],
            'contacts' => ['nullable', 'array'],
            'contacts.phone' => ['nullable', 'string', 'max:80'],
            'contacts.email' => ['nullable', 'email', 'max:150'],
            'contacts.website' => ['nullable', 'url', 'max:255'],
            'contacts.messenger' => ['nullable', 'string', 'max:150'],
            'price_details' => ['nullable', 'array', 'max:30'],
            'price_details.*.name' => ['required_with:price_details', 'string', 'max:120'],
            'price_details.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'price_details.*.unit' => ['nullable', 'string', 'max:40'],
            'price_details.*.note' => ['nullable', 'string', 'max:200'],
            'promotions' => ['nullable', 'array', 'max:20'],
            'promotions.*.title' => ['required_with:promotions', 'string', 'max:150'],
            'promotions.*.description' => ['nullable', 'string', 'max:500'],
            'promotions.*.valid_until' => ['nullable', 'date'],
            'truck_access' => ['nullable', 'array'],
            'truck_access.allowed' => ['nullable', 'boolean'],
            'truck_access.max_height_m' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'truck_access.max_length_m' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'truck_access.max_weight_t' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'truck_access.surface' => ['nullable', 'string', 'max:100'],
            'truck_access.turnaround' => ['nullable', 'boolean'],
            'truck_access.parking_spaces' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'truck_access.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Выберите активную категорию объекта из справочника.',
        ];
    }
}
