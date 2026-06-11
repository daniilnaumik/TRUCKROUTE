<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'origin' => [
                'label' => $this->origin,
                'point' => $this->origin_point,
            ],
            'destination' => [
                'label' => $this->destination,
                'point' => $this->destination_point,
            ],
            'via_points' => $this->via_points ?: [],
            'start_time' => optional($this->start_time)->toIso8601String(),
            'arrival_time' => optional($this->arrival_time)->toIso8601String(),

            'vehicle' => [
                'id' => $this->vehicle_id,
                'type' => $this->vehicle_type,
                'tank_capacity_l' => $this->tank_capacity_l,
                'consumption_l_per_100' => $this->consumption_l_per_100 !== null ? (float) $this->consumption_l_per_100 : null,
                'effective_consumption_l_per_100' => $this->effective_consumption_l_per_100 !== null ? (float) $this->effective_consumption_l_per_100 : null,
                'cruise_speed_kmh' => $this->cruise_speed_kmh,
                'curb_weight_t' => $this->vehicle_curb_weight_t !== null ? (float) $this->vehicle_curb_weight_t : null,
            ],
            'cargo' => [
                'type' => $this->cargo_type,
                'weight_t' => $this->cargo_weight_t !== null ? (float) $this->cargo_weight_t : null,
                'gross_weight_t' => $this->gross_weight_t !== null ? (float) $this->gross_weight_t : null,
            ],
            'fuel' => [
                'start_l' => $this->start_fuel_l,
                'needed_l' => $this->fuel_needed_l !== null ? (float) $this->fuel_needed_l : null,
                'cost_rub' => $this->fuel_cost_rub !== null ? (float) $this->fuel_cost_rub : null,
                'reserve_percent' => $this->reserve_percent,
                'reserve_l' => $this->reserve_l !== null ? (float) $this->reserve_l : null,
                'range_km_at_start' => $this->range_km,
            ],
            'planning_mode' => $this->planning_mode,
            'distance_km' => $this->distance_km,
            'drive_time_minutes' => $this->drive_time_minutes,
            'stops_count' => $this->stops_count,
            'recommendations_text' => $this->recommendations,

            'route' => [
                'provider' => $this->routing_provider,
                'polyline' => $this->polyline(), // [[lat,lng], ...]
            ],

            'stops' => RouteRecommendationResource::collection(
                $this->whenLoaded('recommendationsList'),
            ),

            'image_url' => $this->image ? asset('assets/images/'.$this->image) : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
