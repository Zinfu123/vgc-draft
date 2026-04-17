<?php

namespace App\Modules\Pokedex\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class SerebiiChampionsAvailableRosterService
{
    private const ROSTER_URL = 'https://www.serebii.net/pokemonchampions/pokemon.shtml';

    private const HTTP_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (compatible; VGCDraft/1.0)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
    ];

    public function fetchRosterHtml(): ?string
    {
        try {
            $response = Http::withHeaders(self::HTTP_HEADERS)
                ->timeout(45)
                ->retry(2, 1000, fn (\Exception $e): bool => ! ($e instanceof \Illuminate\Http\Client\RequestException))
                ->get(self::ROSTER_URL);
        } catch (\Exception $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * @return list<array{national_dex: int, serebii_slug: string, display_name: string, type_slugs: list<string>, sprite_variant: string|null}>
     */
    public function parseRosterRows(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);
        libxml_clear_errors();
        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $table = $xpath->query("//h2[contains(., 'List of Available')]/following::table[contains(@class, 'tab')][1]");
        if ($table === false || $table->length === 0) {
            return [];
        }

        $rows = [];
        $trNodes = $xpath->query('.//tr', $table->item(0));
        if ($trNodes === false) {
            return [];
        }

        foreach ($trNodes as $tr) {
            $tds = $xpath->query('./td', $tr);
            if ($tds === false || $tds->length < 4) {
                continue;
            }

            $dexText = trim(preg_replace('/\s+/', ' ', $tds->item(0)?->textContent ?? '') ?? '');
            $nationalDex = (int) preg_replace('/\D+/', '', $dexText);
            if ($nationalDex === 0) {
                continue;
            }

            $imgNodes = $xpath->query('.//img', $tds->item(1));
            $imgSrc = '';
            if ($imgNodes !== false && $imgNodes->length > 0) {
                $firstImg = $imgNodes->item(0);
                if ($firstImg instanceof \DOMElement) {
                    $imgSrc = $firstImg->getAttribute('src');
                }
            }

            $spriteParsed = $this->parseSpriteFile($imgSrc);
            if ($spriteParsed === null) {
                continue;
            }

            $spriteVariant = $spriteParsed['variant'];

            $nameLink = $xpath->query('.//a[contains(@href, "/pokedex-champions/")]', $tds->item(2));
            if ($nameLink === false || $nameLink->length === 0) {
                continue;
            }

            $href = $nameLink->item(0)?->getAttribute('href') ?? '';
            $serebiiSlug = $this->serebiiSlugFromPokedexChampionsHref($href);
            if ($serebiiSlug === null) {
                continue;
            }

            $displayName = trim(preg_replace('/\s+/', ' ', $nameLink->item(0)?->textContent ?? '') ?? '');
            if ($displayName === '') {
                continue;
            }

            $typeSlugs = [];
            $typeLinks = $xpath->query('.//a[contains(@href, "/pokemon/")]', $tds->item(3));
            if ($typeLinks !== false) {
                foreach ($typeLinks as $a) {
                    if (! $a instanceof \DOMElement) {
                        continue;
                    }

                    $th = $a->getAttribute('href');
                    if (preg_match('#/pokemon/([a-z0-9_-]+)\.shtml#i', $th, $m)) {
                        $typeSlugs[] = strtolower($m[1]);
                    }
                }
            }

            $rows[] = [
                'national_dex' => $nationalDex,
                'serebii_slug' => $serebiiSlug,
                'display_name' => $displayName,
                'type_slugs' => $typeSlugs,
                'sprite_variant' => $spriteVariant,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{national_dex: int, serebii_slug: string, display_name: string, type_slugs: list<string>, sprite_variant: string|null}  $row
     */
    public function resolveRowToPokedexName(array $row, SerebiiChampionsImporter $importer): string
    {
        $base = $importer->pokedexBaseNameFromSerebiiSpeciesSlug($row['serebii_slug']);
        $display = $row['display_name'];
        $types = $row['type_slugs'];
        $variant = $row['sprite_variant'];
        $dex = $row['national_dex'];

        $isMega = str_starts_with($display, 'Mega ');

        if ($isMega) {
            if ($row['serebii_slug'] === 'charizard') {
                if ($variant === 'mx' || in_array('dragon', $types, true)) {
                    return 'charizard-mega-x';
                }

                return 'charizard-mega-y';
            }

            if ($row['serebii_slug'] === 'floette') {
                return 'floette-eternal-mega';
            }

            return $base.'-mega';
        }

        if ($variant === 'a') {
            return $base.'-alola';
        }

        if ($variant === 'g') {
            return $base.'-galar';
        }

        if ($variant === 'h') {
            return $base.'-hisui';
        }

        if ($variant === 'p') {
            return $base.'-paldea';
        }

        if ($variant === 'e' && $dex === 670) {
            return 'floette-eternal';
        }

        return $base;
    }

    /**
     * @return list<string> Unique pokedex `name` values in stable order.
     */
    public function resolveUniquePokedexNamesFromHtml(string $html, SerebiiChampionsImporter $importer): array
    {
        $seen = [];
        $ordered = [];

        foreach ($this->parseRosterRows($html) as $row) {
            $name = $this->resolveRowToPokedexName($row, $importer);
            if ($name === '') {
                continue;
            }

            if (! isset($seen[$name])) {
                $seen[$name] = true;
                $ordered[] = $name;
            }
        }

        return $ordered;
    }

    /**
     * @return array{variant: string|null}|null
     */
    private function parseSpriteFile(string $imgSrc): ?array
    {
        $path = parse_url($imgSrc, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $base = basename($path);
        if (! preg_match('/^(\d+)(?:-([a-z0-9]+))?\.png$/i', $base, $m)) {
            return null;
        }

        return [
            'variant' => isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null,
        ];
    }

    private function serebiiSlugFromPokedexChampionsHref(string $href): ?string
    {
        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/pokedex-champions/([^/]+)/?#i', $path, $m)) {
            $slug = strtolower($m[1]);

            return $slug !== '' ? $slug : null;
        }

        return null;
    }
}
