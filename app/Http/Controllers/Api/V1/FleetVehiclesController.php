<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Vehicles\StoreVehicleRequest;
use App\Http\Resources\V1\VehicleResource;
use App\Models\Fleet;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FleetVehiclesController extends Controller
{
    public function index(Request $request, Fleet $fleet): AnonymousResourceCollection
    {
        $this->authorizeFleetAccess($request, $fleet);

        return VehicleResource::collection(
            $fleet->vehicles()->latest()->get(),
        );
    }

    public function store(StoreVehicleRequest $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);

        $vehicle = $fleet->vehicles()->create([
            ...$request->validated(),
            'user_id' => null,
            'is_active' => true,
        ]);

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function update(StoreVehicleRequest $request, Fleet $fleet, Vehicle $vehicle): VehicleResource
    {
        $this->authorizeOwner($request, $fleet);
        $this->belongsToFleet($fleet, $vehicle);

        $vehicle->update([
            ...$request->validated(),
            'user_id' => null,
        ]);

        return new VehicleResource($vehicle->fresh());
    }

    public function destroy(Request $request, Fleet $fleet, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);
        $this->belongsToFleet($fleet, $vehicle);

        if ($fleet->assignments()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', ['issued', 'accepted', 'in_progress'])
            ->exists()) {
            return response()->json([
                'message' => 'Эта фура назначена на активное задание. Сначала завершите или отмените задание.',
            ], 422);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Фура удалена из автопарка.']);
    }

    private function authorizeOwner(Request $request, Fleet $fleet): void
    {
        if ($request->user()->role !== 'admin' && $fleet->owner_id !== $request->user()->id) {
            abort(403, 'Только владелец автопарка может управлять его транспортом.');
        }
    }

    private function authorizeFleetAccess(Request $request, Fleet $fleet): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $fleet->owner_id === $user->id) {
            return;
        }

        if ($fleet->drivers()->where('users.id', $user->id)->exists()) {
            return;
        }

        abort(403, 'Нет доступа к транспорту этого автопарка.');
    }

    private function belongsToFleet(Fleet $fleet, Vehicle $vehicle): void
    {
        if ((int) $vehicle->fleet_id !== (int) $fleet->id) {
            abort(404, 'Фура не найдена в этом автопарке.');
        }
    }
}
