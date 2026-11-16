<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep auction lifecycle statuses synchronized automatically.
Schedule::command('auctions:sync-statuses')->everyMinute()->withoutOverlapping();
Schedule::command('buyers:expire-temporary-accounts')->daily();
