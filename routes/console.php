<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('feeds:sync')->everyFifteenMinutes();
Schedule::command('webhook:prune-seen')->daily(); // php artisan feeds:sync

// php artisan feeds:sync reddit
