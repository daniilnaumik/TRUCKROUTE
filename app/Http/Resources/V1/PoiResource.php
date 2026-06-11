<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'brand' => $this->brand,
            'highway' => $this->highway,
            'km_marker' => $this->km_marker,
            'location' => $this->location,
            'coordinates' => $this->lat !== null && $this->lng !== null
                ? ['lat' => (float) $this->lat, 'lng' => (float) $this->lng]
                : null,
            'description' => $this->description,
            'services' => $this->services,
            'fuel_price' => $this->fuel_price,
            'has_truck_parking' => (bool) $this->has_truck_parking,
            'detour_km' => (float) $this->detour_km,
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'status' => $this->status,
            'verified' => (bool) $this->verified,
            'image_url' => $this->mediaUrl($this->image),
            // distance_m появляется при поиске по радиусу — пробрасываем как helper.
            'distance_m' => isset($this->distance_m) ? (int) round($this->distance_m) : null,
            'distance_km' => isset($this->distance_m) ? round($this->distance_m / 1000, 2) : null,
            'gallery'    => collect($this->gallery ?? [])->map(fn ($img) => $this->mediaUrl($img))->filter()->values(),
            'video_url'  => $this->mediaUrl($this->video_url),
            'tags'       => $this->tags ?? [],
            'content'    => $this->content,
            'working_hours' => $this->working_hours ?? [],
            'contacts' => $this->contacts ?? [],
            'price_details' => $this->price_details ?? [],
            'promotions' => $this->promotions ?? [],
            'truck_access' => $this->truck_access ?? [],
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'reviews' => PoiReviewResource::collection($this->whenLoaded('reviews')),
            'provider_id' => $this->provider_id,
            'view_count' => (int) $this->view_count,
            'selections_count' => (int) ($this->selections_count ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        if (str_starts_with($path, 'uploads/')) {
            return '/storage/'.$path;
        }

        if (str_starts_with($path, '/assets/')) {
            return $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        return asset('assets/images/'.$path);
    }
}
