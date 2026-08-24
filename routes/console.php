<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hostinger: single cron `* * * * * php artisan schedule:run` drains the DB queue.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

// Missing daily reports: same-day evening warning + next-morning overdue warning.
Schedule::command('reports:warn-missing 0')
    ->dailyAt('21:00')
    ->withoutOverlapping();

Schedule::command('reports:warn-missing 1')
    ->dailyAt('08:00')
    ->withoutOverlapping();
