<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplayTeamPreviewParser
{
    public function __construct(
        private ShowdownPasteParser $pasteParser,
    ) {}

    /**
     * @return array{errors: list<string>, p1: list<string>, p2: list<string>}
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
            if (($parts[1] ?? '') !== 'poke') {
                continue;
            }

            $player = strtolower((string) ($parts[2] ?? ''));
            if ($player !== 'p1' && $player !== 'p2') {
                continue;
            }

            $details = (string) ($parts[3] ?? '');
            $species = $this->pasteParser->speciesRawFromReplayPokeDetails($details);
            if ($species === null || $species === '') {
                return [
                    'errors' => ['Could not parse replay team preview line: '.substr($line, 0, 200)],
                    'p1' => [],
                    'p2' => [],
                ];
            }

            if ($player === 'p1') {
                $p1[] = $species;
            } else {
                $p2[] = $species;
            }
        }

        if (count($p1) !== 6 || count($p2) !== 6) {
            return [
                'errors' => [
                    'Expected 6 Pokémon per player in replay preview; found p1='.count($p1).', p2='.count($p2).'.',
                ],
                'p1' => [],
                'p2' => [],
            ];
        }

        return ['errors' => [], 'p1' => $p1, 'p2' => $p2];
    }
}
