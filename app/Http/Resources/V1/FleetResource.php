<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'inn' => $this->inn,
            'phone' => $this->phone,
            'base_city' => $this->base_city,
            'address' => $this->address,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'avatar_url' => $this->mediaUrl($this->avatar),
            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'is_owner' => $request->user()
                ? (int) $this->owner_id === (int) $request->user()->id
                : false,
            'drivers_count' => $this->whenCounted('drivers'),
            'assignments_count' => $this->whenCounted('assignments'),
            'completed_assignments_count' => $this->whenCounted('completedAssignments'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    private function mediaUrl(?string $path): ?string
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
