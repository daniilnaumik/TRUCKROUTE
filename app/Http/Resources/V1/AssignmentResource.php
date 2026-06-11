<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'driver_user_id' => $this->driver_user_id,
            'issued_by_user_id' => $this->issued_by_user_id,
            'route_plan_id' => $this->route_plan_id,
            'vehicle_source' => $this->vehicle_source ?? 'driver',
            'vehicle_id' => $this->vehicle_id,
            'origin' => $this->origin,
            'origin_point' => $this->origin_point,
            'destination' => $this->destination,
            'destination_point' => $this->destination_point,
            'via_points' => $this->via_points,
            'planned_start_at' => optional($this->planned_start_at)->toIso8601String(),
            'comment' => $this->comment,
            'status' => $this->status,
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'rating_stars' => $this->rating_stars,
            'rating_comment' => $this->rating_comment,
            'rated_at' => optional($this->rated_at)->toIso8601String(),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'title' => $this->vehicle->title,
                'type' => $this->vehicle->type,
                'model' => $this->vehicle->model,
                'tank_capacity_l' => $this->vehicle->tank_capacity_l,
                'consumption_l_per_100' => (float) $this->vehicle->consumption_l_per_100,
            ] : null),
            'fleet' => $this->whenLoaded('fleet', fn () => [
                'id' => $this->fleet->id,
                'name' => $this->fleet->name,
                'inn' => $this->fleet->inn,
                'phone' => $this->fleet->phone,
                'base_city' => $this->fleet->base_city,
                'address' => $this->fleet->address,
                'description' => $this->fleet->description,
                'avatar_url' => $this->fleetAvatarUrl($this->fleet->avatar),
                'owner' => $this->fleet->relationLoaded('owner') && $this->fleet->owner ? [
                    'id' => $this->fleet->owner->id,
                    'name' => $this->fleet->owner->name,
                ] : null,
            ]),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function fleetAvatarUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        if (str_starts_with($path, 'uploads/')) {
            return '/storage/'.$path;
        }

        return asset('storage/'.$path);
    }
}
