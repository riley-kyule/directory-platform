<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('profiles:expire')->everyMinute()->withoutOverlapping();
Schedule::command('profiles:rotate-listing-order --scheduled')->hourly()->withoutOverlapping();
Schedule::command('system:heartbeat scheduler')->everyMinute()->withoutOverlapping();
Schedule::command('verification:refresh-statuses')->daily()->withoutOverlapping();
Schedule::command('search:prune-term-logs')->daily()->withoutOverlapping();
Schedule::command('conversion:prune')->daily()->withoutOverlapping();
Schedule::command('accounts:purge-deleted')->daily()->withoutOverlapping();
Schedule::command('system:backup --prune')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('system:backup-media --prune')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
// No-ops safely until OPS_RESTORE_DRILL_DB_* is configured with an isolated target.
Schedule::command('system:restore-drill')->weeklyOn(1, '03:30')->withoutOverlapping()->onOneServer();
