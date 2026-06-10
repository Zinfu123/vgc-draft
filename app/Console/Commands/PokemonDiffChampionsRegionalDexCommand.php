<?php

namespace App\Console\Commands;

use App\Actions\DiffChampionsRegionalDexVsPokeApiAction;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PokemonDiffChampionsRegionalDexCommand extends Command
{
    protected $signature = 'pokemon:diff-champions-regional-dex
                            {--slug=champions-reg-ma : Local version_groups.slug to compare}
                            {--pokeapi-pokedex=champions : PokéAPI pokedex id or name (Champions regional dex is usually champions)}';

    protected $description = 'Diff PokéAPI Champions regional Pokédex species vs species covered by pokemon_generation_data for a version group';

    public function handle(DiffChampionsRegionalDexVsPokeApiAction $diff): int
    {
        $slug = (string) $this->option('slug');
        $pokedex = (string) $this->option('pokeapi-pokedex');

        try {
            $result = $diff->handle($slug, $pokedex);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Regional dex comparison');
        $this->line('  Local version group: '.$result['version_group_slug']);
        $this->line('  PokéAPI pokedex: '.$result['pokeapi_pokedex']);
        $this->line('  PokéAPI species count: '.count($result['pokeapi_species']));
        $this->line('  Database species count (unique): '.count($result['database_species']));
        $this->newLine();

        if ($result['unresolved_nationaldex_floors'] !== []) {
            $this->warn('Could not resolve species for floor(nationaldex_id): '.implode(', ', $result['unresolved_nationaldex_floors']));
            $this->newLine();
        }

        $this->info('Only in PokéAPI regional dex (not in your version group data): '.count($result['only_in_pokeapi']));
        if ($result['only_in_pokeapi'] !== []) {
            $this->table(['species'], array_map(fn (string $n): array => [$n], $result['only_in_pokeapi']));
        }

        $this->info('Only in your database (not in PokéAPI regional dex): '.count($result['only_in_database']));
        if ($result['only_in_database'] !== []) {
            $this->table(['species'], array_map(fn (string $n): array => [$n], $result['only_in_database']));
        }

        if ($result['only_in_pokeapi'] === [] && $result['only_in_database'] === [] && $result['unresolved_nationaldex_floors'] === []) {
            $this->info('Sets match (same unique species names).');
        }

        return self::SUCCESS;
    }
}
