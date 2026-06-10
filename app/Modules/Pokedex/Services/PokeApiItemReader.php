<?php

namespace App\Modules\Pokedex\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PokeApiItemReader
{
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array<string, mixed>
     */
    public function propsForInertia(int $pokeapiItemId): array
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $key = 'pokeapi:item:v1:'.$pokeapiItemId;

        /** @var array<string, mixed> $data */
        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($baseUrl, $pokeapiItemId): array {
            $response = Http::timeout(45)
                ->retry(3, 500)
                ->acceptJson()
                ->get("{$baseUrl}/item/{$pokeapiItemId}/");

            if (! $response->successful()) {
                return ['_error' => 'not_found'];
            }

            $json = $response->json();

            return is_array($json) ? $json : ['_error' => 'invalid'];
        });

        if (isset($data['_error'])) {
            return [
                'error' => (string) $data['_error'],
                'id' => $pokeapiItemId,
                'name_display' => 'Unknown',
            ];
        }

        $nameSlug = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $nameDisplayEn = $nameSlug !== '' ? Str::title(str_replace('-', ' ', $nameSlug)) : 'Unknown';
        foreach ($data['names'] ?? [] as $n) {
            if (! is_array($n) || ($n['language']['name'] ?? '') !== 'en' || empty($n['name'])) {
                continue;
            }
            $nameDisplayEn = (string) $n['name'];

            break;
        }

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

        $category = null;
        if (isset($data['category']['name']) && is_string($data['category']['name'])) {
            $category = Str::title(str_replace('-', ' ', $data['category']['name']));
        }

        $spriteUrl = null;
        if (isset($data['sprites']['default']) && is_string($data['sprites']['default'])) {
            $spriteUrl = $data['sprites']['default'];
        }

        $flavorLines = [];
        foreach ($data['flavor_text_entries'] ?? [] as $ft) {
            if (! is_array($ft) || ($ft['language']['name'] ?? '') !== 'en') {
                continue;
            }
            $vg = $ft['version_group']['name'] ?? '';
            if (is_string($ft['text'] ?? null) && $ft['text'] !== '') {
                $flavorLines[] = [
                    'text' => (string) $ft['text'],
                    'version_group' => is_string($vg) ? $vg : '',
                ];
            }
        }

        return [
            'error' => null,
            'id' => (int) ($data['id'] ?? $pokeapiItemId),
            'name_display' => $nameDisplayEn,
            'name_slug' => $nameSlug,
            'cost' => isset($data['cost']) ? (int) $data['cost'] : 0,
            'category' => $category,
            'effect' => $effect,
            'short_effect' => $shortEffect,
            'sprite_url' => $spriteUrl,
            'flavor_lines' => array_slice($flavorLines, 0, 12),
        ];
    }
}
