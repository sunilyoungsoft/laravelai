<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler readiness (Phase 0)
|--------------------------------------------------------------------------
|
| No scheduled tasks yet. Add Illuminate\Support\Facades\Schedule definitions
| in this file in later phases.
|
| Local development: php artisan schedule:work
|
*/
