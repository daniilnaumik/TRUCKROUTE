<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Events\IndexEventsRequest;
use App\Http\Requests\V1\Events\StoreEventRequest;
use App\Http\Requests\V1\Events\VoteEventRequest;
use App\Http\Resources\V1\EventResource;
use App\Models\EventVote;
use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Services\Events\EventConfidenceService;
use App\Services\Events\EventDedupeService;
use App\Services\Events\RouteEventMatcher;
use App\Services\Events\RouteSubscriptionNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EventsController extends Controller
{
    public function __construct(
        private readonly EventConfidenceService $confidence,
        private readonly EventDedupeService $dedupe,
        private readonly RouteSubscriptionNotifier $notifier,
        private readonly RouteEventMatcher $matcher,
    ) {
    }

    /**
     * GET /api/v1/events
     *
     * Фильтры (все опциональны): bbox=W,S,E,N | highway | type | status (default=active+checking) | from | to | limit.
     */
    public function index(IndexEventsRequest $request): AnonymousResourceCollection
    {
        $status = $request->input('status', 'active');

        $q = RoadEvent::query()
            ->withCount([
                'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
                'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
            ]);

        if ($status === 'all') {
            // ничего — отдадим все
        } elseif ($status === 'feed') {
            $threshold = (int) config('events.confidence.hide_threshold', 0);
            $q->whereIn('status', ['active', 'checking'])
                ->where('confidence_score', '>=', $threshold);
        } elseif (in_array($status, ['active', 'checking', 'rejected', 'expired'], true)) {
            if ($status === 'active') {
                $q->visible(); // active + не expired + confidence >= hide_threshold
            } else {
                $q->where('status', $status);
            }
        }

        if ($bbox = $request->bbox()) {
            $q->withinBbox($bbox['west'], $bbox['south'], $bbox['east'], $bbox['north']);
        }
        if ($highway = $request->input('highway')) {
            $q->where('highway', 'like', '%'.$highway.'%');
        }
        if ($type = $request->input('type')) {
            $q->where('type', $type);
        }
        if ($from = $request->input('from')) {
            $q->where('reported_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->where('reported_at', '<=', $to);
        }

        // corridor=<route_plan_id> — bbox-предфильтр по границам маршрута,
        // затем точная проверка через Haversine на каждый сегмент полилинии.
        $corridorPlan = null;
        if ($corridorId = $request->input('corridor')) {
            $corridorPlan = RoutePlan::find((int) $corridorId);
            if ($corridorPlan?->polyline_json) {
                $poly = $corridorPlan->polyline();
                if (! empty($poly)) {
                    $lats = array_column($poly, 0);
                    $lngs = array_column($poly, 1);
                    $km   = (float) config('events.route_subscription.corridor_km', 5);
                    $deg  = $km / 111;
                    $q->withinBbox(
                        min($lngs) - $deg, min($lats) - $deg,
                        max($lngs) + $deg, max($lats) + $deg,
                    );
                }
            }
        }

        $events = $q->orderByDesc('reported_at')
            ->limit((int) $request->input('limit', 100))
            ->get();

        // Уточняющий фильтр по коридору: оставляем только те, что реально близко к маршруту.
        if ($corridorPlan) {
            $events = $events
                ->filter(fn ($e) => $this->matcher->matchesCorridor($corridorPlan, $e))
                ->values();
        }

        return EventResource::collection($events)->additional([
            'meta' => [
                'count'         => $events->count(),
                'status_filter' => $status,
                'corridor_km'   => $corridorPlan
                    ? config('events.route_subscription.corridor_km', 5)
                    : null,
            ],
        ]);
    }

    public function show(RoadEvent $event)
    {
        $event->loadCount([
            'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
            'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
        ]);

        // ResourceResponse оборачивает в {data: {...}} — единый формат с index/store.
        return new EventResource($event);
    }

    /**
     * POST /api/v1/events — создание события водителем.
     * Если рядом уже есть событие того же типа — голосуем за него, чтобы не плодить дубли.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Антиспам по созданию.
        $maxPerDay = (int) config('events.spam.max_events_per_user_per_day', 10);
        $todayCount = $user->reportedEvents()->where('created_at', '>=', now()->startOfDay())->count();
        if ($todayCount >= $maxPerDay) {
            return response()->json([
                'message' => sprintf('Превышен дневной лимит создания событий (%d/сут).', $maxPerDay),
            ], 429);
        }

        // Дедуп: если рядом уже есть событие того же типа — превращаем в голос.
        $duplicate = $this->dedupe->findDuplicate($data['type'], (float) $data['lat'], (float) $data['lng']);
        if ($duplicate) {
            if ((int) $duplicate->created_by_user_id === (int) $user->id) {
                return (new EventResource($duplicate->loadCount([
                    'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
                    'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
                ])))
                    ->additional([
                        'message' => 'Рядом уже есть ваше похожее событие.',
                        'merged' => true,
                    ])
                    ->response()
                    ->setStatusCode(200);
            }

            $vote = EventVote::updateOrCreate(
                ['road_event_id' => $duplicate->id, 'user_id' => $user->id],
                ['vote' => 1],
            );
            $duplicate = $this->confidence->recalculate($duplicate);

            return (new EventResource($duplicate->loadCount([
                'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
                'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
            ])))
                ->additional([
                    'message' => 'Рядом уже есть похожее событие — оно подтверждено вашим голосом.',
                    'merged' => true,
                ])
                ->response()
                ->setStatusCode(200);
        }

        $event = DB::transaction(function () use ($data, $user) {
            $ttlByType = config('events.ttl_minutes', []);
            $ttl = (int) ($ttlByType[$data['type']] ?? $ttlByType['default'] ?? 360);

            return RoadEvent::create([
                'title' => $data['title'],
                'type' => $data['type'],
                'highway' => $data['highway'] ?? null,
                'location' => $data['location'],
                'description' => $data['description'] ?? '',
                'status' => 'checking',                  // на проверке, пока не накопит голосов
                'importance' => $data['importance'] ?? 'medium',
                'delay_minutes' => (int) ($data['delay_minutes'] ?? 0),
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'confidence_score' => (int) config('events.confidence.base', 1),
                'image' => $data['image'] ?? null,
                'gallery' => $data['gallery'] ?? [],
                'video_url' => $data['video_url'] ?? null,
                'reported_at' => now(),
                'expires_at' => now()->addMinutes($ttl),
                'created_by_user_id' => $user->id,
            ]);
        });

        $event = $this->confidence->recalculate($event);

        // Сразу пробуем уведомить подписчиков активных маршрутов (если confidence уже выше порога).
        $notified = $this->notifier->notifyAboutEvent($event);

        return (new EventResource($event->loadCount([
            'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
            'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
        ])))
            ->additional(['notified_users' => $notified])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/events/{event}/vote
     */
    public function vote(VoteEventRequest $request, RoadEvent $event): JsonResponse
    {
        $user = $request->user();
        $vote = (int) $request->input('vote');

        if ((int) $event->created_by_user_id === (int) $user->id) {
            return response()->json([
                'message' => 'Нельзя голосовать за собственное событие.',
            ], 422);
        }

        $existingVote = EventVote::where('road_event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            return response()->json([
                'message' => $existingVote->vote > 0
                    ? 'Вы уже подтвердили это событие. Пожаловаться на него после подтверждения нельзя.'
                    : 'Вы уже пожаловались на это событие. Подтвердить его после жалобы нельзя.',
            ], 422);
        }

        // Антиспам.
        $maxVotes = (int) config('events.spam.max_votes_per_user_per_day', 50);
        $today = $user->eventVotes()->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= $maxVotes) {
            return response()->json([
                'message' => sprintf('Превышен дневной лимит голосов (%d/сут).', $maxVotes),
            ], 429);
        }

        EventVote::updateOrCreate(
            ['road_event_id' => $event->id, 'user_id' => $user->id],
            ['vote' => $vote],
        );

        $event = $this->confidence->recalculate($event);

        // После апвоутов могла подняться confidence — попробуем уведомить.
        $notified = 0;
        if ($vote > 0) {
            $notified = $this->notifier->notifyAboutEvent($event);
        }

        return (new EventResource($event->loadCount([
            'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
            'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
        ])))
            ->additional(['notified_users' => $notified])
            ->response();
    }

    /**
     * POST /api/v1/events/{event}/report — пользовательская жалоба (отрицательный голос с пометкой).
     * Здесь упрощённо — это просто -1 голос; пометки/комментарии — на следующей итерации.
     */
    public function report(Request $request, RoadEvent $event)
    {
        if ((int) $event->created_by_user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'Нельзя жаловаться на собственное событие.',
            ], 422);
        }

        $existingVote = EventVote::where('road_event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingVote) {
            return response()->json([
                'message' => $existingVote->vote > 0
                    ? 'Вы уже подтвердили это событие. Пожаловаться на него после подтверждения нельзя.'
                    : 'Вы уже пожаловались на это событие. Повторная жалоба недоступна.',
            ], 422);
        }

        EventVote::updateOrCreate(
            ['road_event_id' => $event->id, 'user_id' => $request->user()->id],
            ['vote' => -1],
        );
        $event = $this->confidence->recalculate($event);

        return (new EventResource($event->loadCount([
            'votes as votes_up_count' => fn ($q) => $q->where('vote', '>', 0),
            'votes as votes_down_count' => fn ($q) => $q->where('vote', '<', 0),
        ])))
            ->additional(['message' => 'Жалоба отправлена.'])
            ->response();
    }
}
