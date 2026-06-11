<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Routes\StoreRouteRequest;
use App\Http\Resources\V1\RouteResource;
use App\Models\Fleet;
use App\Models\RoutePlan;
use App\Services\RouteBuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutesController extends Controller
{
    public function __construct(private readonly RouteBuildService $builder)
    {
    }

    /**
     * GET /api/v1/routes — мои сохранённые маршруты (пагинация позже, пока latest).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $routes = $request->user()
            ->routePlans()
            ->with(['recommendationsList.serviceObject', 'vehicle'])
            ->latest()
            ->limit(50)
            ->get();

        return RouteResource::collection($routes);
    }

    /**
     * POST /api/v1/routes — построить и сохранить маршрут.
     */
    public function store(StoreRouteRequest $request): JsonResponse
    {
        try {
            $plan = $this->builder->build($request->user(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 503 ? 503 : 500);
        }

        return (new RouteResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/routes/{routePlan} — маршрут с остановками и полилинией.
     */
    public function show(Request $request, RoutePlan $routePlan): RouteResource
    {
        $this->authorizeView($request, $routePlan);
        $routePlan->load(['recommendationsList.serviceObject', 'vehicle']);

        return new RouteResource($routePlan);
    }

    /**
     * POST /api/v1/routes/{routePlan}/recalculate — пересобрать тот же маршрут заново
     * (например, изменились road_events или предпочтения).
     */
    public function recalculate(Request $request, RoutePlan $routePlan): JsonResponse
    {
        $this->authorizeOwnership($request, $routePlan);

        $overrides = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.planning_mode' => ['nullable', 'string', 'max:50'],
            'preferences.reserve_percent' => ['nullable', 'integer', 'min:0', 'max:80'],
            'preferences.continuous_drive_hours' => ['nullable', 'numeric', 'min:1', 'max:12'],
            'preferences.include_rest_stop' => ['nullable', 'boolean'],
            'preferences.preferred_fuel_brand' => ['nullable', 'string', 'max:50'],
            'preferences.lodging_type' => ['nullable', 'string', 'max:50'],
            'start_fuel_l' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'start_time' => ['nullable', 'date'],
        ]);

        try {
            $plan = $this->builder->rebuild($routePlan, $overrides);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return (new RouteResource($plan))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * DELETE /api/v1/routes/{routePlan}
     */
    public function destroy(Request $request, RoutePlan $routePlan): JsonResponse
    {
        $this->authorizeOwnership($request, $routePlan);
        $routePlan->delete();

        return response()->json(['message' => 'Маршрут удалён.']);
    }

    private function authorizeOwnership(Request $request, RoutePlan $routePlan): void
    {
        if ($routePlan->user_id !== $request->user()->id) {
            abort(403, 'Этот маршрут принадлежит другому пользователю.');
        }
    }

    private function authorizeView(Request $request, RoutePlan $routePlan): void
    {
        $actor = $request->user();

        if ($routePlan->user_id === $actor->id || $actor->role === 'admin') {
            return;
        }

        $driverAllowedSharing = (bool) $routePlan->user
            ?->settings()
            ->value('share_route_history_with_fleet');

        $actorOwnsDriversFleet = $driverAllowedSharing
            && Fleet::query()
                ->where('owner_id', $actor->id)
                ->whereHas('drivers', fn ($query) => $query->where('users.id', $routePlan->user_id))
                ->exists();

        if (! $actorOwnsDriversFleet) {
            abort(403, 'Водитель не разрешил автопарку просматривать этот маршрут.');
        }
    }
}
