<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'highway' => $this->highway,
            'location' => $this->location,
            'description' => $this->description,
            'status' => $this->status,
            'importance' => $this->importance,
            'delay_minutes' => (int) $this->delay_minutes,
            'confidence_score' => (int) $this->confidence_score,
            'coordinates' => $this->lat !== null && $this->lng !== null
                ? ['lat' => (float) $this->lat, 'lng' => (float) $this->lng]
                : null,
            'image_url' => $this->mediaUrl($this->image),
            'gallery' => collect($this->gallery ?? [])->map(fn ($img) => $this->mediaUrl($img))->filter()->values(),
            'video_url' => $this->mediaUrl($this->video_url),
            'reported_at' => optional($this->reported_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'created_by_user_id' => $this->created_by_user_id,
            'user_vote' => $request->user()
                ? (int) ($this->votes()->where('user_id', $request->user()->id)->value('vote') ?? 0)
                : 0,
            'votes' => [
                'up' => (int) ($this->votes_up_count ?? $this->votesUp()),
                'down' => (int) ($this->votes_down_count ?? $this->votesDown()),
            ],
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

        if (str_starts_with($path, 'uploads/')) {
            return '/storage/'.$path;
        }

        return asset('assets/images/'.$path);
    }
}
