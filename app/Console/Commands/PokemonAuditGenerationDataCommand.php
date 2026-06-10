<?php

namespace App\Console\Commands;

use App\Actions\AuditPokemonGenerationDataVarietiesAction;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class PokemonAuditGenerationDataCommand extends Command
{
    protected $signature = 'pokemon:audit-generation-data
                            {--slug=scarlet-violet : Version group slug to audit}
                            {--limit= : Max pokemon_generation_data rows to check}
                            {--fix : Re-import rows with variety mismatches}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Audit pokemon_generation_data variety IDs against PokéAPI (catches wrong-form imports like Indeedee-F or Tauros-Paldea)';

    public function handle(
        AuditPokemonGenerationDataVarietiesAction $audit,
        PokeApiPokemonGameDataImporter $importer,
    ): int {
        $slug = (string) $this->option('slug');
        $limitRaw = $this->option('limit');
        $limit = ($limitRaw !== null && $limitRaw !== '') ? max(1, (int) $limitRaw) : null;

        try {
            $result = $audit->handle($slug, $limit);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fix') && $result['issues'] !== []) {
            $versionGroup = VersionGroup::query()->where('slug', $slug)->firstOrFail();
            $fixable = collect($result['issues'])
                ->filter(fn (array $row): bool => in_array($row['issue'], ['variety_mismatch', 'missing_stored_pokeapi_id'], true))
                ->pluck('pokedex_id')
                ->unique()
                ->values();

            $this->info('Re-importing '.$fixable->count().' pokedex row(s)…');
            $bar = $this->output->createProgressBar($fixable->count());
            $bar->start();

            foreach ($fixable as $pokedexId) {
                $pokedex = Pokedex::query()->find($pokedexId);
                if ($pokedex !== null) {
                    $importer->import($pokedex, $versionGroup);
                    usleep(150_000);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            try {
                $result = $audit->handle($slug, $limit);
            } catch (Throwable $e) {
                $this->error('Re-audit after fix failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['rows_with_issues'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Pokémon generation data variety audit ['.$result['version_group'].']');
        $this->line('  Rows checked: '.$result['rows_checked']);
        $this->line('  Rows OK: '.$result['rows_ok']);
        $this->line('  Rows with issues: '.$result['rows_with_issues']);
        $this->newLine();

        foreach ($result['issues'] as $row) {
            $this->warn($row['name'].' [pokedex_id '.$row['pokedex_id'].', nationaldex '.$row['nationaldex_id'].']');
            $this->line('  Issue: '.$row['issue']);
            if ($row['stored_pokeapi_pokemon_id'] !== null) {
                $storedLabel = $row['stored_variety_name'] ?? '(unknown variety name)';
                $this->line('  Stored: '.$row['stored_pokeapi_pokemon_id'].' ('.$storedLabel.')');
            } else {
                $this->line('  Stored: (null)');
            }
            if ($row['expected_pokeapi_pokemon_id'] !== null) {
                $expectedLabel = $row['expected_variety_name'] ?? '(unknown variety name)';
                $this->line('  Expected: '.$row['expected_pokeapi_pokemon_id'].' ('.$expectedLabel.')');
            } else {
                $this->line('  Expected: (could not resolve variety for this pokedex row)');
            }
            $this->newLine();
        }

        if ($result['rows_with_issues'] === 0) {
            $this->info('All checked rows match the expected PokéAPI variety.');

            return self::SUCCESS;
        }

        $this->comment('Run with --fix to re-import mismatched rows, or pokemon:import-version-group '.$slug.' --id=<pokedex_id> for one species.');

        return self::FAILURE;
    }
}
