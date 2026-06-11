<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AssignmentResource;
use App\Models\Fleet;
use App\Models\RouteAssignment;
use App\Models\User;
use App\Notifications\FleetAssignmentIssued;
use App\Services\RouteBuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class FleetAssignmentsController extends Controller
{
    public function __construct(private readonly RouteBuildService $routeBuilder)
    {
    }

    /**
     * GET /api/v1/fleets/{fleet}/assignments — список заданий менеджера парка.
     * GET /api/v1/assignments — все задания текущего водителя.
     */
    public function indexForFleet(Request $request, Fleet $fleet): AnonymousResourceCollection
    {
        $this->authorizeFleetAccess($request, $fleet);

        $user = $request->user();
        $isManager = $user->role === 'admin' || $fleet->owner_id === $user->id;

        $assignments = RouteAssignment::with(['driver', 'fleet.owner', 'vehicle'])
            ->where('fleet_id', $fleet->id)
            ->when(! $isManager, fn ($q) => $q->where('driver_user_id', $user->id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('driver_id'), fn ($q, $id) => $q->where('driver_user_id', $id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return AssignmentResource::collection($assignments);
    }

    /**
     * GET /api/v1/assignments — задания водителя (авторизованного).
     */
    public function indexForDriver(Request $request): AnonymousResourceCollection
    {
        $assignments = RouteAssignment::with(['driver', 'fleet.owner', 'vehicle'])
            ->where('driver_user_id', $request->user()->id)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return AssignmentResource::collection($assignments);
    }

    /**
     * POST /api/v1/fleets/{fleet}/assignments — выдать задание водителю.
     */
    public function store(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);
        $request->merge([
            'vehicle_source' => $request->input('vehicle_source', 'driver'),
        ]);

        $data = $request->validate([
            'driver_user_id' => ['required', 'integer', 'exists:users,id'],
            'origin' => ['required', 'string', 'max:255'],
            'origin_point' => ['nullable', 'array'],
            'origin_point.lat' => ['required_with:origin_point', 'numeric'],
            'origin_point.lng' => ['required_with:origin_point', 'numeric'],
            'destination' => ['required', 'string', 'max:255'],
            'destination_point' => ['nullable', 'array'],
            'destination_point.lat' => ['required_with:destination_point', 'numeric'],
            'destination_point.lng' => ['required_with:destination_point', 'numeric'],
            'via_points' => ['nullable', 'array', 'max:8'],
            'planned_start_at' => ['required', 'date', 'after:now'],
            'vehicle_source' => ['required', 'in:driver,fleet'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'driver_user_id.required' => 'Выберите водителя.',
            'origin.required' => 'Укажите точку отправления.',
            'destination.required' => 'Укажите точку назначения.',
            'planned_start_at.required' => 'Укажите дату и время начала задания.',
            'planned_start_at.date' => 'Введите дату и время в формате ДД.ММ.ГГГГ ЧЧ:ММ.',
            'planned_start_at.after' => 'Время начала должно быть позже текущего времени.',
            'vehicle_source.required' => 'Выберите, чей транспорт будет использовать водитель.',
            'vehicle_source.in' => 'Выберите личную фуру водителя или фуру автопарка.',
            'vehicle_id.exists' => 'Выбранная фура не найдена.',
        ]);

        // Водитель должен быть в составе парка.
        if (!$fleet->drivers()->where('user_id', $data['driver_user_id'])->exists()) {
            return response()->json([
                'message' => 'Водитель не состоит в этом автопарке.',
                'errors' => ['driver_user_id' => ['Выберите водителя из состава автопарка.']],
            ], 422);
        }

        $vehicle = null;
        if ($data['vehicle_source'] === 'fleet') {
            if (empty($data['vehicle_id'])) {
                return response()->json([
                    'message' => 'Выберите фуру автопарка.',
                    'errors' => ['vehicle_id' => ['Выберите фуру автопарка для этого задания.']],
                ], 422);
            }

            $vehicle = $fleet->vehicles()->find($data['vehicle_id']);
            if (! $vehicle) {
                return response()->json([
                    'message' => 'Выбранная фура не принадлежит этому автопарку.',
                    'errors' => ['vehicle_id' => ['Выберите фуру из транспорта текущего автопарка.']],
                ], 422);
            }
        }

        $assignment = RouteAssignment::create([
            ...$data,
            'vehicle_id' => $vehicle?->id,
            'fleet_id' => $fleet->id,
            'issued_by_user_id' => $request->user()->id,
            'status' => 'issued',
        ]);

        $assignment->load(['driver', 'fleet.owner', 'vehicle']);
        $assignment->driver->notify(new FleetAssignmentIssued($assignment));

        return (new AssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fleet $fleet, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeFleetAccess($request, $fleet);
        $this->belongsToFleet($fleet, $assignment);
        $this->authorizeAssignmentRead($request, $fleet, $assignment);

        return response()->json(['data' => new AssignmentResource($assignment->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    public function showForDriver(Request $request, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        return response()->json(['data' => new AssignmentResource($assignment->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    /**
     * PATCH /api/v1/fleets/{fleet}/assignments/{assignment}
     * Менеджер: изменить comment, planned_start_at, отменить.
     */
    public function update(Request $request, Fleet $fleet, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);
        $this->belongsToFleet($fleet, $assignment);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
            'planned_start_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:cancelled'],  // менеджер может только отменить
        ]);

        $assignment->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['data' => new AssignmentResource($assignment->fresh()->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    /**
     * POST /api/v1/assignments/{assignment}/accept — водитель принимает задание.
     */
    public function accept(Request $request, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        if ($assignment->status !== 'issued') {
            return response()->json(['message' => 'Принять можно только задание со статусом issued.'], 422);
        }

        $driver = $request->user();
        $vehicle = $driver->vehicles()->where('is_active', true)->latest()->first()
            ?? $driver->vehicles()->latest()->first();

        $vehicleInput = $vehicle ? ['vehicle_id' => $vehicle->id] : [
            'vehicle' => [
                'type' => 'Тягач + полуприцеп',
                'model' => 'Транспорт из задания',
                'fuel_type' => 'Дизель',
                'allowed_fuel' => 'Дизель + AdBlue',
                'tank_capacity_l' => 600,
                'consumption_l_per_100' => 29,
                'cruise_speed_kmh' => 85,
                'curb_weight_t' => 15.5,
            ],
        ];

        if ($assignment->vehicle_source === 'fleet') {
            $vehicle = $assignment->vehicle;

            if (! $vehicle || (int) $vehicle->fleet_id !== (int) $assignment->fleet_id) {
                return response()->json([
                    'message' => 'Назначенная фура автопарка больше недоступна. Обратитесь к владельцу автопарка.',
                ], 422);
            }

            $vehicleInput = [
                'vehicle' => [
                    'type' => $vehicle->type,
                    'model' => $vehicle->model ?: $vehicle->title,
                    'fuel_type' => $vehicle->fuel_type ?: 'Дизель',
                    'allowed_fuel' => $vehicle->allowed_fuel ?: 'Дизель + AdBlue',
                    'tank_capacity_l' => (float) $vehicle->tank_capacity_l,
                    'consumption_l_per_100' => (float) $vehicle->consumption_l_per_100,
                    'cruise_speed_kmh' => (int) ($vehicle->cruise_speed_kmh ?: 85),
                    'curb_weight_t' => (float) ($vehicle->curb_weight_t ?: 15.5),
                ],
            ];
        }

        $tankCapacity = (float) ($vehicle?->tank_capacity_l ?? 600);
        $origin = $assignment->origin_point
            ? [...$assignment->origin_point, 'label' => $assignment->origin]
            : $assignment->origin;
        $destination = $assignment->destination_point
            ? [...$assignment->destination_point, 'label' => $assignment->destination]
            : $assignment->destination;

        try {
            $routePlan = $this->routeBuilder->build($driver, [
                ...$vehicleInput,
                'origin' => $origin,
                'destination' => $destination,
                'via' => $assignment->via_points ?? [],
                'start_time' => optional($assignment->planned_start_at)->toIso8601String(),
                'start_fuel_l' => round($tankCapacity * 0.7),
                'cargo' => [
                    'weight_t' => 0,
                    'flag' => 'Обычный',
                    'requirements' => $assignment->comment ?: 'Без особых требований',
                ],
                'preferences' => [
                    'reserve_percent' => 15,
                    'planning_mode' => 'Безопасный',
                    'continuous_drive_hours' => 4,
                    'include_rest_stop' => true,
                    'preferred_fuel_brand' => 'Любые',
                    'no_toll_roads' => 'Нет',
                ],
            ]);
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'message' => 'Задание пока не принято: не удалось подготовить маршрут. Проверьте точки А и Б и повторите попытку.',
            ], 422);
        }

        $assignment->update([
            'status' => 'accepted',
            'route_plan_id' => $routePlan->id,
        ]);

        return response()->json([
            'data' => new AssignmentResource($assignment->fresh()->load(['driver', 'fleet.owner', 'vehicle'])),
            'route_plan_id' => $routePlan->id,
            'message' => 'Задание принято. Готовый маршрут добавлен в ваши маршруты.',
        ]);
    }

    /**
     * POST /api/v1/assignments/{assignment}/complete — водитель завершает задание.
     */
    public function complete(Request $request, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        if (!in_array($assignment->status, ['accepted', 'in_progress'], true)) {
            return response()->json(['message' => 'Завершить можно только принятое или выполняемое задание.'], 422);
        }

        $data = $request->validate([
            'route_plan_id' => ['nullable', 'integer', 'exists:route_plans,id'],
        ]);

        $assignment->update([
            'status' => 'completed',
            'route_plan_id' => $data['route_plan_id'] ?? null,
            'completed_at' => now(),
        ]);
        return response()->json(['data' => new AssignmentResource($assignment->fresh()->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    public function rate(Request $request, Fleet $fleet, RouteAssignment $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $fleet);
        $this->belongsToFleet($fleet, $assignment);

        if ($assignment->status !== 'completed') {
            return response()->json(['message' => 'Оценить можно только выполненное задание.'], 422);
        }

        $data = $request->validate([
            'rating_stars' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment->update([
            'rating_stars' => $data['rating_stars'],
            'rating_comment' => $data['rating_comment'] ?? null,
            'rated_by_user_id' => $request->user()->id,
            'rated_at' => now(),
        ]);

        return response()->json(['data' => new AssignmentResource($assignment->fresh()->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    /**
     * POST /api/v1/assignments/{assignment}/cancel — водитель или менеджер отменяет задание.
     */
    public function cancel(Request $request, RouteAssignment $assignment): JsonResponse
    {
        $user = $request->user();
        $isDriver = $assignment->driver_user_id === $user->id;
        $isManagerOrAdmin = $user->role === 'admin' || $assignment->fleet->owner_id === $user->id;

        if (!$isDriver && !$isManagerOrAdmin) {
            abort(403, 'Нет прав для отмены этого задания.');
        }

        if (in_array($assignment->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Задание уже завершено или отменено.'], 422);
        }

        $assignment->update(['status' => 'cancelled']);
        return response()->json(['data' => new AssignmentResource($assignment->fresh()->load(['driver', 'fleet.owner', 'vehicle']))]);
    }

    private function authorizeFleetAccess(Request $request, Fleet $fleet): void
    {
        $user = $request->user();
        if ($user->role === 'admin' || $fleet->owner_id === $user->id) {
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
            abort(403, 'Только владелец автопарка может управлять заданиями.');
        }
    }

    private function authorizeDriver(Request $request, RouteAssignment $assignment): void
    {
        if ($assignment->driver_user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            abort(403, 'Это задание принадлежит другому водителю.');
        }
    }

    private function authorizeAssignmentRead(Request $request, Fleet $fleet, RouteAssignment $assignment): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $fleet->owner_id === $user->id || $assignment->driver_user_id === $user->id) {
            return;
        }

        abort(403, 'Нет доступа к этому заданию.');
    }

    private function belongsToFleet(Fleet $fleet, RouteAssignment $assignment): void
    {
        if ($assignment->fleet_id !== $fleet->id) {
            abort(404, 'Задание не найдено в этом автопарке.');
        }
    }
}
