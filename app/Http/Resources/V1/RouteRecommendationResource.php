<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteRecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'order_index' => $this->order_index,
            'distance_from_start_km' => $this->distance_from_start_km,
            'detour_km' => (float) $this->detour_km,
            'eta_at' => optional($this->eta_at)->toIso8601String(),
            'fuel_before_l' => $this->fuel_before_l !== null ? (float) $this->fuel_before_l : null,
            'suggested_fuel_l' => $this->suggested_fuel_l !== null ? (float) $this->suggested_fuel_l : null,
            'note' => $this->note,
            'poi' => $this->whenLoaded('serviceObject', fn () => new PoiResource($this->serviceObject)),
        ];
    }
}
