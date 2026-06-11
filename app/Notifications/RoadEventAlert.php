<?php

namespace App\Notifications;

use App\Models\RoadEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoadEventAlert extends Notification
{
    use Queueable;

    public function __construct(private readonly RoadEvent $event)
    {
    }

    public function via(object $notifiable): array
    {
        return NotificationChannels::for($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('TruckRoute: событие на ' . ($this->event->highway ?: 'дороге'))
            ->greeting('Новое дорожное событие')
            ->line($this->event->title)
            ->line($this->event->description)
            ->line('Место: ' . $this->event->location)
            ->line('Задержка: ' . $this->event->delay_minutes . ' мин.')
            ->action('Открыть уведомления', route('profile.notifications'))
            ->line('Уведомление отправлено, потому что в настройках включены дорожные происшествия.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'road_event_id' => $this->event->id,
            'title' => $this->event->title,
            'type' => $this->event->type,
            'highway' => $this->event->highway,
            'location' => $this->event->location,
            'description' => $this->event->description,
            'status' => $this->event->status,
            'importance' => $this->event->importance,
            'delay_minutes' => $this->event->delay_minutes,
            'reported_at' => optional($this->event->reported_at)->toDateTimeString(),
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'TruckRoute: '.$this->event->title,
            'body' => trim(($this->event->location ?: $this->event->highway).' · задержка '.$this->event->delay_minutes.' мин'),
            'data' => $this->toArray($notifiable),
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        return implode("\n", array_filter([
            'TruckRoute: '.$this->event->title,
            $this->event->description,
            'Место: '.$this->event->location,
            'Задержка: '.$this->event->delay_minutes.' мин.',
            url('/events/'.$this->event->id),
        ]));
    }
}
