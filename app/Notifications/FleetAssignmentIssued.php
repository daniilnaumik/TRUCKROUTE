<?php

namespace App\Notifications;

use App\Models\RouteAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FleetAssignmentIssued extends Notification
{
    use Queueable;

    public function __construct(private readonly RouteAssignment $assignment)
    {
    }

    public function via(object $notifiable): array
    {
        return NotificationChannels::for($notifiable, includeEmail: false);
    }

    public function toArray(object $notifiable): array
    {
        $fleetName = $this->assignment->fleet?->name ?? 'автопарка';

        return [
            'type' => 'fleet_assignment',
            'assignment_id' => $this->assignment->id,
            'fleet_id' => $this->assignment->fleet_id,
            'title' => 'Новое задание от автопарка',
            'body' => sprintf(
                '%s: %s → %s',
                $fleetName,
                $this->assignment->origin,
                $this->assignment->destination,
            ),
            'url' => '/assignments/'.$this->assignment->id,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'data' => $data,
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        return implode("\n", [
            $data['title'],
            $data['body'],
            url($data['url']),
        ]);
    }
}
