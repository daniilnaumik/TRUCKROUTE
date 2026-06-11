<?php

namespace App\Notifications;

use App\Models\RouteRecommendation;
use App\Models\ServiceObject;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent when the driver is within notification_radius_km of an upcoming stop.
 * Stored in the `notifications` table (database channel) and pushed via FCM
 * if the user has a registered device token.
 */
class ProximityAlert extends Notification
{
    use Queueable;

    public function __construct(
        private readonly RouteRecommendation $recommendation,
        private readonly ServiceObject $poi,
        private readonly float $distanceKm,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for($notifiable, includeEmail: false);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $typeLabels = [
            'fuel'      => 'АЗС',
            'rest'      => 'Остановка',
            'overnight' => 'Ночлег',
            'food'      => 'Питание',
        ];

        $typeLabel = $typeLabels[$this->recommendation->type] ?? ucfirst($this->recommendation->type);

        return [
            'type'       => 'proximity_alert',
            'event_type' => $typeLabel,
            'title'      => "{$typeLabel} через " . round($this->distanceKm, 1) . ' км',
            'body'       => $this->poi->name . ' — ' . ($this->poi->services ?? $this->poi->location),
            'poi_id'     => $this->poi->id,
            'poi_name'   => $this->poi->name,
            'poi_type'   => $this->poi->type,
            'poi_lat'    => $this->poi->lat,
            'poi_lng'    => $this->poi->lng,
            'rec_type'   => $this->recommendation->type,
            'distance_km' => round($this->distanceKm, 1),
            'eta_at'     => $this->recommendation->eta_at?->toIso8601String(),
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'data' => $data,
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        $data = $this->toDatabase($notifiable);

        return implode("\n", [
            $data['title'],
            $data['body'],
            'Расстояние: '.$data['distance_km'].' км',
            url('/places/'.$this->poi->id),
        ]);
    }
}
