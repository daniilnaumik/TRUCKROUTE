<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Заполняем приближённые координаты для демо-объектов.
 * Каждая трасса аппроксимируется как прямая между двумя опорными точками,
 * km_marker интерполируется в долю пути. Этого достаточно для проверки
 * радиус-поиска и отрисовки на карте; реальные POI будут вводить провайдеры.
 */
return new class extends Migration
{
    public function up(): void
    {
        $highways = [
            // Трасса => [полная длина км, [lat,lng старт], [lat,lng конец]]
            'М-4 Дон' => [
                'length' => 1540,
                'start' => [55.7558, 37.6173],   // Москва
                'end' => [45.0355, 38.9753],     // Краснодар
            ],
            'М-7 Волга' => [
                'length' => 820,
                'start' => [55.7558, 37.6173],   // Москва
                'end' => [55.8304, 49.0661],     // Казань
            ],
            'Р-22 Каспий' => [
                'length' => 1380,
                'start' => [55.7558, 37.6173],   // Москва
                'end' => [48.7080, 44.5133],     // Волгоград
            ],
        ];

        $rows = DB::table('service_objects')
            ->whereIn('highway', array_keys($highways))
            ->whereNotNull('km_marker')
            ->whereNull('lat')
            ->get(['id', 'highway', 'km_marker']);

        foreach ($rows as $row) {
            $cfg = $highways[$row->highway];
            $t = min(1.0, max(0.0, $row->km_marker / $cfg['length']));
            $lat = $cfg['start'][0] + ($cfg['end'][0] - $cfg['start'][0]) * $t;
            $lng = $cfg['start'][1] + ($cfg['end'][1] - $cfg['start'][1]) * $t;

            DB::table('service_objects')->where('id', $row->id)->update([
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'verified' => true,
            ]);
        }

        // Аналогично — для road_events с известной трассой, но без координат.
        $eventRows = DB::table('road_events')
            ->whereIn('highway', array_keys($highways))
            ->whereNull('lat')
            ->get(['id', 'highway']);

        foreach ($eventRows as $row) {
            $cfg = $highways[$row->highway];
            $t = 0.5; // событие посреди трассы — для демо
            $lat = $cfg['start'][0] + ($cfg['end'][0] - $cfg['start'][0]) * $t;
            $lng = $cfg['start'][1] + ($cfg['end'][1] - $cfg['start'][1]) * $t;

            DB::table('road_events')->where('id', $row->id)->update([
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
            ]);
        }
    }

    public function down(): void
    {
        // Координаты можно оставить — это безопасный backfill. Откат не нужен.
    }
};
