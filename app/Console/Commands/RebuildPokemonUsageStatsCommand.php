<?php

namespace App\Console\Commands;

use App\Modules\Stats\Services\RebuildPokemonUsageStatsService;
use Illuminate\Console\Command;
use Throwable;

class RebuildPokemonUsageStatsCommand extends Command
{
    protected $signature = 'usage-stats:rebuild';

    protected $description = 'Rebuild global Pokémon usage aggregates (draft picks/bans, match bring counts, game W/L from pool + playoffs).';

    public function handle(RebuildPokemonUsageStatsService $service): int
    {
        try {
            $service();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Usage stats rebuilt.');

        return self::SUCCESS;
    }
}
