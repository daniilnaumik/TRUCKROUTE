<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'excerpt'      => $this->excerpt,
            'content'      => $this->content,
            'image'        => $this->image,
            'image_url'    => $this->mediaUrl($this->image),
            'gallery'      => collect($this->gallery ?? [])->map(fn($img) => $this->mediaUrl($img))->filter()->values(),
            'tags'         => $this->tags ?? [],
            'status'       => $this->status,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'created_at'   => optional($this->created_at)->toIso8601String(),
            'author'       => $this->whenLoaded('author', fn() => [
                'id'   => $this->author->id,
                'name' => $this->author->name,
            ]),
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

        if (str_starts_with($path, 'uploads/')) {
            return '/storage/'.$path;
        }

        return asset('assets/images/'.$path);
    }
}
