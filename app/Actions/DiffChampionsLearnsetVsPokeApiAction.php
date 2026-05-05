<?php

namespace App\Actions;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use App\Modules\Pokedex\Services\SerebiiChampionsAvailableRosterService;
use App\Modules\Pokedex\Services\SerebiiChampionsImporter;
use InvalidArgumentException;
use RuntimeException;

class DiffChampionsLearnsetVsPokeApiAction
{
    public function __construct(
        private readonly PokeApiPokemonGameDataImporter $pokeApiImporter,
        private readonly SerebiiChampionsAvailableRosterService $rosterService,
        private readonly SerebiiChampionsImporter $serebiiChampionsImporter,
    ) {}

    /**
     * Compare `pokemon_generation_data.learnset` for a local Champions (or other) version group against
     * the learnset PokéAPI would build for a reference version group (default scarlet-violet). PokéAPI
     * does not publish champions move lists on `pokemon.moves`, so SV (or another slug) is the baseline.
     *
     * @return array{
     *     db_version_group: string,
     *     pokeapi_version_group: string,
     *     roster_only: bool,
     *     rows_compared: int,
     *     rows_skipped_empty_db_learnset: int,
     *     rows_skipped_pokeapi_unresolved: int,
     *     rows_with_differences: int,
     *     differences: list<array{
     *         pokedex_id: int,
     *         name: string,
     *         only_in_db: list<string>,
     *         only_in_pokeapi: list<string>,
     *     }>,
     * }
     */
    public function handle(
        string $dbVersionGroupSlug = 'champions-reg-ma',
        string $pokeapiVersionGroupSlug = 'scarlet-violet',
        bool $rosterOnly = true,
        ?int $limit = null,
    ): array {
        $versionGroup = VersionGroup::query()->where('slug', $dbVersionGroupSlug)->first();
        if ($versionGroup === null) {
            throw new InvalidArgumentException("Version group [{$dbVersionGroupSlug}] not found.");
        }

        $rosterNames = null;
        if ($rosterOnly) {
            $html = $this->rosterService->fetchRosterHtml();
            if ($html === null) {
                throw new RuntimeException('Could not download the Champions available Pokémon roster from Serebii.');
            }

            $rosterNames = $this->rosterService->resolveUniquePokedexNamesFromHtml($html, $this->serebiiChampionsImporter);
            if ($rosterNames === []) {
                throw new RuntimeException('Roster page parsed zero species. Serebii HTML may have changed.');
            }
        }

        $query = Pokedex::query()
            ->whereHas('generationData', function ($q) use ($versionGroup): void {
                $q->where('version_group_id', $versionGroup->id);
            })
            ->orderBy('id');

        if ($rosterNames !== null) {
            $query->whereIn('name', $rosterNames);
        }

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Pokedex> $pokedexRows */
        $pokedexRows = $query->get();

        $differences = [];
        $skippedEmpty = 0;
        $skippedApi = 0;
        $compared = 0;

        foreach ($pokedexRows as $pokedex) {
            $gen = PokemonGenerationData::query()
                ->where('pokedex_id', $pokedex->id)
                ->where('version_group_id', $versionGroup->id)
                ->first();

            $rawLearnset = $gen?->learnset;
            if (! is_array($rawLearnset) || $rawLearnset === []) {
                $skippedEmpty++;

                continue;
            }

            $dbById = $this->indexLearnsetByMoveId($rawLearnset);
            if ($dbById === []) {
                $skippedEmpty++;

                continue;
            }

            $apiLearnset = $this->pokeApiImporter->learnsetSnapshotForPokedex($pokedex, $pokeapiVersionGroupSlug);
            if ($apiLearnset === null) {
                $skippedApi++;
                $differences[] = [
                    'pokedex_id' => $pokedex->id,
                    'name' => (string) $pokedex->getAttribute('name'),
                    'only_in_db' => array_values(array_map(
                        fn (int $id): string => $this->formatMoveLine($id, $dbById[$id] ?? ''),
                        array_keys($dbById),
                    )),
                    'only_in_pokeapi' => ['(could not resolve PokéAPI species / variety for this row)'],
                ];

                continue;
            }

            $apiById = $this->indexLearnsetByMoveId($apiLearnset);

            $onlyDbIds = array_values(array_diff(array_keys($dbById), array_keys($apiById)));
            $onlyApiIds = array_values(array_diff(array_keys($apiById), array_keys($dbById)));

            $compared++;

            if ($onlyDbIds === [] && $onlyApiIds === []) {
                continue;
            }

            $differences[] = [
                'pokedex_id' => $pokedex->id,
                'name' => (string) $pokedex->getAttribute('name'),
                'only_in_db' => array_values(array_map(
                    fn (int $id): string => $this->formatMoveLine($id, $dbById[$id] ?? ''),
                    $onlyDbIds,
                )),
                'only_in_pokeapi' => array_values(array_map(
                    fn (int $id): string => $this->formatMoveLine($id, $apiById[$id] ?? ''),
                    $onlyApiIds,
                )),
            ];
        }

        return [
            'db_version_group' => $dbVersionGroupSlug,
            'pokeapi_version_group' => $pokeapiVersionGroupSlug,
            'roster_only' => $rosterOnly,
            'rows_compared' => $compared,
            'rows_skipped_empty_db_learnset' => $skippedEmpty,
            'rows_skipped_pokeapi_unresolved' => $skippedApi,
            'rows_with_differences' => count($differences),
            'differences' => $differences,
        ];
    }

    /**
     * @param  list<mixed>|array<int|string, mixed>  $learnset
     * @return array<int, string> move_id => move_name
     */
    private function indexLearnsetByMoveId(array $learnset): array
    {
        $out = [];
        foreach ($learnset as $row) {
            if (! is_array($row) || ! isset($row['move_id']) || ! is_numeric($row['move_id'])) {
                continue;
            }

            $id = (int) $row['move_id'];
            $name = isset($row['move_name']) && is_string($row['move_name']) ? $row['move_name'] : '';

            $out[$id] = $name;
        }

        return $out;
    }

    private function formatMoveLine(int $moveId, string $moveName): string
    {
        $moveName = trim($moveName);

        return $moveName !== '' ? "{$moveName} ({$moveId})" : "(#{$moveId})";
    }
}
