<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplayPlayerNamesParser
{
    /**
     * @return array{p1: ?string, p2: ?string, errors: list<string>}
     */
    public function parse(string $log): array
    {
        $log = str_replace(["\r\n", "\r"], "\n", $log);
        $p1 = null;
        $p2 = null;

        foreach (explode("\n", $log) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '|') {
                continue;
            }

            $parts = explode('|', $line);
            if (($parts[1] ?? '') !== 'player') {
                continue;
            }

            $slot = strtolower((string) ($parts[2] ?? ''));
            if ($slot !== 'p1' && $slot !== 'p2') {
                continue;
            }

            $name = trim((string) ($parts[3] ?? ''));
            if ($name === '') {
                continue;
            }

            if ($slot === 'p1' && $p1 === null) {
                $p1 = $name;
            }
            if ($slot === 'p2' && $p2 === null) {
                $p2 = $name;
            }
        }

        $errors = [];
        if ($p1 === null || $p2 === null) {
            $errors[] = 'Could not read both Showdown player names from the replay log.';
        }

        return ['p1' => $p1, 'p2' => $p2, 'errors' => $errors];
    }
}
