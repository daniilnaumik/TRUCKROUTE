<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FleetResource;
use App\Models\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FleetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $fleets = Fleet::with('owner:id,name')
            ->withCount([
                'drivers as drivers_count' => fn ($q) => $q->whereColumn('users.id', '<>', 'fleets.owner_id'),
                'assignments',
                'completedAssignments',
                'vehicles',
            ])
            ->when($user->role !== 'admin', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('owner_id', $user->id)
                        ->orWhereHas('drivers', fn ($drivers) => $drivers->where('users.id', $user->id));
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return FleetResource::collection($fleets);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'inn' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'base_city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ]);

        $fleet = Fleet::create([
            ...$data,
            'owner_id' => $request->user()->id,
        ]);

        return (new FleetResource($fleet->loadCount([
            'drivers as drivers_count' => fn ($q) => $q->whereColumn('users.id', '<>', 'fleets.owner_id'),
            'assignments',
            'completedAssignments',
            'vehicles',
        ])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeFleetAccess($request, $fleet);
        $fleet->load('owner:id,name')->loadCount([
            'drivers as drivers_count' => fn ($q) => $q->whereColumn('users.id', '<>', 'fleets.owner_id'),
            'assignments',
            'completedAssignments',
            'vehicles',
        ]);
        return response()->json(['data' => new FleetResource($fleet)]);
    }

    public function update(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'inn' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'base_city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ]);

        $fleet->update($data);
        return response()->json(['data' => new FleetResource($fleet->load('owner:id,name')->loadCount([
            'drivers as drivers_count' => fn ($q) => $q->whereColumn('users.id', '<>', 'fleets.owner_id'),
            'assignments',
            'completedAssignments',
            'vehicles',
        ]))]);
    }

    public function destroy(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);
        $fleet->delete();
        return response()->json(['message' => 'Автопарк удалён.']);
    }

    private function authorizeOwner(Request $request, Fleet $fleet): void
    {
        if ($request->user()->role !== 'admin' && $fleet->owner_id !== $request->user()->id) {
            abort(403, 'Этот автопарк принадлежит другому пользователю.');
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

        abort(403, 'Нет доступа к этому автопарку.');
    }
}
