<?php

namespace App\Jobs;

use App\Modules\Stats\Services\RebuildPokemonUsageStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RebuildPokemonUsageStatsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    public function handle(RebuildPokemonUsageStatsService $service): void
    {
        $service();
    }
}
