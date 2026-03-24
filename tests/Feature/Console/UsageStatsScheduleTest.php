<?php

use App\Jobs\RebuildPokemonUsageStatsJob;
use App\Modules\Stats\Models\PokemonUsageStatsMeta;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('schedules the pokemon usage stats rebuild job daily', function () {
    $schedule = app(Schedule::class);
    $descriptions = collect($schedule->events())->pluck('description');

    expect($descriptions)->toContain('rebuild-pokemon-usage-stats');
});

it('runs the rebuild service when the job is processed', function () {
    Bus::dispatchSync(new RebuildPokemonUsageStatsJob);

    expect(PokemonUsageStatsMeta::query()->find(1))->not->toBeNull();
});
