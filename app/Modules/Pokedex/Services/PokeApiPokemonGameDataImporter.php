<?php

namespace App\Modules\Pokedex\Services;

use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PokeApiPokemonGameDataImporter
{
    public function import(Pokedex $pokedex, VersionGroup $versionGroup): bool
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $speciesId = (int) floor((float) $pokedex->getAttribute('nationaldex_id'));

        $species = $this->getJson("{$baseUrl}/pokemon-species/{$speciesId}/");
        if ($species === null) {
            Log::warning('PokeAPI species not found', [
                'pokedex_id' => $pokedex->id,
                'species_id' => $speciesId,
            ]);

            return false;
        }

        $pokemonUrl = $this->resolveVarietyPokemonUrl($species, $pokedex);
        if ($pokemonUrl === null) {
            Log::warning('PokeAPI could not resolve variety for pokedex row', [
                'pokedex_id' => $pokedex->id,
                'species_id' => $speciesId,
                'name' => $pokedex->getAttribute('name'),
            ]);

            return false;
        }

        $pokemonId = $this->extractTrailingId($pokemonUrl);
        if ($pokemonId === null) {
            return false;
        }

        $pokemon = $this->getJson("{$baseUrl}/pokemon/{$pokemonId}/");
        if ($pokemon === null) {
            Log::warning('PokeAPI pokemon not found', [
                'pokedex_id' => $pokedex->id,
                'pokeapi_pokemon_id' => $pokemonId,
            ]);

            return false;
        }

        $slug = $versionGroup->slug;
        $learnset = $this->buildLearnset($pokemon, $slug);
        $learnset = $this->mergeAncestorEggMovesIntoLearnset($learnset, $species, $slug);
        if ($learnset === []) {
            $this->deleteSnapshot($pokedex->id, $versionGroup->id);

            Log::info('Skipped pokemon_generation_data: no moves for version group', [
                'pokedex_id' => $pokedex->id,
                'version_group' => $slug,
                'pokeapi_pokemon_id' => $pokemonId,
            ]);

            return false;
        }

        $learnset = $this->mergeReminderMovesFromPriorGenerations($learnset, $pokemon, $slug);

        $stats = $this->mapStats($pokemon);
        [$type1, $type2] = $this->mapTypes($pokemon);
        [$primaryId, $secondaryId, $hiddenId] = $this->mapAbilityIds($pokemon);

        $generationData = PokemonGenerationData::query()->updateOrCreate(
            [
                'pokedex_id' => $pokedex->id,
                'version_group_id' => $versionGroup->id,
            ],
            [
                'pokeapi_pokemon_id' => $pokemonId,
                'hp' => $stats['hp'],
                'atk' => $stats['atk'],
                'def' => $stats['def'],
                'spa' => $stats['spa'],
                'spd' => $stats['spd'],
                'spe' => $stats['spe'],
                'type1' => $type1,
                'type2' => $type2,
                'ability_primary_pokeapi_id' => $primaryId,
                'ability_secondary_pokeapi_id' => $secondaryId,
                'ability_hidden_pokeapi_id' => $hiddenId,
                'learnset' => $learnset,
                'mechanics' => $this->defaultMechanicsForVersionGroup($versionGroup),
            ]
        );

        $this->syncAbilities($pokedex->id, $versionGroup->id, $pokemon);
        $this->hydrateMoveCache($baseUrl, $learnset);

        return true;
    }

    private function deleteSnapshot(int $pokedexId, int $versionGroupId): void
    {
        AbilityGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->delete();

        PokemonGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $pokemon
     */
    private function syncAbilities(int $pokedexId, int $versionGroupId, array $pokemon): void
    {
        AbilityGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->delete();

        foreach ($pokemon['abilities'] ?? [] as $row) {
            if (! is_array($row) || empty($row['ability']['url'])) {
                continue;
            }

            $id = $this->extractTrailingId((string) $row['ability']['url']);
            $name = isset($row['ability']['name']) ? (string) $row['ability']['name'] : '';
            if ($id === null || $name === '') {
                continue;
            }

            AbilityGenerationData::query()->create([
                'pokedex_id' => $pokedexId,
                'version_group_id' => $versionGroupId,
                'pokeapi_ability_id' => $id,
                'ability_name' => $name,
                'slot' => (int) ($row['slot'] ?? 0),
                'is_hidden' => ! empty($row['is_hidden']),
            ]);
        }
    }

    /**
     * @param  list<array{move_id: int, move_name: string, method: string, level: int}>  $learnset
     */
    private function hydrateMoveCache(string $baseUrl, array $learnset): void
    {
        $ids = [];
        foreach ($learnset as $row) {
            if (isset($row['move_id']) && is_numeric($row['move_id'])) {
                $ids[(int) $row['move_id']] = true;
            }
        }

        $moveIds = array_keys($ids);
        if ($moveIds === []) {
            return;
        }

        $existing = PokeApiMoveCache::query()
            ->whereIn('id', $moveIds)
            ->pluck('id')
            ->all();

        $missing = array_values(array_diff($moveIds, $existing));
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 25) as $chunk) {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($baseUrl, $chunk): void {
                foreach ($chunk as $moveId) {
                    $pool->as((string) $moveId)
                        ->timeout(45)
                        ->retry(3, 500)
                        ->acceptJson()
                        ->get(rtrim($baseUrl, '/').'/move/'.$moveId.'/');
                }
            });

            foreach ($chunk as $moveId) {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = $responses[(string) $moveId] ?? null;
                if ($response === null || ! $response->successful()) {
                    continue;
                }

                $data = $response->json();
                if (! is_array($data) || empty($data['name'])) {
                    continue;
                }

                $shortEffect = null;
                foreach ($data['effect_entries'] ?? [] as $entry) {
                    if (! is_array($entry) || empty($entry['short_effect'])) {
                        continue;
                    }
                    $lang = $entry['language']['name'] ?? '';
                    if ($lang === 'en') {
                        $shortEffect = (string) $entry['short_effect'];

                        break;
                    }
                }

                $ailment = null;
                if (isset($data['meta']['ailment']['name']) && is_string($data['meta']['ailment']['name'])) {
                    $ailment = $data['meta']['ailment']['name'];
                }

                PokeApiMoveCache::query()->updateOrCreate(
                    ['id' => (int) $data['id']],
                    [
                        'name' => (string) $data['name'],
                        'type_slug' => isset($data['type']['name']) ? (string) $data['type']['name'] : 'unknown',
                        'damage_class' => isset($data['damage_class']['name']) ? (string) $data['damage_class']['name'] : 'status',
                        'power' => isset($data['power']) && $data['power'] !== null ? (int) $data['power'] : null,
                        'accuracy' => isset($data['accuracy']) && $data['accuracy'] !== null ? (int) $data['accuracy'] : null,
                        'ailment_name' => $ailment,
                        'short_effect_en' => $shortEffect,
                        'updated_at' => now(),
                    ]
                );
            }

            usleep(100_000);
        }
    }

    /**
     * PokeAPI stores regional forms, Megas, etc. as extra species `varieties` entries, each pointing at a
     * different /pokemon/{id} resource. Using only `is_default` makes every row that shares the same floored
     * national dex (e.g. 80.0 and 80.001) import the same form. Match `pokedex.name` to `variety.pokemon.name`
     * (kebab-case in API); fall back to the default variety.
     *
     * @param  array<string, mixed>  $species
     */
    private function resolveVarietyPokemonUrl(array $species, Pokedex $pokedex): ?string
    {
        $varieties = collect($species['varieties'] ?? [])
            ->filter(fn ($v) => is_array($v) && ! empty($v['pokemon']['url']));

        $rowSlug = Str::slug((string) $pokedex->getAttribute('name'));

        $matched = $varieties->first(function (array $v) use ($rowSlug): bool {
            $apiName = isset($v['pokemon']['name']) ? (string) $v['pokemon']['name'] : '';

            return $apiName !== '' && Str::slug($apiName) === $rowSlug;
        });

        if (is_array($matched) && ! empty($matched['pokemon']['url'])) {
            return (string) $matched['pokemon']['url'];
        }

        $default = $varieties->firstWhere('is_default', true);

        if (is_array($default) && ! empty($default['pokemon']['url'])) {
            return (string) $default['pokemon']['url'];
        }

        $first = $varieties->first();
        if (is_array($first) && ! empty($first['pokemon']['url'])) {
            return (string) $first['pokemon']['url'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @param  list<string>|null  $onlyMethods  When set, only these `move_learn_method.name` values (e.g. ['egg']).
     * @return list<array{move_id: int, move_name: string, method: string, level: int}>
     */
    private function buildLearnset(array $pokemon, string $versionGroupSlug, ?array $onlyMethods = null): array
    {
        $entries = [];

        foreach ($pokemon['moves'] ?? [] as $move) {
            if (! is_array($move) || empty($move['move']['url'])) {
                continue;
            }

            $moveId = $this->extractTrailingId((string) $move['move']['url']);
            $moveName = isset($move['move']['name']) ? (string) $move['move']['name'] : '';

            foreach ($move['version_group_details'] ?? [] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $vgName = $detail['version_group']['name'] ?? '';
                if ($vgName !== $versionGroupSlug) {
                    continue;
                }

                $method = isset($detail['move_learn_method']['name']) ? (string) $detail['move_learn_method']['name'] : '';
                if ($onlyMethods !== null && ! in_array($method, $onlyMethods, true)) {
                    continue;
                }

                if ($moveId === null) {
                    continue;
                }

                $entries[] = [
                    'move_id' => $moveId,
                    'move_name' => $moveName,
                    'method' => $method,
                    'level' => (int) ($detail['level_learned_at'] ?? 0),
                ];
            }
        }

        usort($entries, fn (array $a, array $b) => strcmp($a['move_name'], $b['move_name']));

        return $entries;
    }

    /**
     * PokéAPI often lists egg moves only on pre-evolutions (e.g. Scyther), not on the evolved form (Kleavor).
     * Merge egg-method rows from each `evolves_from_species` ancestor's default variety.
     *
     * @param  list<array{move_id: int, move_name: string, method: string, level: int}>  $learnset
     * @param  array<string, mixed>  $species  Current row from /pokemon-species/{id}/
     * @return list<array{move_id: int, move_name: string, method: string, level: int}>
     */
    private function mergeAncestorEggMovesIntoLearnset(array $learnset, array $species, string $versionGroupSlug): array
    {
        $seenSlugs = [];
        foreach ($learnset as $row) {
            $seenSlugs[$this->moveNameToSlugKey((string) ($row['move_name'] ?? ''))] = true;
        }

        foreach ($this->ancestorSpeciesResourceUrls($species) as $ancestorSpeciesUrl) {
            $ancestorSpecies = $this->getJson($ancestorSpeciesUrl);
            if ($ancestorSpecies === null) {
                continue;
            }

            $pokemonUrl = $this->resolveDefaultVarietyPokemonUrl($ancestorSpecies);
            if ($pokemonUrl === null) {
                continue;
            }

            $ancestorPokemon = $this->getJson($pokemonUrl);
            if ($ancestorPokemon === null) {
                continue;
            }

            $eggRows = $this->buildLearnset($ancestorPokemon, $versionGroupSlug, ['egg']);
            foreach ($eggRows as $row) {
                $slug = $this->moveNameToSlugKey((string) ($row['move_name'] ?? ''));
                if ($slug === '' || isset($seenSlugs[$slug])) {
                    continue;
                }
                $learnset[] = $row;
                $seenSlugs[$slug] = true;
            }

            usleep(50_000);
        }

        usort($learnset, fn (array $a, array $b) => strcmp($a['move_name'], $b['move_name']));

        return $learnset;
    }

    /**
     * The Move Reminder NPC allows any Pokémon to relearn moves it could learn via level-up in any
     * previous game in the series. PokéAPI only lists moves explicitly available for each version group,
     * so level-up moves from prior version groups are missing from the current learnset. This method
     * scans the already-fetched $pokemon response (no extra API calls) and adds any historical level-up
     * moves that are not already present, tagging them with method "reminder".
     *
     * @param  list<array{move_id: int, move_name: string, method: string, level: int}>  $learnset
     * @param  array<string, mixed>  $pokemon
     * @return list<array{move_id: int, move_name: string, method: string, level: int}>
     */
    private function mergeReminderMovesFromPriorGenerations(array $learnset, array $pokemon, string $currentVersionGroupSlug): array
    {
        $seenSlugs = [];
        foreach ($learnset as $row) {
            $seenSlugs[$this->moveNameToSlugKey((string) ($row['move_name'] ?? ''))] = true;
        }

        foreach ($pokemon['moves'] ?? [] as $move) {
            if (! is_array($move) || empty($move['move']['url'])) {
                continue;
            }

            $moveId = $this->extractTrailingId((string) $move['move']['url']);
            $moveName = isset($move['move']['name']) ? (string) $move['move']['name'] : '';
            $slug = $this->moveNameToSlugKey($moveName);

            if ($moveId === null || $slug === '' || isset($seenSlugs[$slug])) {
                continue;
            }

            foreach ($move['version_group_details'] ?? [] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $vgName = (string) ($detail['version_group']['name'] ?? '');
                $method = (string) ($detail['move_learn_method']['name'] ?? '');

                if ($vgName === $currentVersionGroupSlug || $method !== 'level-up') {
                    continue;
                }

                $learnset[] = [
                    'move_id' => $moveId,
                    'move_name' => $moveName,
                    'method' => 'reminder',
                    'level' => 0,
                ];
                $seenSlugs[$slug] = true;

                break;
            }
        }

        usort($learnset, fn (array $a, array $b) => strcmp($a['move_name'], $b['move_name']));

        return $learnset;
    }

    /**
     * Walk `evolves_from_species` until absent. First URL is immediate parent, then grandparent, etc.
     *
     * @param  array<string, mixed>  $species
     * @return list<string>
     */
    private function ancestorSpeciesResourceUrls(array $species): array
    {
        $urls = [];
        $from = $species['evolves_from_species'] ?? null;
        for ($i = 0; $i < 24 && is_array($from); $i++) {
            $url = $from['url'] ?? null;
            if (! is_string($url) || $url === '') {
                break;
            }
            $urls[] = $url;
            $parent = $this->getJson($url);
            if ($parent === null) {
                break;
            }
            $from = $parent['evolves_from_species'] ?? null;
            usleep(50_000);
        }

        return $urls;
    }

    /**
     * Default variety only — egg pools follow the base species line in games.
     *
     * @param  array<string, mixed>  $species
     */
    private function resolveDefaultVarietyPokemonUrl(array $species): ?string
    {
        $varieties = collect($species['varieties'] ?? [])
            ->filter(fn ($v) => is_array($v) && ! empty($v['pokemon']['url']));

        $default = $varieties->firstWhere('is_default', true);
        if (is_array($default) && ! empty($default['pokemon']['url'])) {
            return (string) $default['pokemon']['url'];
        }

        $first = $varieties->first();
        if (is_array($first) && ! empty($first['pokemon']['url'])) {
            return (string) $first['pokemon']['url'];
        }

        return null;
    }

    /**
     * Dedup key aligned with {@see \App\Modules\Pokepaste\Services\ShowdownFormatHelper::moveToSlug}.
     */
    private function moveNameToSlugKey(string $displayOrSlug): string
    {
        return strtolower(str_replace([' ', '_'], '-', trim($displayOrSlug)));
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}
     */
    private function mapStats(array $pokemon): array
    {
        $defaults = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 0];
        $map = [
            'hp' => 'hp',
            'attack' => 'atk',
            'defense' => 'def',
            'special-attack' => 'spa',
            'special-defense' => 'spd',
            'speed' => 'spe',
        ];

        foreach ($pokemon['stats'] ?? [] as $statRow) {
            if (! is_array($statRow)) {
                continue;
            }

            $apiName = $statRow['stat']['name'] ?? '';
            $key = $map[$apiName] ?? null;
            if ($key !== null) {
                $defaults[$key] = (int) ($statRow['base_stat'] ?? 0);
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @return array{0: string, 1: string|null}
     */
    private function mapTypes(array $pokemon): array
    {
        $bySlot = collect($pokemon['types'] ?? [])
            ->filter(fn ($t) => is_array($t))
            ->sortBy(fn ($t) => $t['slot'] ?? 0)
            ->values();

        $first = $bySlot->get(0);
        $second = $bySlot->get(1);

        $type1 = is_array($first) ? $this->formatTypeName((string) ($first['type']['name'] ?? '')) : 'Unknown';
        $type2 = is_array($second) ? $this->formatTypeName((string) ($second['type']['name'] ?? '')) : null;

        return [$type1, $type2];
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function mapAbilityIds(array $pokemon): array
    {
        $primary = null;
        $secondary = null;
        $hidden = null;

        foreach ($pokemon['abilities'] ?? [] as $row) {
            if (! is_array($row) || empty($row['ability']['url'])) {
                continue;
            }

            $id = $this->extractTrailingId((string) $row['ability']['url']);
            if ($id === null) {
                continue;
            }

            if (! empty($row['is_hidden'])) {
                $hidden = $id;

                continue;
            }

            $slot = (int) ($row['slot'] ?? 0);
            if ($slot === 1) {
                $primary = $id;
            } elseif ($slot === 2) {
                $secondary = $id;
            }
        }

        return [$primary, $secondary, $hidden];
    }

    private function formatTypeName(string $slug): string
    {
        return Str::title(str_replace('-', ' ', $slug));
    }

    /**
     * @return array<string, bool>
     */
    private function defaultMechanicsForVersionGroup(VersionGroup $versionGroup): array
    {
        if ($versionGroup->generation >= 9) {
            return [
                'tera_capable' => true,
                'mega' => false,
                'z_move' => false,
                'dynamax' => false,
                'gmax' => false,
            ];
        }

        return [
            'tera_capable' => false,
            'mega' => false,
            'z_move' => false,
            'dynamax' => false,
            'gmax' => false,
        ];
    }

    private function extractTrailingId(string $url): ?int
    {
        $path = rtrim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        if ($path === '') {
            return null;
        }

        $segment = basename($path);

        return ctype_digit($segment) ? (int) $segment : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJson(string $url): ?array
    {
        $response = Http::timeout(45)
            ->retry(3, 500)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }
}
