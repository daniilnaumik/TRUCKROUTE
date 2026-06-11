<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PoiResource;
use App\Models\ServiceObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoritesController extends Controller
{
    /** GET /api/v1/favorites — list user's favourite POIs. */
    public function index(Request $request): JsonResponse
    {
        $pois = $request->user()->favoritePois()->get();
        return response()->json(['data' => PoiResource::collection($pois)]);
    }

    /** POST /api/v1/favorites/{poi} — add to favourites. */
    public function store(Request $request, ServiceObject $poi): JsonResponse
    {
        $request->user()->favoritePois()->syncWithoutDetaching([$poi->id]);
        return response()->json(['message' => 'Добавлено в избранное.', 'is_favorite' => true]);
    }

    /** DELETE /api/v1/favorites/{poi} — remove from favourites. */
    public function destroy(Request $request, ServiceObject $poi): JsonResponse
    {
        $request->user()->favoritePois()->detach($poi->id);
        return response()->json(['message' => 'Удалено из избранного.', 'is_favorite' => false]);
    }

    /** GET /api/v1/favorites/ids — just the IDs (for bulk "is this favourited?" checks). */
    public function ids(Request $request): JsonResponse
    {
        $ids = $request->user()->favoritePois()->pluck('service_objects.id');
        return response()->json(['data' => $ids]);
    }
}
