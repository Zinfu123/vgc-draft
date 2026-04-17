<?php

namespace App\Modules\Pokedex\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PikalyticsChampionsUsageService
{
    private const LIST_URL = 'https://www.pikalytics.com/pokedex/championstournaments';

    private const HTTP_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (compatible; VGCDraft/1.0)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
    ];

    /**
     * Fetch and parse Champions tournament usage data from Pikalytics.
     *
     * @return array{map: array<string, float>, error: string|null}
     *                                                              'map' is keyed by normalized pokemon slug (e.g. 'incineroar' => 54.2).
     *                                                              'error' is set when the fetch or parse failed.
     */
    public function fetchUsageMapWithResult(): array
    {
        $html = $this->fetchHtml();
        if ($html === null) {
            return ['map' => [], 'error' => 'HTTP request to Pikalytics failed (blocked or network error).'];
        }

        $map = $this->parseUsageMap($html);
        if ($map === []) {
            return ['map' => [], 'error' => 'Pikalytics page returned no usage data (page may be JavaScript-rendered or HTML structure changed).'];
        }

        return ['map' => $map, 'error' => null];
    }

    /**
     * Parse a usage CSV file (name,usage_pct per row) into a usage map.
     *
     * @return array<string, float>
     */
    public function parseUsageCsv(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            return [];
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return [];
        }

        $map = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Detect 3-column format: rank,pokemon,usage — or 2-column: name,usage.
            if (count($row) >= 3 && is_numeric(trim($row[0]))) {
                $name = trim($row[1]);
                $rawUsage = $row[2];
            } elseif (count($row) >= 2) {
                $name = trim($row[0]);
                $rawUsage = $row[1];
            } else {
                continue;
            }

            $nameLower = strtolower($name);
            if ($nameLower === '' || $nameLower === 'name' || $nameLower === 'pokemon' || $nameLower === 'rank') {
                continue; // Skip header rows.
            }

            $usage = (float) preg_replace('/[^0-9.]/', '', $rawUsage);
            $slug = $this->normalizeToSlug($name);

            if ($slug === '') {
                continue;
            }

            // Keep the highest usage when the same slug appears multiple times.
            if (! isset($map[$slug]) || $usage > $map[$slug]) {
                $map[$slug] = $usage;
            }

            // Add form-base aliases so lookups against the base pokedex name succeed.
            foreach ($this->baseAliases($slug, $usage) as $alias) {
                if (! isset($map[$alias]) || $usage > $map[$alias]) {
                    $map[$alias] = $usage;
                }
            }
        }

        fclose($handle);

        return $map;
    }

    public function fetchHtml(): ?string
    {
        try {
            $response = Http::withHeaders(self::HTTP_HEADERS)
                ->timeout(45)
                ->retry(2, 1000, fn (\Exception $e): bool => ! ($e instanceof \Illuminate\Http\Client\RequestException))
                ->get(self::LIST_URL);
        } catch (\Exception) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * @return array<string, float>
     */
    public function parseUsageMap(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $usageMap = [];

        // Pikalytics renders each Pokemon as a link with class "pokedex-pokemon-seen"
        // containing a name element and a usage percentage element.
        $pokemonLinks = $xpath->query('//*[contains(@class,"pokedex-pokemon-seen")]');
        if ($pokemonLinks !== false && $pokemonLinks->length > 0) {
            foreach ($pokemonLinks as $node) {
                $nameNodes = $xpath->query('.//*[contains(@class,"pokedex-pokemon-name")]', $node);
                $pctNodes = $xpath->query('.//*[contains(@class,"pokedex-pokemon-usage")]', $node);

                if ($nameNodes === false || $nameNodes->length === 0) {
                    continue;
                }

                if ($pctNodes === false || $pctNodes->length === 0) {
                    continue;
                }

                $name = trim($nameNodes->item(0)?->textContent ?? '');
                $pctText = trim($pctNodes->item(0)?->textContent ?? '');

                if ($name === '' || $pctText === '') {
                    continue;
                }

                $usage = (float) preg_replace('/[^0-9.]/', '', $pctText);
                $slug = $this->normalizeToSlug($name);

                if ($slug !== '') {
                    $usageMap[$slug] = $usage;
                }
            }
        }

        // Fallback: try table rows if no class-based elements found.
        if ($usageMap === []) {
            $usageMap = $this->parseFromTable($xpath);
        }

        return $usageMap;
    }

    /**
     * @return array<string, float>
     */
    private function parseFromTable(DOMXPath $xpath): array
    {
        $usageMap = [];

        $rows = $xpath->query('//table//tr');
        if ($rows === false) {
            return [];
        }

        foreach ($rows as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $name = trim($cells->item(0)?->textContent ?? '');
            $pctText = trim($cells->item(1)?->textContent ?? '');

            if ($name === '' || $pctText === '') {
                continue;
            }

            $usage = (float) preg_replace('/[^0-9.]/', '', $pctText);
            if ($usage === 0.0) {
                continue;
            }

            $slug = $this->normalizeToSlug($name);
            if ($slug !== '') {
                $usageMap[$slug] = $usage;
            }
        }

        return $usageMap;
    }

    /**
     * Convert a display name like "Flutter Mane" or "Incineroar" to a lookup slug.
     * Pikalytics display names generally match direct lowercased slugification.
     */
    private function normalizeToSlug(string $displayName): string
    {
        // Remove parenthetical suffixes like "(Mega)" or "(Alolan Form)".
        $cleaned = preg_replace('/\s*\([^)]+\)/', '', $displayName) ?? $displayName;

        return Str::slug(trim($cleaned));
    }

    /**
     * Return additional base-form slugs to alias this slug under in the usage map.
     * Handles cases where Pikalytics names a specific form (e.g. "rotom-wash") but
     * the pokedex only has the base entry (e.g. "rotom").
     *
     * @return list<string>
     */
    private function baseAliases(string $slug, float $usage): array
    {
        // Rotom appliance forms → alias to "rotom".
        if (preg_match('/^rotom-(wash|heat|frost|mow|fan|spin)$/', $slug)) {
            return ['rotom'];
        }

        // Tauros-Paldea regional variants → alias to "tauros-paldea".
        if (preg_match('/^tauros-paldea-(aqua|blaze|combat)$/', $slug)) {
            return ['tauros-paldea'];
        }

        // Aegislash blade/shield → alias to "aegislash".
        if (preg_match('/^aegislash-(blade|shield)$/', $slug)) {
            return ['aegislash'];
        }

        // Tatsugiri forms → alias to "tatsugiri".
        if (preg_match('/^tatsugiri-(droopy|stretchy|curly)$/', $slug)) {
            return ['tatsugiri'];
        }

        // Meowstic female → alias to "meowstic" (our pokedex entry name).
        if ($slug === 'meowstic-f') {
            return ['meowstic'];
        }

        return [];
    }

    /**
     * Resolve a usage percentage to a draft point cost (1–10).
     */
    public static function usageToCost(float $usagePct): int
    {
        return match (true) {
            $usagePct >= 20.0 => 10,
            $usagePct >= 12.0 => 9,
            $usagePct >= 8.0 => 8,
            $usagePct >= 5.0 => 7,
            $usagePct >= 3.0 => 6,
            $usagePct >= 2.0 => 5,
            $usagePct >= 1.0 => 4,
            $usagePct >= 0.5 => 3,
            $usagePct >= 0.1 => 2,
            default => 1,
        };
    }
}
