<?php

namespace App\Services\Events;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Notifications\RoadEventOnRouteNotification;

/**
 * Идёт по всем "активным" RoutePlan'ам и уведомляет владельца, если событие
 * попадает в коридор маршрута. Защита от дубль-нотификаций: один пользователь
 * получает по одному событию максимум одно уведомление (поиск в notifications).
 */
class RouteSubscriptionNotifier
{
    public function __construct(private readonly RouteEventMatcher $matcher)
    {
    }

    public function notifyAboutEvent(RoadEvent $event): int
    {
        if ($event->lat === null || $event->lng === null) {
            return 0;
        }

        $minConfidence = (int) config('events.route_subscription.min_confidence_for_notify', 2);
        if ((int) $event->confidence_score < $minConfidence) {
            return 0;
        }

        $window = (int) config('events.route_subscription.active_route_window_hours', 48);
        $now = now();
        $sent = 0;

        // Активные маршруты: либо ETA впереди, либо без ETA, но созданы недавно.
        RoutePlan::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->whereNotNull('polyline_json')
            ->where(function ($q) use ($now, $window) {
                $q->where('arrival_time', '>', $now)
                  ->orWhere(function ($q2) use ($now, $window) {
                      $q2->whereNull('arrival_time')
                         ->where('created_at', '>=', $now->copy()->subHours($window));
                  });
            })
            ->chunk(50, function ($routes) use ($event, &$sent) {
                foreach ($routes as $plan) {
                    /** @var RoutePlan $plan */
                    $user = $plan->user;
                    if (!$user) {
                        continue;
                    }

                    // Уже уведомляли этого юзера про это событие на этом маршруте? — пропустить.
                    $already = $user->notifications()
                        ->where('type', RoadEventOnRouteNotification::class)
                        ->get()
                        ->first(function ($n) use ($event, $plan) {
                            return (int) ($n->data['road_event_id'] ?? 0) === $event->id
                                && (int) ($n->data['route_plan_id'] ?? 0) === $plan->id;
                        });
                    if ($already) {
                        continue;
                    }

                    $distance = $this->matcher->distanceToRouteMeters($plan, $event);
                    if ($distance === null) {
                        continue;
                    }

                    $corridorM = (float) config('events.route_subscription.corridor_km', 5.0) * 1000;
                    if ($distance > $corridorM) {
                        continue;
                    }

                    $user->notify(new RoadEventOnRouteNotification($event, $plan, $distance));
                    $sent++;
                }
            });

        return $sent;
    }
}
