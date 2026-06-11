<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Vehicles\StoreVehicleRequest;
use App\Http\Resources\V1\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehiclesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicles = $request->user()->vehicles()->latest()->get();
        return VehicleResource::collection($vehicles);
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Если новый профиль помечен активным — снимаем флаг с остальных.
        if (!empty($data['is_active'])) {
            $user->vehicles()->update(['is_active' => false]);
        }

        $vehicle = $user->vehicles()->create(array_merge(
            ['title' => 'Основная фура', 'is_active' => true],
            $data,
        ));

        return response()->json((new VehicleResource($vehicle))->resolve($request), 201);
    }

    public function show(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->ownerOnly($request, $vehicle);
        return new VehicleResource($vehicle);
    }

    public function update(StoreVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->ownerOnly($request, $vehicle);
        $data = $request->validated();

        if (!empty($data['is_active'])) {
            $request->user()->vehicles()->update(['is_active' => false]);
        }

        $vehicle->update($data);
        return new VehicleResource($vehicle->fresh());
    }

    public function activate(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->ownerOnly($request, $vehicle);
        $request->user()->vehicles()->update(['is_active' => false]);
        $vehicle->update(['is_active' => true]);
        return response()->json((new VehicleResource($vehicle->fresh()))->resolve($request));
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->ownerOnly($request, $vehicle);
        $vehicle->delete();
        return response()->json(['message' => 'Фура удалена.']);
    }

    private function ownerOnly(Request $request, Vehicle $vehicle): void
    {
        if ($vehicle->user_id !== $request->user()->id) {
            abort(403, 'Эта фура принадлежит другому пользователю.');
        }
    }
}
