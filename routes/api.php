<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\CargosController;
use App\Http\Controllers\Api\V1\DevicesController;
use App\Http\Controllers\Api\V1\DictionaryController;
use App\Http\Controllers\Api\V1\EventsController;
use App\Http\Controllers\Api\V1\FavoritesController;
use App\Http\Controllers\Api\V1\FleetAssignmentsController;
use App\Http\Controllers\Api\V1\FleetVehiclesController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\FleetDriversController;
use App\Http\Controllers\Api\V1\FleetRouteHistoryController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\PoiController;
use App\Http\Controllers\Api\V1\PoiReviewController;
use App\Http\Controllers\Api\V1\ProviderAnalyticsController;
use App\Http\Controllers\Api\V1\ProviderPoiController;
use App\Http\Controllers\Api\V1\RoutesController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\VehiclesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Префикс /api/v1 задан в bootstrap/app.php (apiPrefix: 'api/v1').
| Все ответы — JSON. Аутентификация — Bearer-токены через Sanctum.
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'track.activity'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// health-чек, чтобы быстро проверять "API живой"
Route::get('ping', fn () => response()->json([
    'status' => 'ok',
    'service' => 'TruckRoute API',
    'version' => 'v1',
    'time' => now()->toIso8601String(),
]));

// Public dictionaries used by web and mobile forms.
Route::get('dictionaries', [DictionaryController::class, 'index']);

// Новости (public read)
Route::get('news', [NewsController::class, 'index']);
Route::get('news/{slug}', [NewsController::class, 'show']);

// POI (АЗС/стоянки/ночлег/СТО) — публичные на чтение.
// along-route: GET for short polylines, POST for long ones. Both before {poi}.
Route::get('poi/along-route', [PoiController::class, 'alongRoute']);
Route::post('poi/along-route', [PoiController::class, 'alongRoute']);
Route::get('poi', [PoiController::class, 'index']);
Route::get('poi/{poi}/reviews', [PoiReviewController::class, 'index']);
Route::get('poi/{poi}', [PoiController::class, 'show']);

// Тонкие обёртки над геокодером и роутером для фронта.
Route::get('geo/config',  [GeoController::class, 'config']);
Route::get('geo/geocode', [GeoController::class, 'geocode']);
Route::get('geo/reverse', [GeoController::class, 'reverse']);
Route::post('geo/route',  [GeoController::class, 'route']);

// События — чтение публичное, запись только под токеном.
Route::get('events', [EventsController::class, 'index']);
Route::get('events/{event}', [EventsController::class, 'show']);

// ─── Защищённые маршруты (auth:sanctum) ───────────────────────────────────

