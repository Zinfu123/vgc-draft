<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplaySelectedPokemonParser
{
    public function __construct(
        private ShowdownPasteParser $pasteParser,
    ) {}

    /**
     * Parse the pokemon each side actually selected for a game by scanning
     * |switch| and |drag| protocol lines.
     *
     * Returns up to 4 unique species per side — the pokemon that entered the field.
     *
     * @return array{p1: list<string>, p2: list<string>}
     */
    public function parse(string $log): array
    {
        $log = str_replace(["\r\n", "\r"], "\n", $log);
        $p1 = [];
        $p2 = [];

        foreach (explode("\n", $log) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            $parts = explode('|', $line);
            $type = $parts[1] ?? '';

            if ($type !== 'switch' && $type !== 'drag') {
                continue;
            }

            // Format: |switch|p1a: Nickname|Species, Gender|hp
            $slotStr = $parts[2] ?? '';
            $detailStr = $parts[3] ?? '';

            if (str_starts_with($slotStr, 'p1')) {
                $side = 'p1';
            } elseif (str_starts_with($slotStr, 'p2')) {
                $side = 'p2';
            } else {
                continue;
            }

            $species = $this->pasteParser->speciesRawFromReplayPokeDetails($detailStr);
            if ($species === null || $species === '') {
                continue;
            }

            if ($side === 'p1' && ! in_array($species, $p1, true)) {
                $p1[] = $species;
            } elseif ($side === 'p2' && ! in_array($species, $p2, true)) {
                $p2[] = $species;
            }
        }

        return ['p1' => $p1, 'p2' => $p2];
    }
}
