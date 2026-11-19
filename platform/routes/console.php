<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (run `php artisan schedule:work` locally, or a cron calling schedule:run in production).
*/
Schedule::command('pricing:apply-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:release-expired')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:verify')->dailyAt('02:00')->timezone('Asia/Dhaka');
