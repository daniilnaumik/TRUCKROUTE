<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Poi\StoreProviderPoiRequest;
use App\Http\Resources\V1\PoiResource;
use App\Models\ServiceObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderPoiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = ServiceObject::where('provider_id', $request->user()->id)
            ->withCount('reviews')
            ->orderByDesc('created_at')
            ->paginate(20);

        return PoiResource::collection($items);
    }

    public function store(StoreProviderPoiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['description'] = $this->descriptionFromPayload($data);
        $data['provider_id'] = $request->user()->id;
        $data['status'] = 'moderation';
        $data['verified'] = false;

        $poi = ServiceObject::create($data);

        return (new PoiResource($poi))->response()->setStatusCode(201);
    }

    public function show(Request $request, ServiceObject $poi): JsonResponse
    {
        $this->authorizeOwnership($request, $poi);

        $poi->load('reviews.user:id,name,avatar')->loadCount('reviews');

        return response()->json(['data' => new PoiResource($poi)]);
    }

    public function update(StoreProviderPoiRequest $request, ServiceObject $poi): JsonResponse
    {
        $this->authorizeOwnership($request, $poi);

        $data = $request->validated();
        if (array_key_exists('description', $data)
            || array_key_exists('content', $data)
            || array_key_exists('services', $data)
            || array_key_exists('location', $data)) {
            $data['description'] = $this->descriptionFromPayload($data, $poi->description);
        }

        if ($poi->verified) {
            $data['verified'] = false;
            $data['status'] = 'moderation';
        }

        $poi->update($data);

        return response()->json(['data' => new PoiResource($poi->fresh())]);
    }

    public function destroy(Request $request, ServiceObject $poi): JsonResponse
    {
        $this->authorizeOwnership($request, $poi);
        $poi->delete();

        return response()->json(['message' => 'POI удален.']);
    }

    public function stats(Request $request, ServiceObject $poi): JsonResponse
    {
        $this->authorizeOwnership($request, $poi);

        return response()->json([
            'data' => [
                'poi_id' => $poi->id,
                'name' => $poi->name,
                'status' => $poi->status,
                'verified' => (bool) $poi->verified,
                'view_count' => (int) $poi->view_count,
                'rating' => $poi->rating !== null ? (float) $poi->rating : null,
                'created_at' => optional($poi->created_at)->toIso8601String(),
            ],
        ]);
    }

    private function authorizeOwnership(Request $request, ServiceObject $poi): void
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $poi->provider_id !== $user->id) {
            abort(403, 'Этот POI принадлежит другому провайдеру.');
        }
    }

    private function descriptionFromPayload(array $data, ?string $fallback = null): string
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $content = trim(strip_tags((string) ($data['content'] ?? '')));
        if ($content !== '') {
            return mb_substr($content, 0, 2000);
        }

        $services = trim((string) ($data['services'] ?? ''));
        if ($services !== '') {
            return $services;
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            return 'Объект сервиса на маршруте: '.$location;
        }

        return $fallback ?: 'Объект сервиса TruckRoute.';
    }
}
