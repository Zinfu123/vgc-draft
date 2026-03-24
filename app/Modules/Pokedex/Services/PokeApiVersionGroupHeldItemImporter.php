<?php

namespace App\Modules\Pokedex\Services;

use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Models\VersionGroupHeldItem;
use Illuminate\Support\Facades\Http;

class PokeApiVersionGroupHeldItemImporter
{
    /**
     * Imports rows from PokeAPI `item-category` resources (union of held-items, berry-pocket categories,
     * and extra categories such as plates / type-enhancement / choice / species-specific by default),
     * filtered by `config('pokemon.version_group_held_item_flavor_slugs')`.
     *
     * @return int Number of rows written or updated
     */
    public function importForVersionGroup(VersionGroup $versionGroup): int
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        $categoryIds = $this->resolveHeldItemCategoryIds($baseUrl);

        $written = 0;

        foreach ($categoryIds as $categoryId) {
            $category = $this->getJson("{$baseUrl}/item-category/{$categoryId}/");
            if ($category === null || ! is_array($category['items'] ?? null)) {
                continue;
            }

            foreach ($category['items'] as $ref) {
                if (! is_array($ref)) {
                    continue;
                }

                $itemId = $this->extractTrailingId((string) ($ref['url'] ?? ''));
                if ($itemId === null) {
                    continue;
                }

                $item = $this->getJson("{$baseUrl}/item/{$itemId}/");
                if ($item === null || empty($item['name'])) {
                    continue;
                }

                if (! $this->qualifiesForVersionGroup($item, $versionGroup->slug)) {
                    continue;
                }

                VersionGroupHeldItem::query()->updateOrCreate(
                    [
                        'version_group_id' => $versionGroup->id,
                        'pokeapi_item_id' => $itemId,
                    ],
                    [
                        'name' => (string) $item['name'],
                        'display_name_en' => $this->englishDisplayName($item),
                        'cost' => isset($item['cost']) ? (int) $item['cost'] : null,
                        'sprite_url' => isset($item['sprites']['default']) && is_string($item['sprites']['default'])
                            ? $item['sprites']['default']
                            : null,
                    ]
                );

                $written++;
                usleep(50_000);
            }
        }

        return $written;
    }

    /**
     * @return list<int>
     */
    private function resolveHeldItemCategoryIds(string $baseUrl): array
    {
        /** @var array<int, int|string>|mixed $explicit */
        $explicit = config('pokemon.pokeapi_held_item_category_ids');
        if (is_array($explicit) && $explicit !== []) {
            return array_values(array_unique(array_map('intval', $explicit)));
        }

        $ids = [(int) config('pokemon.pokeapi_held_item_category_id', 12)];

        /** @var array<int, int|string>|mixed $pocketIds */
        $pocketIds = config('pokemon.pokeapi_held_item_pocket_ids');
        if (! is_array($pocketIds) || $pocketIds === []) {
            $pocketIds = [5];
        }

        foreach ($pocketIds as $pocketId) {
            $pocket = $this->getJson("{$baseUrl}/item-pocket/".(int) $pocketId.'/');
            if ($pocket === null || ! is_array($pocket['categories'] ?? null)) {
                continue;
            }

            foreach ($pocket['categories'] as $cat) {
                if (! is_array($cat)) {
                    continue;
                }

                $cid = $this->extractTrailingId((string) ($cat['url'] ?? ''));
                if ($cid !== null) {
                    $ids[] = $cid;
                }
            }
        }

        /** @var array<int, int|string>|mixed $extraCategoryIds */
        $extraCategoryIds = config('pokemon.pokeapi_held_item_extra_category_ids');
        if (is_array($extraCategoryIds)) {
            foreach ($extraCategoryIds as $extraId) {
                $ids[] = (int) $extraId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function qualifiesForVersionGroup(array $item, string $versionGroupSlug): bool
    {
        /** @var array<int, string>|mixed $allowed */
        $allowed = config("pokemon.version_group_held_item_flavor_slugs.{$versionGroupSlug}");
        if (! is_array($allowed) || $allowed === []) {
            $allowed = [$versionGroupSlug];
        }

        $entries = $item['flavor_text_entries'] ?? [];
        if ($entries === []) {
            return true;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $vgName = $entry['version_group']['name'] ?? null;
            if (is_string($vgName) && in_array($vgName, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function englishDisplayName(array $item): ?string
    {
        foreach ($item['names'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['language']['name'] ?? '') === 'en' && isset($row['name'])) {
                return (string) $row['name'];
            }
        }

        return null;
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
}
