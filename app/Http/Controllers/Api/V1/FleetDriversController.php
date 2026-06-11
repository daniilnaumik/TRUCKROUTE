<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fleet;
use App\Models\RouteAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetDriversController extends Controller
{
    /**
     * GET /api/v1/fleets/{fleet}/drivers
     */
    public function index(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeFleetAccess($request, $fleet);

        $drivers = $fleet->drivers()->select('users.id', 'users.name', 'users.phone', 'users.status', 'users.avatar')
            ->where('users.id', '<>', $fleet->owner_id)
            ->withPivot('role_in_fleet')
            ->get();

        $stats = RouteAssignment::query()
            ->where('fleet_id', $fleet->id)
            ->whereIn('driver_user_id', $drivers->pluck('id'))
            ->where('status', 'completed')
            ->selectRaw('driver_user_id, COUNT(*) as completed_count, AVG(rating_stars) as rating_avg, COUNT(rating_stars) as rating_count')
            ->groupBy('driver_user_id')
            ->get()
            ->keyBy('driver_user_id');

        $drivers = $drivers
            ->map(function ($d) use ($stats) {
                $stat = $stats->get($d->id);

                return [
                'id' => $d->id,
                'name' => $d->name,
                    'phone' => $d->phone,
                    'status' => $d->status,
                    'avatar_url' => $d->avatar ? asset('storage/'.$d->avatar) : null,
                    'role_in_fleet' => $d->pivot->role_in_fleet,
                    'completed_assignments_count' => (int) ($stat?->completed_count ?? 0),
                    'rating_avg' => $stat?->rating_avg !== null ? round((float) $stat->rating_avg, 1) : null,
                    'rating_count' => (int) ($stat?->rating_count ?? 0),
                ];
            });

        return response()->json(['data' => $drivers, 'meta' => ['count' => $drivers->count()]]);
    }

    /**
     * POST /api/v1/fleets/{fleet}/drivers — привязать водителя по user_id.
     */
    public function attach(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_in_fleet' => ['nullable', 'string', 'in:driver,dispatcher'],
        ]);

        $driver = User::findOrFail($data['user_id']);

        if ((int) $fleet->owner_id === (int) $driver->id) {
            $fleet->drivers()->detach($driver->id);
            return response()->json(['message' => 'Владельца автопарка нельзя добавить в этот же автопарк как водителя.'], 422);
        }

        if (!in_array($driver->role, [User::ROLE_DRIVER, User::ROLE_FLEET], true)) {
            return response()->json(['message' => 'Пользователь не является водителем или участником парка.'], 422);
        }

        if ($fleet->drivers()->where('user_id', $driver->id)->exists()) {
            return response()->json(['message' => 'Водитель уже добавлен в этот автопарк.'], 409);
        }

        $fleet->drivers()->attach($driver->id, [
            'role_in_fleet' => $data['role_in_fleet'] ?? 'driver',
        ]);

        return response()->json([
            'message' => 'Водитель добавлен в автопарк.',
            'data' => ['user_id' => $driver->id, 'name' => $driver->name],
        ], 201);
    }

    /**
     * DELETE /api/v1/fleets/{fleet}/drivers/{user}
     */
    public function detach(Request $request, Fleet $fleet, User $user): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);

        if (!$fleet->drivers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Водитель не найден в этом автопарке.'], 404);
        }

        $fleet->drivers()->detach($user->id);
        return response()->json(['message' => 'Водитель исключён из автопарка.']);
    }

    private function authorizeFleetAccess(Request $request, Fleet $fleet): void
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return;
        }
        // Менеджер парка или водитель в составе парка
        if ($fleet->owner_id === $user->id) {
            return;
        }
        if ($fleet->drivers()->where('user_id', $user->id)->exists()) {
            return;
        }
        abort(403, 'Нет доступа к этому автопарку.');
    }

    private function authorizeOwner(Request $request, Fleet $fleet): void
    {
        if ($request->user()->role !== 'admin' && $fleet->owner_id !== $request->user()->id) {
            abort(403, 'Только владелец автопарка может управлять водителями.');
        }
    }
}
