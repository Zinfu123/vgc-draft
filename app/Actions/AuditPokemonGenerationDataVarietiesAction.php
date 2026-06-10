<?php

namespace App\Actions;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use InvalidArgumentException;

class AuditPokemonGenerationDataVarietiesAction
{
    public function __construct(
        private readonly PokeApiPokemonGameDataImporter $pokeApiImporter,
    ) {}

    /**
     * Compare stored pokemon_generation_data.pokeapi_pokemon_id values against the variety
     * our importer would resolve today for each pokedex row.
     *
     * @return array{
     *     version_group: string,
     *     rows_checked: int,
     *     rows_ok: int,
     *     rows_with_issues: int,
     *     issues: list<array{
     *         pokedex_id: int,
     *         name: string,
     *         nationaldex_id: string,
     *         stored_pokeapi_pokemon_id: int|null,
     *         stored_variety_name: string|null,
     *         expected_pokeapi_pokemon_id: int|null,
     *         expected_variety_name: string|null,
     *         issue: string,
     *     }>,
     * }
     */
    public function handle(string $versionGroupSlug = 'scarlet-violet', ?int $limit = null): array
    {
        $versionGroup = VersionGroup::query()->where('slug', $versionGroupSlug)->first();
        if ($versionGroup === null) {
            throw new InvalidArgumentException("Version group [{$versionGroupSlug}] not found.");
        }

        $query = PokemonGenerationData::query()
            ->with('pokedex')
            ->where('version_group_id', $versionGroup->id)
            ->orderBy('pokedex_id');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, PokemonGenerationData> $rows */
        $rows = $query->get();

        /** @var array<int, array<string, mixed>|null> $speciesCache */
        $speciesCache = [];

        $issues = [];
        $ok = 0;

        foreach ($rows as $generationData) {
            $pokedex = $generationData->pokedex;
            if ($pokedex === null) {
                continue;
            }

            $speciesId = (int) floor((float) $pokedex->getAttribute('nationaldex_id'));
            if (! array_key_exists($speciesId, $speciesCache)) {
                $speciesCache[$speciesId] = $this->pokeApiImporter->fetchSpeciesPayload($speciesId);
                usleep(100_000);
            }

            $species = $speciesCache[$speciesId];
            $expected = $species !== null
                ? $this->pokeApiImporter->expectedVarietyForPokedex($pokedex, $species)
                : null;

            $storedId = $generationData->pokeapi_pokemon_id !== null
                ? (int) $generationData->pokeapi_pokemon_id
                : null;

            $storedVarietyName = ($storedId !== null && $species !== null)
                ? $this->varietyNameForPokemonId($species, $storedId)
                : null;

            $issue = $this->classifyIssue($storedId, $expected);

            if ($issue === null) {
                $ok++;

                continue;
            }

            $issues[] = [
                'pokedex_id' => $pokedex->id,
                'name' => (string) $pokedex->getAttribute('name'),
                'nationaldex_id' => (string) $pokedex->getAttribute('nationaldex_id'),
                'stored_pokeapi_pokemon_id' => $storedId,
                'stored_variety_name' => $storedVarietyName,
                'expected_pokeapi_pokemon_id' => $expected['pokeapi_pokemon_id'] ?? null,
                'expected_variety_name' => $expected['variety_name'] ?? null,
                'issue' => $issue,
            ];
        }

        return [
            'version_group' => $versionGroupSlug,
            'rows_checked' => $rows->count(),
            'rows_ok' => $ok,
            'rows_with_issues' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * @param  array{pokeapi_pokemon_id: int, variety_name: string}|null  $expected
     */
    private function classifyIssue(?int $storedId, ?array $expected): ?string
    {
        if ($expected === null) {
            return 'unresolved_expected_variety';
        }

        if ($storedId === null) {
            return 'missing_stored_pokeapi_id';
        }

        if ($storedId !== $expected['pokeapi_pokemon_id']) {
            return 'variety_mismatch';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $species
     */
    private function varietyNameForPokemonId(array $species, int $pokemonId): ?string
    {
        foreach ($species['varieties'] ?? [] as $variety) {
            if (! is_array($variety) || empty($variety['pokemon']['url'])) {
                continue;
            }

            $id = $this->extractTrailingIdFromUrl((string) $variety['pokemon']['url']);
            if ($id === $pokemonId) {
                return isset($variety['pokemon']['name']) ? (string) $variety['pokemon']['name'] : null;
            }
        }

        return null;
    }

    private function extractTrailingIdFromUrl(string $url): ?int
    {
        if (preg_match('#/(\d+)/?\z#', $url, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
