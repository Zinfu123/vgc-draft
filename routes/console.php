<?php

use App\Jobs\RebuildPokemonUsageStatsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RebuildPokemonUsageStatsJob)
    ->name('rebuild-pokemon-usage-stats')
    ->dailyAt('05:00')
    ->withoutOverlapping(180);
