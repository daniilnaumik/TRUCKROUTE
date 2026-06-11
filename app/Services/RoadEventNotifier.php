<?php

namespace App\Services;

use App\Models\RoadEvent;
use App\Models\User;
use App\Notifications\RoadEventAlert;

class RoadEventNotifier
{
    public function notifyUsersAbout(RoadEvent $event, ?User $onlyUser = null): int
    {
        $users = $onlyUser
            ? collect([$onlyUser])
            : User::whereHas('settings', function ($query) {
                $query->where('incident_notifications', true);
            })->get();

        $sent = 0;

        foreach ($users as $user) {
            if ($this->alreadyNotified($user, $event)) {
                continue;
            }

            $user->notify(new RoadEventAlert($event));
            $sent++;
        }

        return $sent;
    }

    public function notifyUserAboutCurrentEvents(User $user): int
    {
        if (!$user->settings?->incident_notifications) {
            return 0;
        }

        return RoadEvent::whereIn('status', ['active', 'checking'])
            ->latest('reported_at')
            ->take(10)
            ->get()
            ->sum(fn (RoadEvent $event) => $this->notifyUsersAbout($event, $user));
    }

    private function alreadyNotified(User $user, RoadEvent $event): bool
    {
        return $user->notifications()
            ->where('type', RoadEventAlert::class)
            ->get()
            ->contains(function ($notification) use ($event) {
                return (int) ($notification->data['road_event_id'] ?? 0) === $event->id;
            });
    }
}
