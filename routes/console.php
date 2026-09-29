<?php

use App\Services\HealthService;
use App\Support\MaintenanceMode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// While a restore is rewriting the database, nothing else should touch it.
Schedule::command('categories:refresh-statuses')->everyMinute()->skip(fn () => MaintenanceMode::restoring());

Schedule::command('backup:tick')->everyMinute()->withoutOverlapping(10);

// Proof that cron is running at all — read by the health tab. Kept in the cache
// (not a table) so it costs nothing and a restore can't overwrite it.
Schedule::call(fn () => Cache::forever(HealthService::HEARTBEAT_KEY, now()->timestamp))
    ->everyMinute()
    ->name('scheduler-heartbeat');

Schedule::command('maintenance:run')->dailyAt('03:00')->withoutOverlapping(30)->skip(fn () => MaintenanceMode::restoring());
