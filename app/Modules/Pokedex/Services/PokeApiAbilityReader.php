<?php

namespace App\Modules\Pokedex\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PokeApiAbilityReader
{
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array<string, mixed>
     */
    public function propsForInertia(int $pokeapiAbilityId): array
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $key = 'pokeapi:ability:v1:'.$pokeapiAbilityId;

        /** @var array<string, mixed> $data */
        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($baseUrl, $pokeapiAbilityId): array {
            $response = Http::timeout(45)
                ->retry(3, 500)
                ->acceptJson()
                ->get("{$baseUrl}/ability/{$pokeapiAbilityId}/");

            if (! $response->successful()) {
                return ['_error' => 'not_found'];
            }

            $json = $response->json();

            return is_array($json) ? $json : ['_error' => 'invalid'];
        });

        if (isset($data['_error'])) {
            return [
                'error' => (string) $data['_error'],
                'id' => $pokeapiAbilityId,
                'name_display' => 'Unknown',
            ];
        }

        $nameSlug = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $nameDisplay = $nameSlug !== '' ? Str::title(str_replace('-', ' ', $nameSlug)) : 'Unknown';

        $effect = null;
        $shortEffect = null;
        foreach ($data['effect_entries'] ?? [] as $entry) {
            if (! is_array($entry) || ($entry['language']['name'] ?? '') !== 'en') {
                continue;
            }
            if (! empty($entry['effect']) && is_string($entry['effect'])) {
                $effect = $entry['effect'];
            }
            if (! empty($entry['short_effect']) && is_string($entry['short_effect'])) {
                $shortEffect = $entry['short_effect'];
            }

            break;
        }

        $generation = null;
        if (isset($data['generation']['name']) && is_string($data['generation']['name'])) {
            $generation = Str::title(str_replace('-', ' ', $data['generation']['name']));
        }

        $flavorLines = [];
        foreach ($data['flavor_text_entries'] ?? [] as $ft) {
            if (! is_array($ft) || ($ft['language']['name'] ?? '') !== 'en') {
                continue;
            }
            $vg = $ft['version_group']['name'] ?? '';
            if (is_string($ft['flavor_text'] ?? null) && $ft['flavor_text'] !== '') {
                $flavorLines[] = [
                    'text' => (string) $ft['flavor_text'],
                    'version_group' => is_string($vg) ? $vg : '',
                ];
            }
        }

        return [
            'error' => null,
            'id' => (int) ($data['id'] ?? $pokeapiAbilityId),
            'name_display' => $nameDisplay,
            'name_slug' => $nameSlug,
            'effect' => $effect,
            'short_effect' => $shortEffect,
            'generation' => $generation,
            'flavor_lines' => array_slice($flavorLines, 0, 12),
        ];
    }
}
