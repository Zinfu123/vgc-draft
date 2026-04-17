<?php

namespace App\Modules\Pokedex\Services;

use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SerebiiChampionsImporter
{
    private const SEREBII_BASE = 'https://www.serebii.net/pokedex-champions/';

    /**
     * Serebii anchors the "Standard Moves" table with <a name="…"> inside the section heading.
     *
     * @var list<string>
     */
    private const CHAMPIONS_MOVE_SECTION_ANCHORS = [
        'standardlevel',
        'hisuianlevel',
        'alolalevel',
        'galarianlevel',
        'paldeanlevel',
    ];

    private const POKEAPI_SLEEP_US = 150_000;

    private const HTTP_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (compatible; VGCDraft/1.0)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
    ];

    public function import(Pokedex $pokedex, VersionGroup $versionGroup): ChampionsSerebiiImportResult
    {
        $isMegaForm = str_starts_with((string) $pokedex->getAttribute('name'), 'mega ') ||
            $this->isMegaName((string) $pokedex->getAttribute('name'));

        $name = (string) $pokedex->getAttribute('name');
        $regionalForm = $this->regionalFormFromPokedexName($name);

        $serebiiSlug = $this->toSerebiiSlug($name);
        $url = self::SEREBII_BASE.$serebiiSlug.'/';

        $html = $this->fetchHtml($url);
        if ($html === null) {
            Log::warning('SerebiiChampionsImporter: could not fetch page', [
                'pokedex_id' => $pokedex->id,
                'name' => $pokedex->getAttribute('name'),
                'url' => $url,
            ]);

            return ChampionsSerebiiImportResult::failed('Could not fetch Serebii page (missing, blocked, or HTTP error).', $url);
        }

        $movesSectionAnchor = $this->championsMovesAnchorForRegionalForm($regionalForm);
        if ($isMegaForm && strcasecmp($name, 'floette-eternal-mega') === 0) {
            $moves = $this->parseMovesInDextableH3ContainingAll($html, 'Standard Moves', 'Eternal Floette');
            if ($moves === []) {
                $moves = $this->parseMoves($html, $movesSectionAnchor);
            }
        } else {
            $moves = $this->parseMoves($html, $movesSectionAnchor);
        }
        if ($moves === []) {
            Log::warning('SerebiiChampionsImporter: no moves found', [
                'pokedex_id' => $pokedex->id,
                'name' => $pokedex->getAttribute('name'),
                'url' => $url,
            ]);

            return ChampionsSerebiiImportResult::failed('No Champions move list found on Serebii page (parser or HTML layout change).', $url);
        }

        $learnset = $this->resolveLearnset($moves);
        if ($learnset === []) {
            Log::warning('SerebiiChampionsImporter: could not resolve any move IDs', [
                'pokedex_id' => $pokedex->id,
                'name' => $pokedex->getAttribute('name'),
            ]);

            return ChampionsSerebiiImportResult::failed('Could not resolve any move IDs (PokeAPI / move cache).', $url);
        }

        $stats = $isMegaForm
            ? $this->parseMegaStats($html, $name)
            : $this->parseBaseStats($html, $regionalForm);

        if ($stats === null) {
            Log::warning('SerebiiChampionsImporter: could not parse stats', [
                'pokedex_id' => $pokedex->id,
                'name' => $pokedex->getAttribute('name'),
                'is_mega' => $isMegaForm,
            ]);

            $hint = $isMegaForm ? 'Mega form stats block not found.' : 'Base stats block not found.';

            return ChampionsSerebiiImportResult::failed('Could not parse stats from Serebii. '.$hint, $url);
        }

        $types = $this->parseTypes($pokedex);
        if ($isMegaForm) {
            $abilities = $this->parseMegaAbilities($html, $name);
        } elseif ($regionalForm !== null) {
            $abilities = $this->parseRegionalFormAbilities($html, $regionalForm);
        } else {
            $abilities = $this->parseBaseAbilities($html);
        }

        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');
        [$primaryId, $secondaryId, $hiddenId] = $this->resolveAbilityIds($baseUrl, $abilities);

        PokemonGenerationData::query()->updateOrCreate(
            [
                'pokedex_id' => $pokedex->id,
                'version_group_id' => $versionGroup->id,
            ],
            [
                'pokeapi_pokemon_id' => null,
                'hp' => $stats['hp'],
                'atk' => $stats['atk'],
                'def' => $stats['def'],
                'spa' => $stats['spa'],
                'spd' => $stats['spd'],
                'spe' => $stats['spe'],
                'type1' => $types[0],
                'type2' => $types[1],
                'ability_primary_pokeapi_id' => $primaryId,
                'ability_secondary_pokeapi_id' => $secondaryId,
                'ability_hidden_pokeapi_id' => $hiddenId,
                'learnset' => $learnset,
                'mechanics' => $this->defaultMechanics($versionGroup),
            ]
        );

        $this->syncAbilityGenerationData($pokedex->id, $versionGroup->id, $abilities, $primaryId, $secondaryId, $hiddenId);

        return ChampionsSerebiiImportResult::ok();
    }

    /**
     * Derive the Serebii slug from a pokedex name.
     * "chandelure-mega" → "chandelure"
     * "greninja-mega"   → "greninja"
     * "mr-mime-galar"   → "mr.mime" (Serebii uses dots for Mr. names)
     * "kommo-o"         → "kommo-o"
     */
    public function toSerebiiSlug(string $name): string
    {
        // Strip "-mega" suffix (e.g. "chandelure-mega" → "chandelure")
        $base = preg_replace('/-mega(-[xyz])?$/i', '', $name) ?? $name;

        // Strip Charizard X/Y mega suffix (e.g. "charizard-mega-x" → "charizard")
        $base = preg_replace('/-mega-[a-z]$/i', '', $base) ?? $base;

        // Strip regional form suffix if it ends in "-mega" (already handled above)
        // Handle "floette-eternal-mega" → "floette"
        $base = preg_replace('/-eternal(-mega)?$/i', '', $base) ?? $base;

        // Hisuian / Alolan / etc. share one Serebii URL with the species base (e.g. typhlosion-hisui → typhlosion/)
        $base = preg_replace('/-(hisui|alola|galar|paldea)$/i', '', $base) ?? $base;

        // Convert dashes back to dots for special names that Serebii uses periods for
        $base = $this->applySerebiiSpecialSlugs($base);

        return strtolower($base);
    }

    /**
     * Parse Standard Moves from the Serebii Champions page HTML.
     * Returns move names as strings (Serebii display names, e.g. "Ice Beam").
     *
     * @return list<string>
     */
    public function parseMoves(string $html, string $sectionAnchor = 'standardlevel'): array
    {
        if (! in_array($sectionAnchor, self::CHAMPIONS_MOVE_SECTION_ANCHORS, true)) {
            $sectionAnchor = 'standardlevel';
        }

        $dom = $this->parseDom($html);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);

        $nodes = false;
        $anchorNodes = $xpath->query("//a[@name='{$sectionAnchor}']");
        if ($anchorNodes !== false && $anchorNodes->length > 0) {
            $anchorEl = $anchorNodes->item(0);
            if ($anchorEl !== null) {
                $scopeTable = $xpath->query('ancestor::table[contains(@class, "dextable")][1]', $anchorEl)->item(0);
                if ($scopeTable instanceof \DOMElement) {
                    $nodes = $xpath->query('.//a[contains(@href, "/attackdex-champions/")]', $scopeTable);
                }
            }
        }

        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//a[contains(@href, "/attackdex-champions/")]');
        }

        if ($nodes === false) {
            return [];
        }

        return $this->collectMoveNamesFromAttackdexAnchors($nodes);
    }

    /**
     * Champions Floette: Mega uses the Eternal Flower learnset; the only move table is
     * "Standard Moves - Eternal Floette" (no standardlevel anchor), so scope by heading text.
     *
     * @return list<string>
     */
    private function parseMovesInDextableH3ContainingAll(string $html, string ...$substrings): array
    {
        if ($substrings === []) {
            return [];
        }

        $dom = $this->parseDom($html);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $tables = $xpath->query("//table[contains(@class, 'dextable')]");
        if ($tables === false) {
            return [];
        }

        foreach ($tables as $table) {
            if (! $table instanceof \DOMElement) {
                continue;
            }

            $h3Nodes = $xpath->query('.//h3', $table);
            if ($h3Nodes === false) {
                continue;
            }

            foreach ($h3Nodes as $h3) {
                $haystack = strtolower($h3->textContent ?? '');
                $matchesAll = true;
                foreach ($substrings as $needle) {
                    if (! str_contains($haystack, strtolower($needle))) {
                        $matchesAll = false;

                        break;
                    }
                }

                if ($matchesAll) {
                    $anchors = $xpath->query('.//a[contains(@href, "/attackdex-champions/")]', $table);

                    return $this->collectMoveNamesFromAttackdexAnchors($anchors);
                }
            }
        }

        return [];
    }

    /**
     * @param  \DOMNodeList<\DOMNode>|\false  $nodes
     * @return list<string>
     */
    private function collectMoveNamesFromAttackdexAnchors(\DOMNodeList|false $nodes): array
    {
        if ($nodes === false) {
            return [];
        }

        $moves = [];
        $seen = [];

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');
            if (! $this->isSpecificChampionsMoveAttackdexLink($href)) {
                continue;
            }

            $text = trim($node->textContent ?? '');
            if ($text === '') {
                continue;
            }

            $key = strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }

            $moves[] = $text;
            $seen[$key] = true;
        }

        return $moves;
    }

    /**
     * True when the href points at a concrete move page under attackdex-champions,
     * not the index or other navigation (which would produce bogus PokeAPI slugs).
     */
    private function isSpecificChampionsMoveAttackdexLink(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
        $needle = '/attackdex-champions/';
        $pos = strpos($path, $needle);
        if ($pos === false) {
            return false;
        }

        $tail = substr($path, $pos + strlen($needle));
        $tail = trim($tail, '/');
        if ($tail === '' || str_contains($tail, '/')) {
            return false;
        }

        if (! str_ends_with(strtolower($tail), '.shtml')) {
            return false;
        }

        $base = basename($tail, '.shtml');
        if ($base === '' || strcasecmp($base, 'index') === 0) {
            return false;
        }

        return true;
    }

    /**
     * Parse base Pokémon stats from the first stat table on the page.
     *
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}|null
     */
    public function parseBaseStats(string $html, ?string $regionalForm = null): ?array
    {
        return $this->parseStatsBlock($html, false, '', $regionalForm);
    }

    /**
     * Parse Mega form stats by finding the "Mega {Name}" heading then the stats table below it.
     *
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}|null
     */
    public function parseMegaStats(string $html, string $megaName): ?array
    {
        return $this->parseStatsBlock($html, true, $megaName, null);
    }

    /**
     * @return list<string> Ability names (Serebii display names, e.g. "Infiltrator").
     */
    public function parseBaseAbilities(string $html): array
    {
        return $this->parseAbilitiesFromSection($html, false, '');
    }

    /**
     * @return list<string> Ability names for the Mega form.
     */
    public function parseMegaAbilities(string $html, string $megaName): array
    {
        return $this->parseAbilitiesFromSection($html, true, $megaName);
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    /**
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}|null
     */
    private function parseStatsBlock(string $html, bool $isMega, string $megaName, ?string $regionalForm): ?array
    {
        $dom = $this->parseDom($html);
        if ($dom === null) {
            return null;
        }

        $xpath = new DOMXPath($dom);

        if ($isMega) {
            $baseName = $this->megaDisplayName($megaName);
            $headings = $xpath->query('//*[contains(translate(., "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "'.strtoupper($baseName).'")]');
            if ($headings === false || $headings->length === 0) {
                return null;
            }
        }

        $targetRow = null;

        if (! $isMega && $regionalForm !== null) {
            $statsNeedle = $this->statsHeadingNeedleForRegionalForm($regionalForm);
            if ($statsNeedle !== null) {
                $tables = $xpath->query("//table[contains(@class, 'dextable')][.//h2[contains(., '{$statsNeedle}')]]");
                if ($tables !== false && $tables->length > 0) {
                    $table = $tables->item(0);
                    if ($table !== null) {
                        $rows = $xpath->query(".//td[contains(., 'Base Stats - Total:')]/..", $table);
                        if ($rows !== false && $rows->length > 0) {
                            $targetRow = $rows->item(0);
                        }
                    }
                }
            }
        } elseif (! $isMega) {
            $targetRow = $this->findFirstStandardBaseStatsRow($xpath);
        }

        if ($targetRow === null && ! $isMega) {
            $statRows = $xpath->query('//td[contains(., "Base Stats - Total:")]/..');
            if ($statRows !== false && $statRows->length > 0) {
                $targetRow = $statRows->item(0);
            }
        }

        if ($isMega) {
            $statRows = $xpath->query('//td[contains(., "Base Stats - Total:")]/..');
            if ($statRows === false || $statRows->length === 0) {
                return null;
            }
            $targetRow = $statRows->item($statRows->length - 1);
        }

        if ($targetRow === null) {
            return null;
        }

        return $this->extractStatsFromRow($targetRow);
    }

    private function findFirstStandardBaseStatsRow(DOMXPath $xpath): ?\DOMNode
    {
        $tables = $xpath->query("//table[contains(@class, 'dextable')][.//h2[normalize-space(.)='Stats']]");
        if ($tables !== false && $tables->length > 0) {
            $rows = $xpath->query(".//td[contains(., 'Base Stats - Total:')]/..", $tables->item(0));
            if ($rows !== false && $rows->length > 0) {
                return $rows->item(0);
            }
        }

        $h2s = $xpath->query("//h2[normalize-space(.)='Stats']");
        if ($h2s !== false && $h2s->length > 0) {
            $h2 = $h2s->item(0);
            if ($h2 !== null) {
                $rows = $xpath->query('following-sibling::table[1]//td[contains(., "Base Stats - Total:")]/..', $h2);
                if ($rows !== false && $rows->length > 0) {
                    return $rows->item(0);
                }
            }
        }

        return null;
    }

    /**
     * @return 'hisui'|'alola'|'galar'|'paldea'|null
     */
    public function regionalFormFromPokedexName(string $name): ?string
    {
        $n = strtolower($name);
        $n = preg_replace('/-mega(-[xyz])?$/', '', $n) ?? $n;
        $n = preg_replace('/-mega-[a-z]$/', '', $n) ?? $n;
        $n = preg_replace('/-eternal(-mega)?$/', '', $n) ?? $n;

        return match (true) {
            str_ends_with($n, '-hisui') => 'hisui',
            str_ends_with($n, '-alola') => 'alola',
            str_ends_with($n, '-galar') => 'galar',
            str_ends_with($n, '-paldea') => 'paldea',
            default => null,
        };
    }

    private function championsMovesAnchorForRegionalForm(?string $regionalForm): string
    {
        return match ($regionalForm) {
            'hisui' => 'hisuianlevel',
            'alola' => 'alolalevel',
            'galar' => 'galarianlevel',
            'paldea' => 'paldeanlevel',
            default => 'standardlevel',
        };
    }

    private function statsHeadingNeedleForRegionalForm(string $regionalForm): ?string
    {
        return match ($regionalForm) {
            'hisui' => 'Stats - Hisuian',
            'alola' => 'Stats - Alolan',
            'galar' => 'Stats - Galarian',
            'paldea' => 'Stats - Paldean',
            default => null,
        };
    }

    private function regionalAbilitySectionLabel(string $regionalForm): ?string
    {
        return match ($regionalForm) {
            'hisui' => 'Hisuian Form Abilities',
            'alola' => 'Alola Form Abilities',
            'galar' => 'Galarian Form Abilities',
            'paldea' => 'Paldean Form Abilities',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function parseRegionalFormAbilities(string $html, string $regionalForm): array
    {
        $label = $this->regionalAbilitySectionLabel($regionalForm);
        if ($label === null) {
            return [];
        }

        $dom = $this->parseDom($html);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $tds = $xpath->query("//td[contains(., '{$label}')]");
        if ($tds === false || $tds->length === 0) {
            return [];
        }

        $targetTd = $tds->item(0);
        if (! $targetTd instanceof \DOMElement) {
            return [];
        }

        return $this->collectAbilityNamesFromAbilitiesCell($xpath, $targetTd);
    }

    /**
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}|null
     */
    private function extractStatsFromRow(\DOMNode $row): ?array
    {
        $cells = [];
        foreach ($row->childNodes as $child) {
            if ($child->nodeName === 'td') {
                $text = trim($child->textContent ?? '');
                $cells[] = $text;
            }
        }

        // The row should be: "Base Stats - Total: NNN | HP | Atk | Def | SpA | SpD | Spe"
        // But the stat values are numeric — filter to find them
        $numbers = [];
        foreach ($cells as $cell) {
            if (preg_match('/^\d+$/', trim($cell))) {
                $numbers[] = (int) $cell;
            }
        }

        if (count($numbers) < 6) {
            return null;
        }

        return [
            'hp' => $numbers[0],
            'atk' => $numbers[1],
            'def' => $numbers[2],
            'spa' => $numbers[3],
            'spd' => $numbers[4],
            'spe' => $numbers[5],
        ];
    }

    /**
     * Abilities come from the same Serebii Champions page: the row labeled "Abilities:"
     * for the base form (first such cell) or for the matching Mega section (cell after
     * the "Mega …" heading, e.g. Charizard X vs Y). Only concrete /abilitydex/{name}.shtml
     * links count — not Abilitydex index/navigation URLs.
     *
     * @return list<string>
     */
    private function parseAbilitiesFromSection(string $html, bool $isMega, string $megaName): array
    {
        $dom = $this->parseDom($html);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);

        $abilityTds = $xpath->query('//td[contains(., "Abilities:")]');
        if ($abilityTds === false || $abilityTds->length === 0) {
            return [];
        }

        $targetTd = null;

        if (! $isMega) {
            $targetTd = $abilityTds->item(0);
        } else {
            $headingNeedle = strtolower($this->megaHeadingSearchText($megaName));
            if ($headingNeedle !== '') {
                $headingNodes = $xpath->query('//*[self::h1 or self::h2 or self::h3]');
                if ($headingNodes !== false) {
                    foreach ($headingNodes as $candidate) {
                        $headingText = strtolower(trim($candidate->textContent ?? ''));
                        if ($headingText === '' || ! str_contains($headingText, $headingNeedle)) {
                            continue;
                        }

                        $following = $xpath->query('following::td[contains(., "Abilities:")]', $candidate);
                        if ($following !== false && $following->length > 0) {
                            $targetTd = $following->item(0);

                            break;
                        }
                    }
                }
            }

            if (! $targetTd instanceof \DOMElement) {
                $targetTd = $abilityTds->item($abilityTds->length - 1);
            }
        }

        if (! $targetTd instanceof \DOMElement) {
            return [];
        }

        return $this->collectAbilityNamesFromAbilitiesCell($xpath, $targetTd);
    }

    /**
     * Heading text used to find the correct Mega block on pages with multiple Megas (e.g. Charizard X/Y).
     */
    private function megaHeadingSearchText(string $pokedexName): string
    {
        if (preg_match('/^(.+)-mega-([xy])$/i', $pokedexName, $m)) {
            $speciesTitle = Str::title(str_replace('-', ' ', $m[1]));

            return 'Mega '.$speciesTitle.' '.strtoupper($m[2]);
        }

        return $this->megaDisplayName($pokedexName);
    }

    /**
     * @return list<string>
     */
    private function collectAbilityNamesFromAbilitiesCell(DOMXPath $xpath, \DOMElement $abilitiesTd): array
    {
        $nodes = $xpath->query('.//a[contains(@href, "/abilitydex/")]', $abilitiesTd);
        if ($nodes === false) {
            return [];
        }

        $abilities = [];
        $seen = [];

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            if (! $this->isSpecificAbilitydexLink($node->getAttribute('href'))) {
                continue;
            }

            $text = trim($node->textContent ?? '');
            if ($text === '' || strcasecmp($text, 'Battle Bond') === 0) {
                continue;
            }

            $key = strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $abilities[] = $text;
        }

        return $abilities;
    }

    /**
     * True when href points at a concrete ability page, not the Abilitydex index.
     */
    private function isSpecificAbilitydexLink(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
        $needle = '/abilitydex/';
        $pos = strpos($path, $needle);
        if ($pos === false) {
            return false;
        }

        $tail = substr($path, $pos + strlen($needle));
        $tail = trim($tail, '/');
        if ($tail === '' || str_contains($tail, '/')) {
            return false;
        }

        if (! str_ends_with(strtolower($tail), '.shtml')) {
            return false;
        }

        $base = basename($tail, '.shtml');
        if ($base === '' || strcasecmp($base, 'index') === 0) {
            return false;
        }

        return true;
    }

    /**
     * Convert a pokedex name like "chandelure-mega" to "Mega Chandelure" for matching on the Serebii page.
     */
    private function megaDisplayName(string $pokedexName): string
    {
        // Eternal Flower Floette mega is titled "Mega Floette" on Serebii, not "Mega Floette Eternal"
        $base = preg_replace('/-eternal-mega$/i', '-mega', $pokedexName) ?? $pokedexName;

        // "chandelure-mega" → "Mega Chandelure"
        $base = preg_replace('/-mega(-[xyz])?$/i', '', $base) ?? $base;
        $base = preg_replace('/-mega-[a-z]$/i', '', $base) ?? $base;
        $base = str_replace('-', ' ', $base);

        return 'Mega '.Str::title($base);
    }

    private function isMegaName(string $name): bool
    {
        return str_contains($name, '-mega');
    }

    /**
     * Internal pokedex species slug (e.g. mr-mime) → Serebii /pokedex-champions/ path segment (e.g. mr.mime).
     *
     * @return array<string, string>
     */
    public static function serebiiSpeciesPathSegmentMap(): array
    {
        return [
            'mr-mime' => 'mr.mime',
            'mime-jr' => 'mimejr.',
            'mr-rime' => 'mr.rime',
            'kommo-o' => 'kommo-o',
            'jangmo-o' => 'jangmo-o',
            'hakamo-o' => 'hakamo-o',
            'porygon-z' => 'porygon-z',
            'ho-oh' => 'ho-oh',
            'chi-yu' => 'chi-yu',
            'chien-pao' => 'chien-pao',
            'ting-lu' => 'ting-lu',
            'wo-chien' => 'wo-chien',
        ];
    }

    /**
     * Reverse of {@see serebiiSpeciesPathSegmentMap()}: Serebii path segment → internal base species name.
     */
    public function pokedexBaseNameFromSerebiiSpeciesSlug(string $serebiiSlug): string
    {
        $serebiiSlug = strtolower(trim($serebiiSlug, '/'));

        static $reverse = null;
        if ($reverse === null) {
            $reverse = array_flip(self::serebiiSpeciesPathSegmentMap());
        }

        return $reverse[$serebiiSlug] ?? $serebiiSlug;
    }

    /**
     * Serebii uses dots in slug for names like "Mr. Mime" → "mr.mime", "Kommo-o" → "kommo-o".
     */
    private function applySerebiiSpecialSlugs(string $slug): string
    {
        $map = self::serebiiSpeciesPathSegmentMap();

        return $map[$slug] ?? $slug;
    }

    private function parseDom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);

        // Prepend XML encoding declaration so DOMDocument parses UTF-8 correctly
        // without the deprecated mb_convert_encoding HTML-ENTITIES approach.
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);

        libxml_clear_errors();

        return $loaded ? $dom : null;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::withHeaders(self::HTTP_HEADERS)
                ->timeout(45)
                ->retry(2, 1000, fn (\Exception $e): bool => ! ($e instanceof \Illuminate\Http\Client\RequestException))
                ->get($url);
        } catch (\Exception $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * Resolve move names to learnset entries using PokeApiMoveCache first, then PokeAPI.
     *
     * @param  list<string>  $moveNames
     * @return list<array{move_id: int, move_name: string, method: string, level: int}>
     */
    private function resolveLearnset(array $moveNames): array
    {
        $baseUrl = rtrim((string) config('pokemon.pokeapi_url'), '/');

        // Build slug → display name map
        /** @var array<string, string> $slugToDisplay */
        $slugToDisplay = [];
        foreach ($moveNames as $displayName) {
            $slug = Str::slug($displayName);
            if ($slug !== '') {
                $slugToDisplay[$slug] = $displayName;
            }
        }

        // Look up what we have in PokeApiMoveCache by name
        $cachedMoves = PokeApiMoveCache::query()
            ->whereIn('name', array_keys($slugToDisplay))
            ->get(['id', 'name'])
            ->keyBy('name');

        $learnset = [];
        $missing = [];

        foreach ($slugToDisplay as $slug => $displayName) {
            $cached = $cachedMoves->get($slug);
            if ($cached !== null) {
                $learnset[] = [
                    'move_id' => (int) $cached->id,
                    'move_name' => $slug,
                    'method' => 'machine',
                    'level' => 0,
                ];
            } else {
                $missing[$slug] = $displayName;
            }
        }

        // For missing moves, fetch from PokeAPI
        foreach ($missing as $slug => $displayName) {
            $data = $this->getPokeApiJson("{$baseUrl}/move/{$slug}/");
            if ($data !== null && isset($data['id'])) {
                $moveId = (int) $data['id'];
                $learnset[] = [
                    'move_id' => $moveId,
                    'move_name' => $slug,
                    'method' => 'machine',
                    'level' => 0,
                ];

                // Cache it
                PokeApiMoveCache::query()->updateOrCreate(
                    ['id' => $moveId],
                    [
                        'name' => $slug,
                        'type_slug' => isset($data['type']['name']) ? (string) $data['type']['name'] : 'unknown',
                        'damage_class' => isset($data['damage_class']['name']) ? (string) $data['damage_class']['name'] : 'status',
                        'power' => isset($data['power']) && $data['power'] !== null ? (int) $data['power'] : null,
                        'accuracy' => isset($data['accuracy']) && $data['accuracy'] !== null ? (int) $data['accuracy'] : null,
                        'ailment_name' => isset($data['meta']['ailment']['name']) && is_string($data['meta']['ailment']['name'])
                            ? $data['meta']['ailment']['name']
                            : null,
                        'short_effect_en' => $this->extractShortEffect($data),
                        'updated_at' => now(),
                    ]
                );
            }

            usleep(self::POKEAPI_SLEEP_US);
        }

        usort($learnset, fn (array $a, array $b) => strcmp($a['move_name'], $b['move_name']));

        return $learnset;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractShortEffect(array $data): ?string
    {
        foreach ($data['effect_entries'] ?? [] as $entry) {
            if (! is_array($entry) || empty($entry['short_effect'])) {
                continue;
            }

            $lang = $entry['language']['name'] ?? '';
            if ($lang === 'en') {
                return (string) $entry['short_effect'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $abilityNames
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function resolveAbilityIds(string $baseUrl, array $abilityNames): array
    {
        $ids = [];
        foreach ($abilityNames as $name) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $data = $this->getPokeApiJson("{$baseUrl}/ability/{$slug}/");
            if ($data !== null && isset($data['id'])) {
                $ids[] = (int) $data['id'];
            }

            usleep(self::POKEAPI_SLEEP_US);
        }

        return [
            $ids[0] ?? null,
            $ids[1] ?? null,
            $ids[2] ?? null,
        ];
    }

    /**
     * @param  list<string>  $abilityNames
     * @param  array{0: int|null, 1: int|null, 2: int|null}  $resolvedIds
     */
    private function syncAbilityGenerationData(
        int $pokedexId,
        int $versionGroupId,
        array $abilityNames,
        ?int $primaryId,
        ?int $secondaryId,
        ?int $hiddenId
    ): void {
        AbilityGenerationData::query()
            ->where('pokedex_id', $pokedexId)
            ->where('version_group_id', $versionGroupId)
            ->delete();

        $slotMap = [0 => $primaryId, 1 => $secondaryId, 2 => $hiddenId];

        foreach ($abilityNames as $slot => $name) {
            $abilityId = $slotMap[$slot] ?? null;
            if ($abilityId === null) {
                continue;
            }

            AbilityGenerationData::query()->create([
                'pokedex_id' => $pokedexId,
                'version_group_id' => $versionGroupId,
                'pokeapi_ability_id' => $abilityId,
                'ability_name' => Str::slug($name),
                'slot' => $slot + 1,
                'is_hidden' => $slot === 2,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function parseTypes(Pokedex $pokedex): array
    {
        $type1 = (string) $pokedex->getAttribute('type1');
        $type2 = $pokedex->getAttribute('type2');

        return [
            $type1 !== '' ? $type1 : 'Normal',
            ($type2 !== null && $type2 !== '' && $type2 !== '-') ? (string) $type2 : null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function defaultMechanics(VersionGroup $versionGroup): array
    {
        return [
            'tera_capable' => $versionGroup->isTeraMechanic(),
            'mega' => $versionGroup->isMegaMechanic(),
            'z_move' => false,
            'dynamax' => false,
            'gmax' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPokeApiJson(string $url): ?array
    {
        try {
            $response = Http::timeout(45)
                ->retry(2, 500, fn (\Exception $e): bool => ! ($e instanceof \Illuminate\Http\Client\RequestException))
                ->acceptJson()
                ->get($url);
        } catch (\Exception $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }
}
