<?php

namespace App\Http\Controllers;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Models\ServiceDocument;
use App\Models\ServiceObject;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Vehicle;
use App\Services\RouteCalculator;
use App\Services\RoadEventNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home', [
            'events' => RoadEvent::latest('reported_at')->take(3)->get(),
            'routes' => RoutePlan::latest()->take(3)->get(),
            'objects' => ServiceObject::take(4)->get(),
        ]);
    }

    public function news()
    {
        return view('pages.news', [
            'events' => RoadEvent::latest('reported_at')->get(),
            'highways' => RoadEvent::whereNotNull('highway')
                ->distinct()
                ->orderBy('highway')
                ->pluck('highway'),
        ]);
    }

    public function routes()
    {
        $user = Auth::user();
        $calculatedRouteId = session('calculated_route_id');
        $routePlan = null;
        $vehicle = $this->currentVehicle($user);

        if ($calculatedRouteId && $user) {
            $routePlan = $user->routePlans()->with('recommendationsList.serviceObject')->whereKey($calculatedRouteId)->first();
        }

        $routePlan ??= $user?->routePlans()->with('recommendationsList.serviceObject')->latest()->first()
            ?? RoutePlan::with('recommendationsList.serviceObject')->first();

        return view('pages.routes', [
            'routePlan' => $routePlan,
            'routes' => $user?->routePlans()->latest()->get() ?? RoutePlan::latest()->get(),
            'vehicle' => $vehicle,
            'events' => RoadEvent::latest('reported_at')->take(3)->get(),
            'truckCatalog' => config('truck_models'),
            'truckModelImages' => config('truck_images.models'),
            'routePresets' => config('truck_routes'),
            'restObjects' => ServiceObject::whereIn('type', ['АЗС', 'Стоянка', 'Ночлег'])
                ->whereNotNull('km_marker')
                ->orderBy('km_marker')
                ->get(['id', 'name', 'type', 'highway', 'km_marker', 'location', 'services', 'rating', 'detour_km', 'has_truck_parking']),
        ]);
    }

    public function calculateRoute(Request $request, RouteCalculator $calculator)
    {
        $this->normalizeNumericInput($request);
        $request->merge([
            'continuous_drive_hours' => $request->input('continuous_drive_hours', 4),
            'lodging_type' => $request->input('lodging_type', 'Стоянка'),
        ]);
        $this->applyRouteDefaults($request);
        $this->applyStoredVehicleDefaults($request);
        $this->applyVehicleModelDefaults($request);

        $data = $request->validate([
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'via_point' => ['nullable', 'string', 'max:255'],
            'start_time' => ['nullable', 'date'],
            'preferred_fuel_brand' => ['required', 'string', 'max:50'],
            'lodging_type' => ['required', 'string', 'max:50'],
            'night_budget_rub' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'include_rest_stop' => ['nullable', 'boolean'],
            'selected_rest_object_id' => ['nullable', 'integer', 'exists:service_objects,id'],
            'vehicle_model' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'fuel_type' => ['required', 'string', 'max:50'],
            'allowed_fuel' => ['required', 'string', 'max:50'],
            'vehicle_curb_weight_t' => ['required', 'numeric', 'min:1', 'max:40'],
            'cargo_weight_t' => ['required', 'numeric', 'min:0', 'max:45'],
            'cargo_flag' => ['required', 'string', 'max:50'],
            'cargo_requirements' => ['required', 'string', 'max:255'],
            'no_toll_roads' => ['required', 'string', 'max:10'],
            'distance_km' => ['required', 'integer', 'min:1', 'max:10000'],
            'start_fuel_l' => ['required', 'numeric', 'min:0', 'max:2000'],
            'tank_capacity_l' => ['required', 'numeric', 'min:1', 'max:2000'],
            'consumption_l_per_100' => ['required', 'numeric', 'min:1', 'max:100'],
            'reserve_percent' => ['required', 'integer', 'min:0', 'max:80'],
            'cruise_speed_kmh' => ['required', 'integer', 'min:30', 'max:120'],
            'planning_mode' => ['required', 'string', 'max:50'],
            'continuous_drive_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'restrictions' => ['nullable', 'string', 'max:255'],
        ]);

        $calculation = $calculator->calculate(
            $data,
            RoadEvent::latest('reported_at')->get(),
            ServiceObject::whereIn('type', ['АЗС', 'Стоянка', 'Ночлег', 'СТО'])->get()
        );

        $vehicle = $this->currentVehicle(Auth::user());

        if (!$vehicle || $vehicle->user_id !== Auth::id()) {
            $vehicle = Vehicle::create([
                'user_id' => Auth::id(),
                'title' => 'Основная фура',
                'type' => $data['vehicle_type'],
                'model' => $data['vehicle_model'] ?: null,
                'fuel_type' => $data['fuel_type'],
                'allowed_fuel' => $data['allowed_fuel'],
                'tank_capacity_l' => (int) $data['tank_capacity_l'],
                'consumption_l_per_100' => $data['consumption_l_per_100'],
                'cruise_speed_kmh' => (int) $data['cruise_speed_kmh'],
                'curb_weight_t' => $data['vehicle_curb_weight_t'],
                'restrictions' => ($data['restrictions'] ?? null) ?: 'Без опасного груза',
                'image' => $this->truckImageForModel($data['vehicle_model']),
                'is_active' => true,
            ]);
        }

        $routePlan = RoutePlan::create([
            'user_id' => Auth::id(),
            'title' => $data['origin'] . ' - ' . $data['destination'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'via_point' => $data['via_point'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'vehicle_type' => $vehicle->type,
            'cargo_type' => 'Вес груза: ' . number_format((float) $data['cargo_weight_t'], 1, '.', ' ') . ' т',
            'cargo_weight_t' => $data['cargo_weight_t'],
            'vehicle_curb_weight_t' => $calculation['vehicle_curb_weight_t'],
            'gross_weight_t' => $calculation['gross_weight_t'],
            'start_fuel_l' => min((int) $data['start_fuel_l'], (int) $data['tank_capacity_l']),
            'tank_capacity_l' => (int) $data['tank_capacity_l'],
            'consumption_l_per_100' => $data['consumption_l_per_100'],
            'effective_consumption_l_per_100' => $calculation['effective_consumption_l_per_100'],
            'reserve_percent' => (int) $data['reserve_percent'],
            'reserve_l' => $calculation['reserve_l'],
            'cruise_speed_kmh' => (int) $data['cruise_speed_kmh'],
            'planning_mode' => $data['planning_mode'],
            'distance_km' => $calculation['distance_km'],
            'drive_time_minutes' => $calculation['drive_time_minutes'],
            'arrival_time' => $calculation['arrival_time'],
            'fuel_needed_l' => $calculation['fuel_needed_l'],
            'fuel_cost_rub' => $calculation['fuel_cost_rub'],
            'range_km' => $calculation['range_km'],
            'stops_count' => $calculation['stops_count'],
            'recommendations' => $calculation['recommendations'],
            'image' => $calculation['image'],
        ]);

        foreach ($calculation['recommendation_points'] as $point) {
            $routePlan->recommendationsList()->create($point);
        }

        return redirect()
            ->route('routes')
            ->with('calculated_route_id', $routePlan->id)
            ->with('success', 'Маршрут рассчитан и сохранен в вашем профиле.');
    }

    public function settings()
    {
        $user = Auth::user();

        return view('pages.settings', [
            'user' => $user,
            'settings' => $user ? UserSetting::firstOrCreate(['user_id' => $user->id]) : null,
            'documents' => ServiceDocument::get(),
            'sessions' => $this->activeSessions($user),
        ]);
    }

    public function profile()
    {
        $user = User::with(['vehicles', 'routePlans'])->find(Auth::id());
        $vehicle = $this->currentVehicle($user);

        return view('pages.profile', [
            'user' => $user,
            'vehicle' => $vehicle,
            'vehicles' => $user?->vehicles()->latest()->get() ?? collect(),
            'routes' => $user?->routePlans()->latest()->get() ?? collect(),
            'unreadNotificationsCount' => $user?->unreadNotifications()->count() ?? 0,
            'eventsCount' => RoadEvent::count(),
            'truckCatalog' => config('truck_models'),
            'truckImages' => config('truck_images.brands'),
            'truckModelImages' => config('truck_images.models'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
        ]);

        Auth::user()->update($data);

        return redirect()
            ->route('profile')
            ->with('success', 'Профиль обновлен.');
    }

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = Auth::user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $data['avatar']->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return redirect()
            ->route('profile')
            ->with('success', 'Аватарка обновлена.');
    }

    public function storeVehicle(Request $request)
    {
        $this->normalizeNumericInput($request);
        $this->applyVehicleModelDefaults($request);

        $data = $request->validate([
            'vehicle_model' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'fuel_type' => ['required', 'string', 'max:50'],
            'allowed_fuel' => ['required', 'string', 'max:50'],
            'tank_capacity_l' => ['required', 'numeric', 'min:1', 'max:2000'],
            'consumption_l_per_100' => ['required', 'numeric', 'min:1', 'max:100'],
            'cruise_speed_kmh' => ['required', 'integer', 'min:30', 'max:120'],
            'vehicle_curb_weight_t' => ['required', 'numeric', 'min:1', 'max:40'],
            'restrictions' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $user->vehicles()->update(['is_active' => false]);

        $vehicle = Vehicle::updateOrCreate(
            [
                'user_id' => $user->id,
                'model' => $data['vehicle_model'],
            ],
            [
                'title' => 'Основная фура',
                'type' => $data['vehicle_type'],
                'fuel_type' => $data['fuel_type'],
                'allowed_fuel' => $data['allowed_fuel'],
                'tank_capacity_l' => (int) $data['tank_capacity_l'],
                'consumption_l_per_100' => $data['consumption_l_per_100'],
                'cruise_speed_kmh' => (int) $data['cruise_speed_kmh'],
                'curb_weight_t' => $data['vehicle_curb_weight_t'],
                'restrictions' => ($data['restrictions'] ?? null) ?: 'Стандартная еврофура',
                'image' => $this->truckImageForModel($data['vehicle_model']),
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('profile')
            ->with('success', 'Выбрана фура: ' . $vehicle->model . '. Теперь маршрут будет считаться по ее характеристикам.');
    }

    public function selectVehicle(Vehicle $vehicle)
    {
        abort_unless($vehicle->user_id === Auth::id(), 403);

        Auth::user()->vehicles()->update(['is_active' => false]);
        $vehicle->update([
            'image' => $this->truckImageForModel($vehicle->model),
            'is_active' => true,
        ]);

        return redirect()
            ->route('profile')
            ->with('success', 'Активная фура изменена.');
    }

    public function destroyRoutePlan(RoutePlan $routePlan)
    {
        abort_unless($routePlan->user_id === Auth::id(), 403);

        $routePlan->delete();

        return redirect()
            ->route('profile')
            ->with('success', 'Маршрут удален.');
    }

    public function updateSettings(Request $request, RoadEventNotifier $notifier)
    {
        $settings = UserSetting::firstOrCreate(['user_id' => Auth::id()]);
        $wasEnabled = $settings->incident_notifications;
        $enabled = $request->boolean('incident_notifications');

        $settings->update([
            'incident_notifications' => $enabled,
            'privacy_policy_accepted' => $request->boolean('privacy_policy_accepted'),
            'data_processing_accepted' => $request->boolean('data_processing_accepted'),
        ]);

        $sent = (!$wasEnabled && $enabled)
            ? $notifier->notifyUserAboutCurrentEvents(Auth::user()->load('settings'))
            : 0;

        $message = $sent > 0
            ? 'Настройки сохранены. Отправлено уведомлений: ' . $sent . '.'
            : 'Настройки сохранены.';

        return redirect()->route('settings')->with('success', $message);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Текущий пароль указан неверно.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
            'password.min' => 'Новый пароль должен быть не короче 8 символов.',
        ]);

        Auth::user()->update([
            'password' => $data['password'],
        ]);

        UserSetting::firstOrCreate(['user_id' => Auth::id()])->update([
            'last_password_change_at' => now(),
        ]);

        session()->regenerate();

        return redirect()->route('settings')->with('success', 'Пароль обновлен.');
    }

    public function destroySession(string $sessionId)
    {
        if ($sessionId === session()->getId()) {
            return redirect()->route('settings')->with('success', 'Текущую сессию лучше завершать через кнопку выхода.');
        }

        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->delete();

        return redirect()->route('settings')->with('success', 'Выбранный сеанс завершен.');
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Текущий пароль указан неверно.',
        ]);

        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', session()->getId())
            ->delete();

        session()->regenerate();

        return redirect()->route('settings')->with('success', 'Все остальные устройства вышли из аккаунта.');
    }

    public function moderateEvent(Request $request, RoadEvent $event, RoadEventNotifier $notifier)
    {
        $action = $request->validate([
            'action' => ['required', 'in:approve,reject,delete'],
        ])['action'];

        if ($action === 'delete') {
            $event->delete();

            return redirect()->route('admin')->with('success', 'Событие удалено.');
        }

        $event->update([
            'status' => $action === 'approve' ? 'active' : 'rejected',
        ]);

        if ($action === 'approve') {
            $notifier->notifyUsersAbout($event->fresh());
        }

        return redirect()->route('admin')->with('success', 'Статус события обновлен.');
    }

    public function notifications()
    {
        return view('pages.notifications', [
            'notifications' => Auth::user()->notifications()->latest()->get(),
        ]);
    }

    public function showNotification(DatabaseNotification $notification)
    {
        abort_unless(
            $notification->notifiable_type === User::class && (int) $notification->notifiable_id === Auth::id(),
            403
        );

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        $event = RoadEvent::find($notification->data['road_event_id'] ?? null);

        return view('pages.notification-show', [
            'notification' => $notification,
            'event' => $event,
        ]);
    }

    public function admin()
    {
        return view('pages.admin', [
            'usersCount' => User::count(),
            'routesCount' => RoutePlan::count(),
            'events' => RoadEvent::latest('reported_at')->get(),
            'eventsCount' => RoadEvent::where('status', 'active')->count(),
            'objects' => ServiceObject::latest()->get(),
            'objectsModerationCount' => ServiceObject::where('status', 'moderation')->count(),
            'documents' => ServiceDocument::get(),
        ]);
    }

    private function normalizeNumericInput(Request $request): void
    {
        $numericFields = [
            'start_fuel_l',
            'tank_capacity_l',
            'consumption_l_per_100',
            'vehicle_curb_weight_t',
            'cargo_weight_t',
            'continuous_drive_hours',
            'night_budget_rub',
        ];

        $normalized = [];

        foreach ($numericFields as $field) {
            if ($request->filled($field)) {
                $normalized[$field] = str_replace(',', '.', (string) $request->input($field));
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function activeSessions(?User $user)
    {
        if (!$user) {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                return (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?: 'неизвестно',
                    'browser' => $this->browserName($session->user_agent),
                    'user_agent' => $session->user_agent ?: 'данные браузера недоступны',
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                    'is_current' => $session->id === session()->getId(),
                ];
            });
    }

    private function browserName(?string $userAgent): string
    {
        $agent = mb_strtolower((string) $userAgent);

        return match (true) {
            str_contains($agent, 'edg') => 'Microsoft Edge',
            str_contains($agent, 'chrome') => 'Google Chrome',
            str_contains($agent, 'firefox') => 'Firefox',
            str_contains($agent, 'safari') => 'Safari',
            str_contains($agent, 'opera') || str_contains($agent, 'opr') => 'Opera',
            default => 'Неизвестный браузер',
        };
    }

    private function applyVehicleModelDefaults(Request $request): void
    {
        $model = (string) $request->input('vehicle_model');
        $specs = $this->truckModelSpecs($model);

        if (!$specs) {
            return;
        }

        $request->merge([
            'vehicle_type' => $specs['type'],
            'fuel_type' => $specs['fuel_type'],
            'allowed_fuel' => $specs['allowed_fuel'],
            'tank_capacity_l' => $specs['tank_capacity_l'],
            'consumption_l_per_100' => $specs['consumption_l_per_100'],
            'cruise_speed_kmh' => $specs['cruise_speed_kmh'],
            'restrictions' => $specs['restrictions'],
            'vehicle_curb_weight_t' => $this->truckCurbWeight($specs['type'] ?? null),
        ]);
    }

    private function applyStoredVehicleDefaults(Request $request): void
    {
        if ($request->filled('vehicle_model')) {
            return;
        }

        $vehicle = $this->currentVehicle(Auth::user());

        if (!$vehicle) {
            return;
        }

        $request->merge([
            'vehicle_model' => $vehicle->model,
            'vehicle_type' => $vehicle->type,
            'fuel_type' => $vehicle->fuel_type,
            'allowed_fuel' => $vehicle->allowed_fuel ?: 'Дизель + AdBlue',
            'tank_capacity_l' => $vehicle->tank_capacity_l,
            'consumption_l_per_100' => $vehicle->consumption_l_per_100,
            'cruise_speed_kmh' => $vehicle->cruise_speed_kmh,
            'vehicle_curb_weight_t' => $vehicle->curb_weight_t ?: $this->truckCurbWeight($vehicle->type),
            'restrictions' => $vehicle->restrictions,
        ]);
    }

    private function applyRouteDefaults(Request $request): void
    {
        if ($request->filled('distance_km')) {
            return;
        }

        $route = $this->routePreset((string) $request->input('origin'), (string) $request->input('destination'));

        if (!$route) {
            return;
        }

        $request->merge([
            'distance_km' => $route['distance_km'],
            'via_point' => $route['via_point'] ?? null,
        ]);
    }

    private function routePreset(string $origin, string $destination): ?array
    {
        $origin = mb_strtolower(trim($origin));
        $destination = mb_strtolower(trim($destination));

        foreach (config('truck_routes') as $route) {
            $routeOrigin = mb_strtolower($route['origin']);
            $routeDestination = mb_strtolower($route['destination']);

            if ($routeOrigin === $origin && $routeDestination === $destination) {
                return $route;
            }

            if ($routeOrigin === $destination && $routeDestination === $origin) {
                return [
                    ...$route,
                    'origin' => $route['destination'],
                    'destination' => $route['origin'],
                ];
            }
        }

        return null;
    }

    private function truckModelSpecs(string $model): ?array
    {
        foreach (config('truck_models') as $models) {
            if (isset($models[$model])) {
                return $models[$model];
            }
        }

        return null;
    }

    private function truckBrandForModel(string $model): ?string
    {
        foreach (config('truck_models') as $brand => $models) {
            if (isset($models[$model])) {
                return $brand;
            }
        }

        return null;
    }

    private function truckImageForModel(?string $model): string
    {
        $modelImages = config('truck_images.models', []);

        if ($model && isset($modelImages[$model])) {
            return $modelImages[$model];
        }

        $brand = $model ? $this->truckBrandForModel($model) : null;
        $brandImages = config('truck_images.brands', []);

        if ($brand && isset($brandImages[$brand])) {
            return $brandImages[$brand];
        }

        return config('truck_images.fallback', 'truck-white.jpg');
    }

    private function currentVehicle(?User $user): ?Vehicle
    {
        if (!$user) {
            return Vehicle::where('is_active', true)->latest()->first() ?? Vehicle::latest()->first();
        }

        return $user->vehicles()->where('is_active', true)->latest()->first()
            ?? $user->vehicles()->latest()->first()
            ?? Vehicle::whereNull('user_id')->where('is_active', true)->latest()->first()
            ?? Vehicle::latest()->first();
    }

    private function truckCurbWeight(?string $type): float
    {
        $type = mb_strtolower((string) $type);

        return match (true) {
            str_contains($type, 'фургон') => 7.5,
            str_contains($type, 'одиночка') => 12.0,
            str_contains($type, 'реф') || str_contains($type, 'рефрижератор') => 17.0,
            str_contains($type, 'цистерна') => 18.0,
            default => 15.5,
        };
    }
}
