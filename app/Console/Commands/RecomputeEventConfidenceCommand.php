<?php

namespace App\Console\Commands;

use App\Models\RoadEvent;
use App\Services\Events\EventConfidenceService;
use Illuminate\Console\Command;

class RecomputeEventConfidenceCommand extends Command
{
    protected $signature = 'events:recompute-confidence {--event= : ID конкретного события}';
    protected $description = 'Пересчитывает confidence_score у активных событий на основе голосов и фото.';

    public function handle(EventConfidenceService $service): int
    {
        $query = RoadEvent::query()->whereIn('status', ['active', 'checking']);
        if ($id = $this->option('event')) {
            $query->whereKey($id);
        }

        $count = 0;
        $query->chunkById(100, function ($events) use ($service, &$count) {
            foreach ($events as $e) {
                $service->recalculate($e);
                $count++;
            }
        });

        $this->info('Пересчитано событий: '.$count);

        return self::SUCCESS;
    }
}
