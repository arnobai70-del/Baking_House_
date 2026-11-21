<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (run `php artisan schedule:work` locally, or a cron calling schedule:run in production).
*/
Schedule::command('pricing:apply-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:release-expired')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:verify')->dailyAt('02:00')->timezone('Asia/Dhaka');
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('quotations:expire')->hourly()->withoutOverlapping();
Schedule::command('media:withdraw-expired')->dailyAt('01:00')->timezone('Asia/Dhaka');
Schedule::command('crm:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

/* Heartbeat for `php artisan app:readiness`: proves the cron entry for schedule:run is active. */
Schedule::call(fn () => Cache::put('scheduler:heartbeat', now()->toIso8601String(), now()->addDay()))->everyMinute()->name('scheduler-heartbeat');