Route::middleware(['auth:sanctum', 'track.activity'])->group(function () {

    // Личный транспорт и маршруты водителя
    Route::apiResource('vehicles', VehiclesController::class);
    Route::post('vehicles/{vehicle}/activate', [VehiclesController::class, 'activate']);

    Route::get('routes', [RoutesController::class, 'index']);
    Route::post('routes', [RoutesController::class, 'store']);
    Route::get('routes/{routePlan}', [RoutesController::class, 'show']);
    Route::post('routes/{routePlan}/recalculate', [RoutesController::class, 'recalculate']);
    Route::delete('routes/{routePlan}', [RoutesController::class, 'destroy']);

    // Дорожные события
    Route::post('events', [EventsController::class, 'store']);
    Route::post('events/{event}/vote', [EventsController::class, 'vote']);
    Route::post('events/{event}/report', [EventsController::class, 'report']);

    // Настройки пользователя
    Route::get('settings', [SettingsController::class, 'show']);
    Route::patch('settings', [SettingsController::class, 'update']);
    Route::patch('settings/password', [SettingsController::class, 'updatePassword']);

    // Уведомления (порядок важен: read-all до {id}/read)
    Route::get('notifications', [NotificationsController::class, 'index']);
    Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationsController::class, 'markRead']);

    // Устройства (FCM-токены для пуш-уведомлений)
    Route::get('devices', [DevicesController::class, 'index']);
    Route::post('devices', [DevicesController::class, 'register']);
    Route::delete('devices/{device}', [DevicesController::class, 'destroy']);

    // ─── Загрузка медиа (авторизованные) ─────────────────────────────────
    Route::post('media/upload', [MediaController::class, 'upload']);

    // ─── Новости (admin CRUD) ─────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::post('news', [NewsController::class, 'store']);
        Route::put('news/{news}', [NewsController::class, 'update']);
        Route::patch('news/{news}', [NewsController::class, 'update']);
        Route::delete('news/{news}', [NewsController::class, 'destroy']);
    });

    // ─── Администратор ────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dictionaries', [DictionaryController::class, 'adminIndex']);
        Route::post('dictionaries', [DictionaryController::class, 'store']);
        Route::patch('dictionaries/{dictionaryItem}', [DictionaryController::class, 'update']);
        Route::delete('dictionaries/{dictionaryItem}', [DictionaryController::class, 'destroy']);
        Route::get('stats', [AdminController::class, 'stats']);
        Route::get('analytics', [AdminAnalyticsController::class, 'index']);
        Route::get('events', [AdminController::class, 'events']);
        Route::get('events/pending', [AdminController::class, 'eventsPending']);
        Route::post('events/{event}/approve', [AdminController::class, 'approveEvent']);
        Route::post('events/{event}/reject', [AdminController::class, 'rejectEvent']);
        Route::delete('events/{event}', [AdminController::class, 'deleteEvent']);
        Route::get('poi', [AdminController::class, 'allPoi']);
        Route::get('poi/pending', [AdminController::class, 'poiPending']);
        Route::post('poi/{poi}/approve', [AdminController::class, 'approvePoi']);
        Route::post('poi/{poi}/reject', [AdminController::class, 'rejectPoi']);
        Route::delete('poi/{poi}', [AdminController::class, 'deletePoi']);
        Route::get('users', [AdminController::class, 'users']);
        Route::patch('users/{user}', [AdminController::class, 'updateUser']);
        Route::post('users/{user}/ban', [AdminController::class, 'banUser']);
        Route::post('users/{user}/unban', [AdminController::class, 'unbanUser']);
        Route::get('news', [AdminController::class, 'news']);
        Route::get('news/{news}', [AdminController::class, 'showNews']);
    });

    // ─── Провайдер: CRUD собственных POI + аналитика ─────────────────────
    Route::middleware('role:provider,admin')->prefix('provider')->group(function () {
        Route::get('poi', [ProviderPoiController::class, 'index']);
        Route::post('poi', [ProviderPoiController::class, 'store']);
        Route::get('poi/{poi}', [ProviderPoiController::class, 'show']);
        Route::put('poi/{poi}', [ProviderPoiController::class, 'update']);
        Route::patch('poi/{poi}', [ProviderPoiController::class, 'update']);
        Route::delete('poi/{poi}', [ProviderPoiController::class, 'destroy']);
        Route::get('poi/{poi}/stats', [ProviderPoiController::class, 'stats']);
        // Analytics
        Route::get('analytics', [ProviderAnalyticsController::class, 'index']);
        Route::get('analytics/{poi}', [ProviderAnalyticsController::class, 'show']);
    });

    Route::get('fleets', [FleetController::class, 'index']);
    Route::get('fleets/{fleet}', [FleetController::class, 'show']);
    Route::get('fleets/{fleet}/drivers', [FleetDriversController::class, 'index']);
    Route::get('fleets/{fleet}/drivers/{driver}/routes', [FleetRouteHistoryController::class, 'index']);
    Route::get('fleets/{fleet}/assignments', [FleetAssignmentsController::class, 'indexForFleet']);
    Route::get('fleets/{fleet}/assignments/{assignment}', [FleetAssignmentsController::class, 'show']);
    Route::get('fleets/{fleet}/vehicles', [FleetVehiclesController::class, 'index']);

    // ─── Автопарк (fleet role) ────────────────────────────────────────────
    Route::middleware('role:fleet,admin')->group(function () {
        Route::apiResource('fleets', FleetController::class)->except(['index', 'show']);

        Route::post('fleets/{fleet}/drivers', [FleetDriversController::class, 'attach']);
        Route::delete('fleets/{fleet}/drivers/{user}', [FleetDriversController::class, 'detach']);

        Route::post('fleets/{fleet}/assignments', [FleetAssignmentsController::class, 'store']);
        Route::patch('fleets/{fleet}/assignments/{assignment}', [FleetAssignmentsController::class, 'update']);
        Route::post('fleets/{fleet}/assignments/{assignment}/rating', [FleetAssignmentsController::class, 'rate']);
        Route::post('fleets/{fleet}/vehicles', [FleetVehiclesController::class, 'store']);
        Route::put('fleets/{fleet}/vehicles/{vehicle}', [FleetVehiclesController::class, 'update']);
        Route::patch('fleets/{fleet}/vehicles/{vehicle}', [FleetVehiclesController::class, 'update']);
        Route::delete('fleets/{fleet}/vehicles/{vehicle}', [FleetVehiclesController::class, 'destroy']);
    });

    // Задания водителя (driver видит свои задания, может принять/завершить/отменить)
    Route::get('assignments', [FleetAssignmentsController::class, 'indexForDriver']);
    Route::get('assignments/{assignment}', [FleetAssignmentsController::class, 'showForDriver']);
    Route::post('assignments/{assignment}/accept', [FleetAssignmentsController::class, 'accept']);
    Route::post('assignments/{assignment}/complete', [FleetAssignmentsController::class, 'complete']);
    Route::post('assignments/{assignment}/cancel', [FleetAssignmentsController::class, 'cancel']);

    // ─── Профили груза ────────────────────────────────────────────────────
    Route::get('cargos', [CargosController::class, 'index']);
    Route::post('cargos', [CargosController::class, 'store']);
    Route::patch('cargos/{cargo}', [CargosController::class, 'update']);
    Route::delete('cargos/{cargo}', [CargosController::class, 'destroy']);

    // ─── Избранные POI ────────────────────────────────────────────────────
    // ids before {poi} to avoid route conflict
    Route::get('favorites/ids', [FavoritesController::class, 'ids']);
    Route::get('favorites', [FavoritesController::class, 'index']);
    Route::post('favorites/{poi}', [FavoritesController::class, 'store']);
    Route::delete('favorites/{poi}', [FavoritesController::class, 'destroy']);

    Route::post('poi/{poi}/reviews', [PoiReviewController::class, 'store']);
    Route::patch('poi/{poi}/reviews/{review}', [PoiReviewController::class, 'update']);
    Route::delete('poi/{poi}/reviews/{review}', [PoiReviewController::class, 'destroy']);
    Route::post('poi/{poi}/reviews/{review}/reply', [PoiReviewController::class, 'reply']);
    Route::delete('poi/{poi}/reviews/{review}/reply', [PoiReviewController::class, 'deleteReply']);

    // ─── Активная поездка (trip session) ─────────────────────────────────
    Route::get('trip/current', [TripController::class, 'current']);
    Route::post('trip/start', [TripController::class, 'start']);
    Route::post('trip/location', [TripController::class, 'location']);
    Route::post('trip/stop-decision', [TripController::class, 'stopDecision']);
    Route::post('trip/end', [TripController::class, 'end']);
});
