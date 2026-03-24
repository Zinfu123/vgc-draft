<?php

namespace App\Modules\Pokedex\Services;

use Illuminate\Support\Facades\Http;

class PokeApiSvDexSpeciesService
{
    /**
     * Paldea, Kitakami, and Blueberry Pokédexes linked to Scarlet/Violet-era version groups in PokeAPI.
     *
     * @var list<int>
     */
    private const SV_REGIONAL_POKEDEX_IDS = [31, 32, 33];

    /**
     * Unique pokemon-species IDs listed across Paldea + Kitakami + Blueberry regional dexes.
     *
     * @return list<int>
     */
    public function regionalDexUnionSpeciesIds(): array
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $ids = [];

        foreach (self::SV_REGIONAL_POKEDEX_IDS as $pokedexId) {
            $data = $this->getJson("{$baseUrl}/pokedex/{$pokedexId}/");
            if ($data === null) {
                continue;
            }

            foreach ($data['pokemon_entries'] ?? [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $url = $entry['pokemon_species']['url'] ?? null;
                if (! is_string($url)) {
                    continue;
                }

                $speciesId = $this->extractTrailingId($url);
                if ($speciesId !== null) {
                    $ids[$speciesId] = true;
                }
            }
        }

        $list = array_keys($ids);
        sort($list);

        return $list;
    }

    /**
     * Species first introduced in Generation IX (subset of what can appear in SV; not the full SV pool).
     *
     * @return list<int>
     */
    public function generationNineSpeciesIds(): array
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $data = $this->getJson("{$baseUrl}/generation/9/");
        if ($data === null) {
            return [];
        }

        $ids = [];
        foreach ($data['pokemon_species'] ?? [] as $row) {
            if (! is_array($row) || empty($row['url'])) {
                continue;
            }

            $id = $this->extractTrailingId((string) $row['url']);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJson(string $url): ?array
    {
        $response = Http::timeout(60)
            ->retry(3, 500)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
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
}
