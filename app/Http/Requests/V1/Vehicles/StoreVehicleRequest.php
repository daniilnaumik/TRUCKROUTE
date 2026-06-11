<?php

namespace App\Http\Requests\V1\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:255'],
            'fuel_type' => ['nullable', 'string', 'max:50'],
            'allowed_fuel' => ['nullable', 'string', 'max:50'],
            'tank_capacity_l' => ['required', 'numeric', 'min:1', 'max:2000'],
            'consumption_l_per_100' => ['required', 'numeric', 'min:1', 'max:100'],
            'cruise_speed_kmh' => ['nullable', 'integer', 'min:30', 'max:120'],
            'curb_weight_t' => ['nullable', 'numeric', 'min:1', 'max:40'],
            'restrictions' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название транспорта',
            'type' => 'тип транспорта',
            'model' => 'модель транспорта',
            'fuel_type' => 'тип топлива',
            'tank_capacity_l' => 'объём бака',
            'consumption_l_per_100' => 'расход топлива',
            'cruise_speed_kmh' => 'крейсерская скорость',
            'curb_weight_t' => 'масса транспорта',
            'restrictions' => 'ограничения',
        ];
    }
}
