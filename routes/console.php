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
Schedule::command('system:backup --prune')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
