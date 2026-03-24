<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownPasteParser
{
    /**
     * @return array{errors: list<string>, blocks: list<array{species_raw: string, item: ?string, ability: ?string, nature: ?string, tera_type: ?string, moves: list<string>, evs: ?array<string, int>}>}
     */
    public function parse(string $paste): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $paste);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return ['errors' => ['Paste is empty.'], 'blocks' => []];
        }

        $rawBlocks = preg_split('/\n\s*\n/', $normalized);
        $rawBlocks = array_values(array_filter(array_map('trim', $rawBlocks)));

        if (count($rawBlocks) !== 6) {
            return ['errors' => ['Expected exactly 6 Pokémon blocks; found '.count($rawBlocks).'.'], 'blocks' => []];
        }

        $blocks = [];
        $errors = [];

        foreach ($rawBlocks as $i => $rawBlock) {
            $parsed = $this->parseBlock($rawBlock);
            if ($parsed['errors'] !== []) {
                foreach ($parsed['errors'] as $e) {
                    $errors[] = 'Set '.($i + 1).": {$e}";
                }

                continue;
            }
            $blocks[] = $parsed['data'];
        }

        if ($errors !== [] || count($blocks) !== 6) {
            return ['errors' => $errors !== [] ? $errors : ['Could not parse one or more Pokémon sets.'], 'blocks' => []];
        }

        return ['errors' => [], 'blocks' => $blocks];
    }

    /**
     * @return array{errors: list<string>, data?: array{species_raw: string, item: ?string, ability: ?string, nature: ?string, tera_type: ?string, moves: list<string>, evs: ?array<string, int>}}
     */
    private function parseBlock(string $block): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn (string $l) => $l !== ''));

        if ($lines === []) {
            return ['errors' => ['Empty block.']];
        }

        $first = array_shift($lines);
        $speciesLine = $this->parseSpeciesLine($first);
        if ($speciesLine === null) {
            return ['errors' => ['Invalid first line (species / item).']];
        }

        $ability = null;
        $nature = null;
        $teraType = null;
        $evs = null;
        $moves = [];

        foreach ($lines as $line) {
            if (preg_match('/^ability:\s*(.+)$/i', $line, $m)) {
                $ability = trim($m[1]);

                continue;
            }

            if (preg_match('/^tera type:\s*(.+)$/i', $line, $m)) {
                $teraType = trim($m[1]);

                continue;
            }

            if (preg_match('/^nature:\s*(.+)$/i', $line, $m)) {
                $nature = trim(rtrim($m[1], " \t\n\r\0\x0B"));
                $nature = preg_replace('/\s+Nature$/i', '', $nature) ?? $nature;

                continue;
            }

            if (preg_match('/^evs:\s*(.+)$/i', $line, $m)) {
                $parsedEvs = $this->parseEvsSegment(trim($m[1]));
                if ($parsedEvs !== []) {
                    $evs = $parsedEvs;
                }

                continue;
            }

            if (preg_match('/^level:\s*\d+/i', $line)) {
                continue;
            }

            if (preg_match('/^ivs:/i', $line)) {
                continue;
            }

            if (preg_match('/^shiny:/i', $line)) {
                continue;
            }

            if (preg_match('/^happiness:/i', $line)) {
                continue;
            }

            if (preg_match('/^ball:/i', $line)) {
                continue;
            }

            if (preg_match('/^gigantamax:/i', $line)) {
                continue;
            }

            if (preg_match('/^-\s*(.+)$/', $line, $m)) {
                $moves[] = trim($m[1]);

                continue;
            }

            if (preg_match('/^(.+)\s+Nature$/i', $line, $m)) {
                $nature = trim($m[1]);

                continue;
            }
        }

        if ($ability === null || $ability === '') {
            return ['errors' => ['Missing Ability line.']];
        }

        if (count($moves) !== 4) {
            return ['errors' => ['Exactly four moves (lines starting with "-") are required.']];
        }

        return [
            'errors' => [],
            'data' => [
                'species_raw' => $speciesLine['species'],
                'item' => $speciesLine['item'],
                'ability' => $ability,
                'nature' => $nature,
                'tera_type' => $teraType,
                'moves' => $moves,
                'evs' => $evs,
            ],
        ];
    }

    /**
     * @return array{species: string, item: ?string}|null
     */
    private function parseSpeciesLine(string $line): ?array
    {
        $line = trim($line);

        if (preg_match('/^"(?<nick>[^"]+)"\s*\((?<species>[^)]+)\)\s*(?:@\s*(?<item>.*))?$/u', $line, $m)) {
            return [
                'species' => $this->stripTrailingGenderInParentheses(trim($m['species'])),
                'item' => isset($m['item']) && trim((string) $m['item']) !== '' ? trim((string) $m['item']) : null,
            ];
        }

        // Unquoted nickname: Sham Wow (Chien-Pao) @ Focus Sash — use species in parentheses for matching.
        if (preg_match('/^(?<beforeParen>.+?)\s*\((?<species>[^)]+)\)\s*(?:@\s*(?<item>.+))?$/u', $line, $m)) {
            $species = trim($m['species']);
            if ($species !== '') {
                if (preg_match('/^[MF]$/i', $species) === 1) {
                    $species = trim($m['beforeParen']);
                }
                $species = $this->stripTrailingGenderInParentheses($species);

                return [
                    'species' => $species,
                    'item' => isset($m['item']) && trim((string) $m['item']) !== '' ? trim((string) $m['item']) : null,
                ];
            }
        }

        if (preg_match('/^(?<species>[^@]+?)(?:\s*@\s*(?<item>.+))?$/u', $line, $m)) {
            $species = trim($m['species']);
            $item = isset($m['item']) && trim((string) $m['item']) !== '' ? trim((string) $m['item']) : null;

            if ($species === '') {
                return null;
            }

            $species = $this->stripTrailingGenderInParentheses($species);

            return ['species' => $species, 'item' => $item];
        }

        return null;
    }

    /**
     * Showdown appends gender as "(M)" or "(F)" after the species; that is not part of the species name.
     */
    private function stripTrailingGenderInParentheses(string $species): string
    {
        $trimmed = trim($species);
        if (preg_match('/^(.+?)\s*\(\s*[MF]\s*\)$/iu', $trimmed, $m) === 1) {
            return trim($m[1]);
        }

        return $trimmed;
    }

    /**
     * @return array<string, int>
     */
    private function parseEvsSegment(string $segment): array
    {
        $out = [];
        $labelToKey = [
            'HP' => 'hp',
            'Atk' => 'atk',
            'Def' => 'def',
            'SpA' => 'spa',
            'SpD' => 'spd',
            'Spe' => 'spe',
        ];

        $parts = preg_split('/\s*\/\s*/', $segment) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(\d+)\s+(HP|Atk|Def|SpA|SpD|Spe)$/i', $part, $m)) {
                $label = $m[2];
                foreach ($labelToKey as $lab => $key) {
                    if (strcasecmp($lab, $label) === 0) {
                        $out[$key] = (int) $m[1];
                        break;
                    }
                }
            }
        }

        return $out;
    }
}
