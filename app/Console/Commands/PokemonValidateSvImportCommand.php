<?php

namespace App\Console\Commands;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiSvDexSpeciesService;
use Illuminate\Console\Command;

class PokemonValidateSvImportCommand extends Command
{
    protected $signature = 'pokemon:validate-sv-import
                            {--slug=scarlet-violet : Version group slug stored in your database (PokeAPI slug)}';

    protected $description = 'Compare PokeAPI Scarlet/Violet Pokédex species counts with your pokemon_game_data import';

    public function handle(PokeApiSvDexSpeciesService $dexService): int
    {
        $slug = (string) $this->option('slug');
        $versionGroup = VersionGroup::query()->where('slug', $slug)->first();
        if ($versionGroup === null) {
            $this->error("Unknown version group [{$slug}] in the database.");

            return self::FAILURE;
        }

        $this->info('Fetching PokeAPI (Paldea + Kitakami + Blueberry Pokédexes, and Generation IX list)…');

        $regionalUnionIds = $dexService->regionalDexUnionSpeciesIds();
        $genNineIds = $dexService->generationNineSpeciesIds();

        if ($regionalUnionIds === []) {
            $this->error('Could not load regional Pokédex data from PokeAPI. Check POKEAPI_URL and your connection.');

            return self::FAILURE;
        }

        $gameDataCount = PokemonGameData::query()
            ->where('version_group_id', $versionGroup->id)
            ->count();

        $localSpeciesIds = Pokedex::query()
            ->whereHas('gameData', function ($q) use ($versionGroup) {
                $q->where('version_group_id', $versionGroup->id);
            })
            ->pluck('nationaldex_id')
            ->map(fn ($v) => (int) floor((float) $v))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $missingInDb = array_values(array_diff($regionalUnionIds, $localSpeciesIds));
        $extraInDb = array_values(array_diff($localSpeciesIds, $regionalUnionIds));

        $this->newLine();
        $this->table(
            ['Source', 'What it measures', 'Count'],
            [
                [
                    'PokeAPI',
                    'Unique species in Paldea (31) + Kitakami (32) + Blueberry (33) dexes',
                    (string) count($regionalUnionIds),
                ],
                [
                    'PokeAPI',
                    'Species introduced in Generation IX only (not full SV obtainability)',
                    (string) count($genNineIds),
                ],
                [
                    'Your DB',
                    "Rows in pokemon_game_data for [{$slug}]",
                    (string) $gameDataCount,
                ],
                [
                    'Your DB',
                    'Distinct FLOOR(nationaldex_id) with SV game_data (species-level)',
                    (string) count($localSpeciesIds),
                ],
            ]
        );

        $this->newLine();
        $this->comment('Regional dex union is the best single PokeAPI number for “species that appear on an in-game SV-area dex”. Your pokedex table may have multiple rows per species (forms); pokemon_game_data rows follow those rows.');
        $this->newLine();

        if ($missingInDb !== []) {
            $this->warn('Species IDs in the regional dex union missing from your DB (no pokedex row with SV data for that FLOOR(nationaldex_id)): '.count($missingInDb));
            $this->line($this->formatIdSample($missingInDb));
        } else {
            $this->info('Every species ID from the three regional dexes has at least one matching national dex (floored) with SV game_data.');
        }

        if ($extraInDb !== []) {
            $this->warn('Distinct species IDs in your SV import not listed on those three regional dexes (transfers, other forms, or data drift): '.count($extraInDb));
            $this->line($this->formatIdSample($extraInDb));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     */
    private function formatIdSample(array $ids): string
    {
        $sample = array_slice($ids, 0, 40);

        return implode(', ', $sample).(count($ids) > 40 ? ' …' : '');
    }
}
