<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('logbook:remind-unsubmitted')->weekdays()->at('17:00')->withoutOverlapping();
Schedule::command('logbook:remind-pending-reviews')->weekdays()->at('09:00')->withoutOverlapping();
