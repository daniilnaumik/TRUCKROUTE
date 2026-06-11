<?php

namespace App\Console\Commands;

use App\Models\RoadEvent;
use Illuminate\Console\Command;

class ExpireRoadEventsCommand extends Command
{
    protected $signature = 'events:expire {--dry-run : Не менять статус, только показать кандидатов}';
    protected $description = 'Закрывает истёкшие дорожные события по expires_at, либо вычисляет ttl по типу из config/events.php.';

    public function handle(): int
    {
        $now = now();
        $ttlByType = config('events.ttl_minutes', []);
        $defaultTtl = (int) ($ttlByType['default'] ?? 360);

        $expired = [];

        // 1. Явный expires_at в прошлом — закрываем.
        RoadEvent::query()
            ->whereIn('status', ['active', 'checking'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->chunkById(100, function ($events) use (&$expired) {
                foreach ($events as $e) {
                    $expired[] = $e;
                }
            });

        // 2. expires_at не задан — вычисляем по типу и reported_at.
        RoadEvent::query()
            ->whereIn('status', ['active', 'checking'])
            ->whereNull('expires_at')
            ->whereNotNull('reported_at')
            ->chunkById(100, function ($events) use (&$expired, $ttlByType, $defaultTtl, $now) {
                foreach ($events as $e) {
                    $ttl = (int) ($ttlByType[$e->type] ?? $defaultTtl);
                    if ($e->reported_at->copy()->addMinutes($ttl)->lessThanOrEqualTo($now)) {
                        $expired[] = $e;
                    }
                }
            });

        $this->info('Кандидатов на закрытие: '.count($expired));

        if ($this->option('dry-run')) {
            foreach ($expired as $e) {
                $this->line(sprintf('  - #%d [%s] %s', $e->id, $e->type, $e->title));
            }
            return self::SUCCESS;
        }

        foreach ($expired as $e) {
            $e->update(['status' => 'expired']);
        }

        $this->info('Закрыто событий: '.count($expired));

        return self::SUCCESS;
    }
}
