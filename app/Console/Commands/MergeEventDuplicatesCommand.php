<?php

namespace App\Console\Commands;

use App\Models\EventVote;
use App\Models\RoadEvent;
use App\Services\Events\EventConfidenceService;
use App\Support\Haversine;
use Illuminate\Console\Command;

class MergeEventDuplicatesCommand extends Command
{
    protected $signature = 'events:merge-dupes
                            {--dry-run : Показать кандидатов без изменений}';
    protected $description = 'Периодически сливает дублирующиеся события одного типа в заданном радиусе.';

    public function handle(EventConfidenceService $confidence): int
    {
        if (! config('events.dedupe.enabled', true)) {
            $this->info('Дедупликация отключена (events.dedupe.enabled = false).');
            return self::SUCCESS;
        }

        $radiusMeters = (float) config('events.dedupe.radius_meters', 500);
        $dryRun = $this->option('dry-run');
        $merged = 0;

        // Обрабатываем каждый тип событий отдельно.
        $types = RoadEvent::query()
            ->active()
            ->whereNotNull('lat')
            ->distinct()
            ->pluck('type');

        foreach ($types as $type) {
            $events = RoadEvent::query()
                ->active()
                ->where('type', $type)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderBy('id') // более ранние — «родители»
                ->get();

            // Хэш-сет уже помеченных как дубль ID — O(1) lookup.
            $expiredIds = [];

            for ($i = 0; $i < count($events); $i++) {
                $parent = $events[$i];
                if (isset($expiredIds[$parent->id])) {
                    continue;
                }

                for ($j = $i + 1; $j < count($events); $j++) {
                    $dup = $events[$j];
                    if (isset($expiredIds[$dup->id])) {
                        continue;
                    }

                    $dist = Haversine::distanceMeters(
                        $parent->lat, $parent->lng,
                        $dup->lat, $dup->lng,
                    );

                    if ($dist > $radiusMeters) {
                        continue;
                    }

                    $expiredIds[$dup->id] = true;
                    $merged++;

                    if ($dryRun) {
                        $this->line(sprintf(
                            '  Дубль #%d → #%d [%s] dist=%dm',
                            $dup->id, $parent->id, $type, (int) $dist,
                        ));
                        continue;
                    }

                    // Переносим уникальные голоса дубля к родителю.
                    $parentVoters = EventVote::where('road_event_id', $parent->id)->pluck('user_id');

                    EventVote::where('road_event_id', $dup->id)
                        ->whereNotIn('user_id', $parentVoters)
                        ->update(['road_event_id' => $parent->id]);

                    // Удаляем оставшиеся голоса дубля (уже есть у родителя).
                    EventVote::where('road_event_id', $dup->id)->delete();

                    // Закрываем дубль.
                    $dup->update(['status' => 'expired']);

                    // Пересчитываем confidence родителя.
                    $confidence->recalculate($parent);
                }
            }
        }

        $this->info(($dryRun ? 'Найдено' : 'Слито').' дублей: '.$merged);

        return self::SUCCESS;
    }
}
