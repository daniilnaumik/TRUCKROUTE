<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RouteResource;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FleetRouteHistoryController extends Controller
{
    public function index(Request $request, Fleet $fleet, User $driver): AnonymousResourceCollection
    {
        $actor = $request->user();
        if ($actor->role !== 'admin' && $fleet->owner_id !== $actor->id) {
            abort(403, 'История маршрутов доступна только владельцу автопарка.');
        }

        if (! $fleet->drivers()->where('users.id', $driver->id)->exists()) {
            abort(404, 'Водитель не состоит в этом автопарке.');
        }

        $allowed = (bool) $driver->settings()
            ->value('share_route_history_with_fleet');

        if (! $allowed) {
            abort(403, 'Водитель не разрешил передачу истории маршрутов автопарку.');
        }

        $routes = $driver->routePlans()
            ->latest()
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return RouteResource::collection($routes);
    }
}
