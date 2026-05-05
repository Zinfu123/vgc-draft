<?php

namespace App\Actions;

use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class DiffChampionsRegionalDexVsPokeApiAction
{
    private const DEFAULT_POKEAPI_POKEDEX = 'champions';

    /**
     * Compare PokéAPI's Champions regional Pokédex (species list) to species implied by local
     * `pokemon_generation_data` rows for a version group. Species on the app side are resolved
     * using the same convention as {@see \App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter}:
     * {@code floor(nationaldex_id)} → {@code GET /pokemon-species/{id}/} → {@code name}.
     *
     * @return array{
     *     version_group_slug: string,
     *     pokeapi_pokedex: string,
     *     pokeapi_species: list<string>,
     *     database_species: list<string>,
     *     only_in_pokeapi: list<string>,
     *     only_in_database: list<string>,
     *     unresolved_nationaldex_floors: list<int>,
     * }
     */
    public function handle(
        string $versionGroupSlug = 'champions-reg-ma',
        ?string $pokeapiPokedexIdentifier = null,
    ): array {
        $versionGroup = VersionGroup::query()->where('slug', $versionGroupSlug)->first();
        if ($versionGroup === null) {
            throw new InvalidArgumentException("Version group [{$versionGroupSlug}] not found.");
        }

        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $pokedexId = $pokeapiPokedexIdentifier ?? self::DEFAULT_POKEAPI_POKEDEX;

        $pokeapiSpecies = $this->fetchPokeApiRegionalDexSpeciesNames($baseUrl, $pokedexId);

        $nationaldexFloors = $this->distinctNationaldexFloorsForVersionGroup($versionGroup->id);
        [$databaseSpecies, $unresolvedFloors] = $this->resolveSpeciesNamesFromNationaldexFloors($baseUrl, $nationaldexFloors);

        sort($pokeapiSpecies);
        sort($databaseSpecies);

        $onlyInPokeapi = array_values(array_diff($pokeapiSpecies, $databaseSpecies));
        $onlyInDatabase = array_values(array_diff($databaseSpecies, $pokeapiSpecies));

        return [
            'version_group_slug' => $versionGroupSlug,
            'pokeapi_pokedex' => (string) $pokedexId,
            'pokeapi_species' => $pokeapiSpecies,
            'database_species' => $databaseSpecies,
            'only_in_pokeapi' => $onlyInPokeapi,
            'only_in_database' => $onlyInDatabase,
            'unresolved_nationaldex_floors' => $unresolvedFloors,
        ];
    }

    /**
     * @return list<string>
     */
    private function fetchPokeApiRegionalDexSpeciesNames(string $baseUrl, string $pokedexIdentifier): array
    {
        $response = Http::timeout(60)->acceptJson()->get("{$baseUrl}/pokedex/{$pokedexIdentifier}/");
        if (! $response->successful()) {
            throw new RuntimeException(
                "PokéAPI pokedex request failed ({$response->status()}): {$baseUrl}/pokedex/{$pokedexIdentifier}/"
            );
        }

        /** @var list<array{pokemon_species: array{name: string}}> $entries */
        $entries = $response->json('pokemon_entries') ?? [];

        $names = [];
        foreach ($entries as $entry) {
            $name = $entry['pokemon_species']['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<int>
     */
    private function distinctNationaldexFloorsForVersionGroup(int $versionGroupId): array
    {
        $rows = DB::table('pokemon_generation_data as pgd')
            ->join('pokedex as p', 'p.id', '=', 'pgd.pokedex_id')
            ->where('pgd.version_group_id', $versionGroupId)
            ->select('p.nationaldex_id')
            ->distinct()
            ->get();

        $floors = [];
        foreach ($rows as $row) {
            $floors[] = (int) floor((float) $row->nationaldex_id);
        }

        return array_values(array_unique($floors));
    }

    /**
     * @param  list<int>  $nationaldexFloors
     * @return array{0: list<string>, 1: list<int>}
     */
    private function resolveSpeciesNamesFromNationaldexFloors(string $baseUrl, array $nationaldexFloors): array
    {
        if ($nationaldexFloors === []) {
            return [[], []];
        }

        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $nationaldexFloors): void {
            foreach ($nationaldexFloors as $id) {
                $pool->as((string) $id)->timeout(60)->acceptJson()->get("{$baseUrl}/pokemon-species/{$id}/");
            }
        });

        $names = [];
        $unresolved = [];

        foreach ($nationaldexFloors as $id) {
            $key = (string) $id;
            $response = $responses[$key] ?? null;
            if ($response === null || ! $response->successful()) {
                $unresolved[] = $id;

                continue;
            }

            $name = $response->json('name');
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            } else {
                $unresolved[] = $id;
            }
        }

        return [array_values(array_unique($names)), $unresolved];
    }
}
