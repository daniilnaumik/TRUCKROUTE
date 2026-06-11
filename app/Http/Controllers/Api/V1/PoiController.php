<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Poi\IndexPoiRequest;
use App\Http\Resources\V1\PoiResource;
use App\Models\ServiceObject;
use App\Services\PoiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PoiController extends Controller
{
    public function __construct(private readonly PoiSearchService $search)
    {
    }

    /**
     * GET /api/v1/poi
     *
     * Два режима:
     *   - ?lat=&lng=&radius_km=  — поиск по радиусу вокруг точки
     *   - ?bbox=west,south,east,north — все POI в прямоугольнике (для viewport карты)
     * Опционально: ?type=АЗС или ?type=АЗС,Стоянка, ?brand=Лукойл, ?verified=true, ?limit=50.
     */
    public function index(IndexPoiRequest $request): AnonymousResourceCollection
    {
        $types = $request->types();
        $brand = $request->input('brand');
        $verified = $request->filled('verified') ? $request->boolean('verified') : null;
        $limit = (int) $request->input('limit', 50);

        if ($request->filled('bbox')) {
            $bbox = $request->bbox();
            $items = $this->search->searchInBbox(
                west: $bbox['west'],
                south: $bbox['south'],
                east: $bbox['east'],
                north: $bbox['north'],
                types: $types,
                brand: $brand,
                verified: $verified,
                limit: $limit,
            );
            return PoiResource::collection($items)->additional([
                'meta' => ['mode' => 'bbox', 'bbox' => $bbox, 'count' => $items->count()],
            ]);
        }

        if (!$request->filled('lat') && !$request->filled('lng')) {
            $query = ServiceObject::query()
                ->whereNotNull('lat')
                ->whereNotNull('lng');

            if (!empty($types)) {
                $query->whereIn('type', $types);
            }
            if ($brand !== null && $brand !== '') {
                $query->where('brand', 'like', '%'.$brand.'%');
            }
            if ($verified !== null) {
                $query->where('verified', $verified);
            }

            $items = $query
                ->withCount('reviews')
                ->orderByDesc('verified')
                ->orderByDesc('rating')
                ->limit(min(200, max(1, $limit)))
                ->get();

            return PoiResource::collection($items)->additional([
                'meta' => ['mode' => 'list', 'count' => $items->count()],
            ]);
        }

        $radiusKm = (float) $request->input('radius_km', config('geo.defaults.search_radius_km', 30));
        $items = $this->search->searchAroundPoint(
            lat: (float) $request->input('lat'),
            lng: (float) $request->input('lng'),
            radiusKm: $radiusKm,
            types: $types,
            brand: $brand,
            verified: $verified,
            limit: $limit,
        );

        return PoiResource::collection($items)->additional([
            'meta' => [
                'mode' => 'radius',
                'lat' => (float) $request->input('lat'),
                'lng' => (float) $request->input('lng'),
                'radius_km' => $radiusKm,
                'count' => $items->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/poi/along-route
     * Returns POI within corridor_km of the given polyline.
     * polyline: JSON array of [[lat,lng],...] or [{lat,lng},...]
     */
    public function alongRoute(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'polyline'    => ['required', 'string'],
            'corridor_km' => ['nullable', 'numeric', 'between:1,50'],
            'type'        => ['nullable', 'string'],
        ]);

        $raw = json_decode($request->input('polyline'), true);
        if (!is_array($raw) || empty($raw)) {
            return PoiResource::collection(collect([]));
        }

        // Normalise to [{lat, lng}] regardless of input format
        $normalized = [];
        foreach ($raw as $p) {
            if (isset($p['lat'], $p['lng'])) {
                $normalized[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
            } elseif (is_array($p) && count($p) >= 2) {
                $normalized[] = ['lat' => (float) $p[0], 'lng' => (float) $p[1]];
            }
        }

        if (empty($normalized)) {
            return PoiResource::collection(collect([]));
        }

        $corridorKm = (float) $request->input('corridor_km', 5.0);
        $types      = $request->filled('type')
            ? array_map('trim', explode(',', $request->input('type')))
            : null;

        $items = $this->search->searchAlongRoute($normalized, $corridorKm, $types);

        return PoiResource::collection($items)->additional([
            'meta' => [
                'mode'        => 'along-route',
                'corridor_km' => $corridorKm,
                'count'       => $items->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/poi/{id}
     */
    public function show(ServiceObject $poi): JsonResponse
    {
        $poi->increment('view_count');
        $poi->load([
            'reviews.user:id,name,avatar',
        ])->loadCount('reviews');

        return response()->json([
            'data' => new PoiResource($poi),
        ]);
    }
}
