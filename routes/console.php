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

Schedule::command('stats:sync-showdown-vgc-usage')
    ->name('sync-showdown-vgc-usage')
    ->dailyAt('06:15')
    ->withoutOverlapping(600);

Schedule::command('matches:notify-unplayed')
    ->name('matches-notify-unplayed')
    ->dailyAt('23:59')
    ->timezone('America/New_York')
    ->withoutOverlapping(5);

Schedule::command('draft:start-scheduled')
    ->name('draft-start-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
