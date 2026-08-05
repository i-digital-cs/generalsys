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

// Forgotten clock-out: close at expected_end + grace (default 60m).
Schedule::command('attendance:auto-clock-out')
    ->everyFiveMinutes()
    ->withoutOverlapping();
