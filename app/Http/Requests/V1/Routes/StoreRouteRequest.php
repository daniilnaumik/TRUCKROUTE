<?php

namespace App\Http\Requests\V1\Routes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Каждая точка (origin/destination/via[*]) может прийти двумя способами:
 *   1) Строка адреса: "Москва"            — будет геокодирована на бэке.
 *   2) Объект {lat,lng,label?}             — координаты уже известны фронту/мобилке.
 *
 * Профиль фуры либо ссылкой на сохранённую (vehicle_id), либо инлайн-объектом (vehicle).
 */
class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'origin' => ['required'],
            'destination' => ['required'],
            'via' => ['nullable', 'array', 'max:8'],
            'via.*' => ['required'],

            'start_time' => ['nullable', 'date'],
            'start_fuel_l' => ['required', 'numeric', 'min:0', 'max:2000'],

            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id', 'required_without:vehicle'],
            'vehicle' => ['nullable', 'array', 'required_without:vehicle_id'],
            'vehicle.type' => ['required_with:vehicle', 'string', 'max:100'],
            'vehicle.model' => ['nullable', 'string', 'max:255'],
            'vehicle.fuel_type' => ['nullable', 'string', 'max:50'],
            'vehicle.allowed_fuel' => ['nullable', 'string', 'max:50'],
            'vehicle.tank_capacity_l' => ['required_with:vehicle', 'numeric', 'min:1', 'max:2000'],
            'vehicle.consumption_l_per_100' => ['required_with:vehicle', 'numeric', 'min:1', 'max:100'],
            'vehicle.cruise_speed_kmh' => ['nullable', 'integer', 'min:30', 'max:120'],
            'vehicle.curb_weight_t' => ['nullable', 'numeric', 'min:1', 'max:40'],
            'vehicle.restrictions' => ['nullable', 'string', 'max:255'],

            'cargo' => ['nullable', 'array'],
            'cargo.weight_t' => ['nullable', 'numeric', 'min:0', 'max:45'],
            'cargo.flag' => ['nullable', 'string', 'max:50'],
            'cargo.requirements' => ['nullable', 'string', 'max:255'],

            'preferences' => ['nullable', 'array'],
            'preferences.preferred_fuel_brand' => ['nullable', 'string', 'max:50'],
            'preferences.lodging_type' => ['nullable', 'string', 'max:50'],
            'preferences.planning_mode' => ['nullable', 'string', 'max:50'],
            'preferences.continuous_drive_hours' => ['nullable', 'numeric', 'min:1', 'max:12'],
            'preferences.reserve_percent' => ['nullable', 'integer', 'min:0', 'max:80'],
            'preferences.include_rest_stop' => ['nullable', 'boolean'],
            'preferences.night_budget_rub' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'preferences.selected_rest_object_id' => ['nullable', 'integer', 'exists:service_objects,id'],
            'preferences.no_toll_roads' => ['nullable', 'in:Да,Нет'],

            'selected_poi_ids'   => ['nullable', 'array'],
            'selected_poi_ids.*' => ['integer', 'exists:service_objects,id'],
            'route_poi_ids'      => ['nullable', 'array', 'max:8'],
            'route_poi_ids.*'    => ['integer', 'exists:service_objects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'origin.required' => 'Укажите точку отправления (строка адреса или {lat,lng}).',
            'destination.required' => 'Укажите точку назначения.',
            'vehicle_id.required_without' => 'Передайте vehicle_id сохранённой фуры или inline-объект vehicle.',
            'vehicle.required_without' => 'Передайте vehicle_id сохранённой фуры или inline-объект vehicle.',
            'via.max' => 'Слишком много транзитных точек (максимум 8).',
        ];
    }
}
