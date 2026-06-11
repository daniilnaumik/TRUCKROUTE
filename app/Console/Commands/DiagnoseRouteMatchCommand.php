<?php

namespace App\Console\Commands;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use App\Services\Events\RouteEventMatcher;
use App\Services\Events\RouteSubscriptionNotifier;
use Illuminate\Console\Command;

class DiagnoseRouteMatchCommand extends Command
{
    protected $signature = 'diagnose:route-match {event} {--route= : Конкретный route_plan_id}';
    protected $description = 'Считает расстояние от события до маршрута и пробует послать уведомление.';

    public function handle(RouteEventMatcher $matcher, RouteSubscriptionNotifier $notif): int
    {
        $event = RoadEvent::find($this->argument('event'));
        if (!$event) {
            $this->error('Event not found.');
            return self::FAILURE;
        }
        $this->line("Event #{$event->id} lat={$event->lat} lng={$event->lng} conf={$event->confidence_score} status={$event->status}");

        $plans = $this->option('route')
            ? RoutePlan::whereKey($this->option('route'))->get()
            : RoutePlan::whereNotNull('user_id')->whereNotNull('polyline_json')->get();

        foreach ($plans as $plan) {
            $d = $matcher->distanceToRouteMeters($plan, $event);
            $this->line(sprintf(
                'Route #%d user=%s arrival=%s polylen=%d → distance=%s',
                $plan->id,
                $plan->user_id,
                $plan->arrival_time?->toIso8601String() ?? 'null',
                count($plan->polyline()),
                $d !== null ? round($d / 1000, 2).' km' : 'null',
            ));
        }

        $this->line('config corridor_km = '.config('events.route_subscription.corridor_km'));
        $this->line('config min_conf = '.config('events.route_subscription.min_confidence_for_notify'));
        $this->line('config window_h = '.config('events.route_subscription.active_route_window_hours'));
        $this->line('now = '.now()->toIso8601String());

        $sent = $notif->notifyAboutEvent($event);
        $this->info('Notifier sent = '.$sent);

        // Полезный helper: вывести середину polyline первого маршрута — туда можно "поставить" тестовое событие.
        $first = $plans->first();
        if ($first) {
            $poly = $first->polyline();
            $mid = $poly[(int) floor(count($poly) / 2)] ?? null;
            if ($mid) {
                $this->line("Middle of route #{$first->id} polyline: lat={$mid[0]} lng={$mid[1]}");
            }
        }

        return self::SUCCESS;
    }
}
