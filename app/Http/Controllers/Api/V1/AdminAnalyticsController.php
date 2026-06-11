<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PoiRouteSelection;
use App\Models\RoutePlan;
use App\Models\TripSession;
use App\Models\UserActivityDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,14,30,90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        $activityDays = UserActivityDay::query()
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'activity_date', 'platform']);

        $dau = UserActivityDay::query()
            ->whereDate('activity_date', today())
            ->distinct('user_id')
            ->count('user_id');
        $mau = UserActivityDay::query()
            ->whereDate('activity_date', '>=', now()->subDays(29)->toDateString())
            ->distinct('user_id')
            ->count('user_id');

        $plans = RoutePlan::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'created_at']);
        $planIds = $plans->pluck('id');
        $cohortTrips = $planIds->isEmpty()
            ? collect()
            : TripSession::query()
                ->whereIn('route_plan_id', $planIds)
                ->get(['id', 'route_plan_id', 'status', 'started_at', 'ended_at']);
        $periodTrips = TripSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->get(['id', 'route_plan_id', 'status', 'started_at', 'ended_at', 'notified_recommendation_ids']);

        $startedRoutes = $cohortTrips->pluck('route_plan_id')->filter()->unique()->count();
        $completedRoutes = $cohortTrips
            ->where('status', 'ended')
            ->pluck('route_plan_id')
            ->filter()
            ->unique()
            ->count();

        $sessionIds = $periodTrips->pluck('id');
        $decisions = $sessionIds->isEmpty()
            ? collect()
            : PoiRouteSelection::query()
                ->whereIn('trip_session_id', $sessionIds)
                ->get(['action', 'created_at']);
        $trackedRecommendationShows = $periodTrips->sum(
            fn (TripSession $session) => count($session->notified_recommendation_ids ?? [])
        );
        $accepted = $decisions->where('action', 'accepted')->count();
        $rejected = $decisions->where('action', 'rejected')->count();
        $recommendationsShown = max($trackedRecommendationShows, $accepted + $rejected);

        $notifications = DB::table('notifications')
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'read_at']);
        $readNotifications = $notifications->whereNotNull('read_at');
        $averageReadMinutes = $readNotifications->isEmpty()
            ? null
            : round((float) $readNotifications->average(function (object $notification): float {
                return Carbon::parse($notification->created_at)
                    ->diffInSeconds(Carbon::parse($notification->read_at)) / 60;
            }), 1);

        $completedTrips = TripSession::query()
            ->with('routePlan:id,drive_time_minutes,fuel_needed_l')
            ->where('status', 'ended')
            ->whereBetween('ended_at', [$from, $to])
            ->get();
        $timeSavedMinutes = 0;
        $timeComparedTrips = 0;
        $fuelSavedLiters = 0.0;
        $fuelMeasuredTrips = 0;

        foreach ($completedTrips as $trip) {
            if (!$trip->routePlan || !$trip->started_at || !$trip->ended_at) {
                continue;
            }

            $actualMinutes = $trip->started_at->diffInSeconds($trip->ended_at) / 60;
            $timeSavedMinutes += max(0, (float) $trip->routePlan->drive_time_minutes - $actualMinutes);
            $timeComparedTrips++;

            if ($trip->actual_fuel_used_l !== null) {
                $fuelSavedLiters += max(
                    0,
                    (float) $trip->routePlan->fuel_needed_l - (float) $trip->actual_fuel_used_l
                );
                $fuelMeasuredTrips++;
            }
        }

        return response()->json([
            'data' => [
                'period' => [
                    'days' => $days,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'audience' => [
                    'dau' => $dau,
                    'mau' => $mau,
                    'stickiness_percent' => $this->percent($dau, $mau),
                    'platforms' => $this->platformBreakdown($activityDays),
                    'series' => $this->activitySeries($activityDays, min($days, 30)),
                ],
                'routes' => [
                    'built' => $plans->count(),
                    'started' => $startedRoutes,
                    'completed' => $completedRoutes,
                    'start_conversion_percent' => $this->percent($startedRoutes, $plans->count()),
                    'completion_conversion_percent' => $this->percent($completedRoutes, $plans->count()),
                    'series' => $this->routeSeries($plans, $periodTrips, min($days, 30)),
                ],
                'recommendations' => [
                    'shown' => $recommendationsShown,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'response_rate_percent' => $this->percent($accepted + $rejected, $recommendationsShown),
                    'acceptance_rate_percent' => $this->percent($accepted, $accepted + $rejected),
                ],
                'notifications' => [
                    'sent' => $notifications->count(),
                    'read' => $readNotifications->count(),
                    'read_rate_percent' => $this->percent($readNotifications->count(), $notifications->count()),
                    'average_read_minutes' => $averageReadMinutes,
                ],
                'savings' => [
                    'time_minutes' => (int) round($timeSavedMinutes),
                    'time_compared_trips' => $timeComparedTrips,
                    'fuel_liters' => round($fuelSavedLiters, 1),
                    'fuel_measured_trips' => $fuelMeasuredTrips,
                ],
                'notes' => [
                    'time' => 'Экономия времени считается для завершённых поездок как разница между плановым и фактическим временем.',
                    'fuel' => 'Экономия топлива считается только для поездок, где при завершении передан фактический расход.',
                ],
            ],
        ]);
    }

    private function percent(int|float $value, int|float $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function platformBreakdown(Collection $activity): array
    {
        return $activity
            ->groupBy('platform')
            ->map(fn (Collection $items) => $items->pluck('user_id')->unique()->count())
            ->sortKeys()
            ->all();
    }

    private function activitySeries(Collection $activity, int $days): array
    {
        $counts = $activity
            ->groupBy(fn (UserActivityDay $item) => Carbon::parse($item->activity_date)->toDateString())
            ->map(fn (Collection $items) => $items->pluck('user_id')->unique()->count());

        return collect(CarbonPeriod::create(now()->subDays($days - 1)->startOfDay(), now()->startOfDay()))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'value' => (int) ($counts[$date->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function routeSeries(Collection $plans, Collection $trips, int $days): array
    {
        $built = $plans->groupBy(fn (RoutePlan $plan) => $plan->created_at->toDateString())->map->count();
        $started = $trips->groupBy(fn (TripSession $trip) => $trip->started_at->toDateString())->map->count();
        $completed = $trips
            ->where('status', 'ended')
            ->filter(fn (TripSession $trip) => $trip->ended_at !== null)
            ->groupBy(fn (TripSession $trip) => $trip->ended_at->toDateString())
            ->map->count();

        return collect(CarbonPeriod::create(now()->subDays($days - 1)->startOfDay(), now()->startOfDay()))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'built' => (int) ($built[$date->toDateString()] ?? 0),
                'started' => (int) ($started[$date->toDateString()] ?? 0),
                'completed' => (int) ($completed[$date->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }
}
