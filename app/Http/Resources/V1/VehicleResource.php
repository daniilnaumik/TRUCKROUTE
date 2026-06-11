<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'fleet_id' => $this->fleet_id,
            'owner_type' => $this->fleet_id ? 'fleet' : 'driver',
            'title' => $this->title,
            'type' => $this->type,
            'model' => $this->model,
            'fuel_type' => $this->fuel_type,
            'allowed_fuel' => $this->allowed_fuel,
            'tank_capacity_l' => $this->tank_capacity_l,
            'consumption_l_per_100' => $this->consumption_l_per_100 !== null ? (float) $this->consumption_l_per_100 : null,
            'cruise_speed_kmh' => $this->cruise_speed_kmh,
            'curb_weight_t' => $this->curb_weight_t !== null ? (float) $this->curb_weight_t : null,
            'restrictions' => $this->restrictions,
            'is_active' => (bool) $this->is_active,
            'image_url' => $this->image ? asset('assets/images/'.$this->image) : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
