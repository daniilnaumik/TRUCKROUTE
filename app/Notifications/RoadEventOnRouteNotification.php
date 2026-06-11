<?php

namespace App\Notifications;

use App\Models\RoadEvent;
use App\Models\RoutePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Нотификация о событии, попавшем в коридор активного маршрута пользователя.
 * Каналы: database (для API/web), mail. Для мобилки в Итерации 5 добавим 'fcm'
 * — здесь достаточно расширить via().
 */
class RoadEventOnRouteNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly RoadEvent $event,
        private readonly RoutePlan $route,
        private readonly float $distanceMeters,
    ) {
    }

    public function via(object $notifiable): array
    {
        // FCM-канал самостоятельно решает что делать без ключа — поэтому держим его всегда.
        return NotificationChannels::for($notifiable);
    }

    /**
     * Payload для FcmChannel — заголовок, тело и сырые данные для in-app навигации.
     *
     * @return array{title: string, body: string, data: array<string, mixed>, priority?: string}
     */
    public function toFcm(object $notifiable): array
    {
        $importanceMap = ['high' => 'high', 'medium' => 'normal', 'low' => 'normal'];

        return [
            'title' => 'TruckRoute: '.$this->event->type.' на маршруте',
            'body' => sprintf(
                '%s · %.1f км от вашего маршрута',
                $this->event->title,
                $this->distanceMeters / 1000,
            ),
            'priority' => $importanceMap[$this->event->importance] ?? 'high',
            'data' => $this->toArray($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('TruckRoute: событие на маршруте')
            ->greeting('Дорожное событие рядом с вашим маршрутом')
            ->line($this->event->title)
            ->line($this->event->description)
            ->line(sprintf('Расстояние до вашего маршрута: %.1f км', $this->distanceMeters / 1000))
            ->line('Задержка: '.$this->event->delay_minutes.' мин.')
            ->action('Пересчитать маршрут', url('/api/v1/routes/'.$this->route->id.'/recalculate'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'road_event_id' => $this->event->id,
            'route_plan_id' => $this->route->id,
            'title' => $this->event->title,
            'type' => $this->event->type,
            'highway' => $this->event->highway,
            'location' => $this->event->location,
            'description' => $this->event->description,
            'importance' => $this->event->importance,
            'delay_minutes' => $this->event->delay_minutes,
            'distance_to_route_m' => (int) round($this->distanceMeters),
            'distance_to_route_km' => round($this->distanceMeters / 1000, 2),
            'lat' => (float) $this->event->lat,
            'lng' => (float) $this->event->lng,
            'reported_at' => optional($this->event->reported_at)->toIso8601String(),
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        return implode("\n", [
            'TruckRoute: событие рядом с маршрутом',
            $this->event->title,
            sprintf('До маршрута: %.1f км', $this->distanceMeters / 1000),
            'Задержка: '.$this->event->delay_minutes.' мин.',
            url('/events/'.$this->event->id),
        ]);
    }
}
