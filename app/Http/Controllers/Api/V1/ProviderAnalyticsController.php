<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PoiReview;
use App\Models\PoiRouteSelection;
use App\Models\ServiceObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderAnalyticsController extends Controller
{
    /**
     * GET /api/v1/provider/analytics
     * Aggregated stats for all POI belonging to this provider.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $pois   = ServiceObject::where('provider_id', $userId)
            ->withAvg('reviews', 'rating')
            ->get();
        $poiIds = $pois->pluck('id');

        $totalAccepts = PoiRouteSelection::whereIn('service_object_id', $poiIds)
            ->where('action', 'accepted')->count();

        $totalRejects = PoiRouteSelection::whereIn('service_object_id', $poiIds)
            ->where('action', 'rejected')->count();

        $reviewsQuery = PoiReview::whereIn('service_object_id', $poiIds);
        $totalViews = (int) $pois->sum('view_count');
        $totalReviews = (clone $reviewsQuery)->count();
        $avgRating = (clone $reviewsQuery)->avg('rating');

        // Selections per day for the last 30 days
        $byDay = PoiRouteSelection::whereIn('service_object_id', $poiIds)
            ->where('action', 'accepted')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        // Per-POI breakdown
        $poiStats = $pois->map(function ($poi) {
            $accepts = PoiRouteSelection::where('service_object_id', $poi->id)
                ->where('action', 'accepted')->count();
            $rejects = PoiRouteSelection::where('service_object_id', $poi->id)
                ->where('action', 'rejected')->count();
            return [
                'id'               => $poi->id,
                'name'             => $poi->name,
                'type'             => $poi->type,
                'status'           => $poi->status,
                'verified'         => (bool) $poi->verified,
                'view_count'       => (int) $poi->view_count,
                'selections_count' => (int) $poi->selections_count,
                'accepts'          => $accepts,
                'rejects'          => $rejects,
                'rating'           => $poi->reviews_avg_rating !== null
                    ? round((float) $poi->reviews_avg_rating, 2)
                    : null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_poi'     => $pois->count(),
                    'total_accepts' => $totalAccepts,
                    'total_rejects' => $totalRejects,
                    'total_views'   => $totalViews,
                    'total_reviews' => $totalReviews,
                    'avg_rating'    => $avgRating !== null ? round((float) $avgRating, 2) : null,
                ],
                'selections_by_day' => $byDay,
                'poi_stats'         => $poiStats,
            ],
        ]);
    }

    /**
     * GET /api/v1/provider/analytics/{poi}
     * Detailed stats for a single POI.
     */
    public function show(Request $request, ServiceObject $poi): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $poi->provider_id !== $request->user()->id) {
            abort(403, 'Этот POI принадлежит другому провайдеру.');
        }

        $byDay = PoiRouteSelection::where('service_object_id', $poi->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                'action',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($rows, $date) {
                $accepted = $rows->where('action', 'accepted')->first()?->count ?? 0;
                $rejected = $rows->where('action', 'rejected')->first()?->count ?? 0;
                return ['date' => $date, 'accepted' => (int) $accepted, 'rejected' => (int) $rejected];
            })
            ->values();

        $totalAccepts = PoiRouteSelection::where('service_object_id', $poi->id)
            ->where('action', 'accepted')->count();
        $totalRejects = PoiRouteSelection::where('service_object_id', $poi->id)
            ->where('action', 'rejected')->count();

        return response()->json([
            'data' => [
                'poi'              => [
                    'id'         => $poi->id,
                    'name'       => $poi->name,
                    'type'       => $poi->type,
                    'location'   => $poi->location,
                    'view_count' => (int) $poi->view_count,
                    'rating'     => $poi->rating !== null ? (float) $poi->rating : null,
                    'status'     => $poi->status,
                    'verified'   => (bool) $poi->verified,
                ],
                'totals'           => [
                    'accepts' => $totalAccepts,
                    'rejects' => $totalRejects,
                ],
                'timeline_30d'     => $byDay,
            ],
        ]);
    }
}
