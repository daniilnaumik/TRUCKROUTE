<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Каждые 10 минут: закрываем протухшие, пересчитываем confidence, сливаем дубли.
// withoutOverlapping — чтобы два инстанса не наехали друг на друга при задержках.
Schedule::command('events:expire')->everyTenMinutes()->withoutOverlapping();
Schedule::command('events:recompute-confidence')->everyTenMinutes()->withoutOverlapping();
Schedule::command('events:merge-dupes')->everyTenMinutes()->withoutOverlapping();
