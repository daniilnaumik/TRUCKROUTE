<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\NewsArticleResource;
use App\Http\Resources\V1\PoiResource;
use App\Http\Resources\V1\UserResource;
use App\Models\NewsArticle;
use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Models\ServiceObject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users_total'    => User::count(),
                'events_active'  => RoadEvent::where('status', 'active')->count(),
                'events_pending' => RoadEvent::where('status', 'checking')->count(),
                'poi_total'      => ServiceObject::count(),
                'poi_pending'    => ServiceObject::where('status', 'moderation')->count(),
                'routes_today'   => RoutePlan::whereDate('created_at', today())->count(),
                'news_published' => NewsArticle::published()->count(),
            ],
        ]);
    }

    // ── Events ────────────────────────────────────────────────────────────

    public function eventsPending(): JsonResponse
    {
        $events = RoadEvent::where('status', 'checking')->latest()->paginate(20);
        return response()->json($events);
    }

    public function events(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));

        $events = RoadEvent::query()
            ->withCount([
                'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
                'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('reported_at')
            ->paginate($perPage);

        return response()->json([
            'data' => EventResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function approveEvent(RoadEvent $event): JsonResponse
    {
        $event->update(['status' => 'active']);
        return response()->json(['message' => 'Событие подтверждено.', 'data' => $event]);
    }

    public function rejectEvent(RoadEvent $event): JsonResponse
    {
        $event->update(['status' => 'rejected']);
        return response()->json(['message' => 'Событие отклонено.', 'data' => $event]);
    }

    public function deleteEvent(RoadEvent $event): JsonResponse
    {
        $event->delete();
        return response()->json(['message' => 'Событие удалено.']);
    }

    // ── POI ───────────────────────────────────────────────────────────────

    public function allPoi(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $poi = ServiceObject::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => PoiResource::collection($poi->items()),
            'meta' => [
                'current_page' => $poi->currentPage(),
                'last_page'    => $poi->lastPage(),
                'total'        => $poi->total(),
            ],
        ]);
    }

    public function poiPending(): JsonResponse
    {
        $poi = ServiceObject::where('status', 'moderation')->latest()->paginate(20);
        return response()->json($poi);
    }

    public function approvePoi(ServiceObject $poi): JsonResponse
    {
        $poi->update(['status' => 'active', 'verified' => true]);
        return response()->json(['message' => 'Объект подтверждён.', 'data' => new PoiResource($poi)]);
    }

    public function rejectPoi(ServiceObject $poi): JsonResponse
    {
        $poi->update(['status' => 'rejected', 'verified' => false]);
        return response()->json(['message' => 'Объект отклонён.', 'data' => new PoiResource($poi)]);
    }

    public function deletePoi(ServiceObject $poi): JsonResponse
    {
        $poi->delete();
        return response()->json(['message' => 'Объект удалён.']);
    }

    // ── Users ─────────────────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->input('role'),   fn ($q, $r) => $q->where('role', $r))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function($qq) use ($s) {
                $qq->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%");
            }))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role'   => ['sometimes', 'string', 'in:' . implode(',', User::ROLES)],
            'status' => ['sometimes', 'string', 'in:active,blocked,banned'],
        ]);
        $user->update($validated);
        return response()->json(['message' => 'Пользователь обновлён.', 'data' => new UserResource($user)]);
    }

    public function banUser(User $user): JsonResponse
    {
        $user->update(['status' => 'banned']);
        return response()->json(['message' => "Пользователь {$user->name} заблокирован."]);
    }

    public function unbanUser(User $user): JsonResponse
    {
        $user->update(['status' => 'active']);
        return response()->json(['message' => "Пользователь {$user->name} разблокирован."]);
    }

    // ── News ──────────────────────────────────────────────────────────────

    public function news(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $articles = NewsArticle::query()
            ->with('author:id,name')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => NewsArticleResource::collection($articles->items()),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    public function showNews(NewsArticle $news): JsonResponse
    {
        return response()->json([
            'data' => new NewsArticleResource($news->load('author:id,name')),
        ]);
    }
}
