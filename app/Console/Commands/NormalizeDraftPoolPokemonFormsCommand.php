<?php

namespace App\Console\Commands;

use App\Modules\League\Services\NormalizeDraftPoolPokemonFormsService;
use Illuminate\Console\Command;

class NormalizeDraftPoolPokemonFormsCommand extends Command
{
    protected $signature = 'league:normalize-draft-pool-pokemon
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Remap draft pool form Pokémon (e.g. Greninja-Ash) to their canonical base species in templates and league pools';

    public function handle(NormalizeDraftPoolPokemonFormsService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be saved.');
        }

        $stats = $service->normalize($dryRun);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Template rows updated', (string) $stats['template_rows_updated']],
                ['Template rows merged into existing base', (string) $stats['template_rows_merged']],
                ['League pool rows updated', (string) $stats['league_pokemon_updated']],
                ['League pool rows merged into existing base', (string) $stats['league_pokemon_merged']],
            ],
        );

        if ($dryRun) {
            $this->info('Dry run complete. Re-run without --dry-run to apply.');
        } else {
            $this->info('Draft pool Pokémon forms normalized.');
        }

        return self::SUCCESS;
    }
}
