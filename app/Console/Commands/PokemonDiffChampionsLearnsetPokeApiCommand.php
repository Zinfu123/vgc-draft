<?php

namespace App\Console\Commands;

use App\Actions\DiffChampionsLearnsetVsPokeApiAction;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PokemonDiffChampionsLearnsetPokeApiCommand extends Command
{
    protected $signature = 'pokemon:diff-champions-learnsets-pokeapi
                            {--slug=champions-reg-ma : Local version_groups.slug whose learnsets to read}
                            {--pokeapi-version-group=scarlet-violet : PokéAPI version_group name for the reference learnset (PokéAPI has no champions moves)}
                            {--all : Compare all pokedex rows with data for the slug, not only Serebii\'s Champions roster}
                            {--limit= : Max pokedex rows to process after filtering}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Diff local Champions learnsets (DB) vs PokéAPI learnsets for a reference version group (default scarlet-violet)';

    public function handle(DiffChampionsLearnsetVsPokeApiAction $diff): int
    {
        $slug = (string) $this->option('slug');
        $pokeapiVg = (string) $this->option('pokeapi-version-group');
        $rosterOnly = ! (bool) $this->option('all');
        $limitRaw = $this->option('limit');
        $limit = ($limitRaw !== null && $limitRaw !== '') ? max(1, (int) $limitRaw) : null;

        try {
            $result = $diff->handle($slug, $pokeapiVg, $rosterOnly, $limit);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Learnset diff (DB vs PokéAPI)');
        $this->line('  DB version group: '.$result['db_version_group']);
        $this->line('  PokéAPI reference version group: '.$result['pokeapi_version_group']);
        $this->line('  Roster filter: '.($result['roster_only'] ? 'Serebii Champions available list' : 'all rows with generation data'));
        $this->line('  Rows compared (non-empty DB learnset + resolved API): '.$result['rows_compared']);
        $this->line('  Skipped (empty DB learnset): '.$result['rows_skipped_empty_db_learnset']);
        $this->line('  Unresolved PokéAPI form (listed as differences): '.$result['rows_skipped_pokeapi_unresolved']);
        $this->line('  Rows with differences: '.$result['rows_with_differences']);
        $this->newLine();

        foreach ($result['differences'] as $row) {
            $this->warn($row['name'].' [pokedex_id '.$row['pokedex_id'].']');
            if ($row['only_in_db'] !== []) {
                $this->line('  Only in DB: '.implode(', ', $row['only_in_db']));
            }
            if ($row['only_in_pokeapi'] !== []) {
                $this->line('  Only in PokéAPI ('.$result['pokeapi_version_group'].'): '.implode(', ', $row['only_in_pokeapi']));
            }
            $this->newLine();
        }

        if ($result['differences'] === []) {
            $this->info('No differences (or no rows matched the filter).');
        }

        return self::SUCCESS;
    }
}
