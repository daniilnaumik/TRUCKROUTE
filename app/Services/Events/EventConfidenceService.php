<?php

namespace App\Services\Events;

use App\Models\RoadEvent;

/**
 * Пересчёт confidence_score события на основе голосов и наличия фото.
 * Формула простая и прозрачная для защиты:
 *   score = base + (up - down) * weight + (photo ? bonus : 0)
 * затем клампится в [min, max].
 */
class EventConfidenceService
{
    public function recalculate(RoadEvent $event): RoadEvent
    {
        $cfg = config('events.confidence');

        $up = $event->votes()->where('vote', '>', 0)->count();
        $down = $event->votes()->where('vote', '<', 0)->count();

        $score = (int) $cfg['base']
            + ($up - $down) * (int) $cfg['vote_weight']
            + ($event->image ? (int) $cfg['photo_bonus'] : 0);

        $score = max((int) $cfg['min'], min((int) $cfg['max'], $score));

        // Авто-перевод "checking → active" по достижении порога доверия.
        $autoActive = (int) $cfg['auto_active_threshold'];
        $statusUpdate = ($event->status === 'checking' && $score >= $autoActive) ? 'active' : null;

        $event->update(array_filter([
            'confidence_score' => $score,
            'status' => $statusUpdate,
        ], fn ($v) => $v !== null));

        return $event->refresh();
    }
}
