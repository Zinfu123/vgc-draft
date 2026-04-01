<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownReplayWinnerParser
{
    /**
     * @return array{winner: ?string, is_tie: bool, errors: list<string>}
     */
    public function parse(string $log): array
    {
        $log = str_replace(["\r\n", "\r"], "\n", $log);
        $lastWinner = null;
        $lastLineWasTie = false;

        foreach (explode("\n", $log) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '|') {
                continue;
            }

            if ($line === '|tie|' || str_starts_with($line, '|tie|')) {
                $lastLineWasTie = true;

                continue;
            }

            $winParts = explode('|', $line);
            if (($winParts[1] ?? '') === 'win') {
                $winName = trim((string) ($winParts[2] ?? ''));
                if ($winName !== '') {
                    $lastWinner = $winName;
                    $lastLineWasTie = false;
                }
            }
        }

        if (($lastWinner === null || $lastWinner === '') && $lastLineWasTie) {
            return [
                'winner' => null,
                'is_tie' => true,
                'errors' => ['Replay ended in a tie; automatic scoring is not applied.'],
            ];
        }

        if ($lastWinner === null || $lastWinner === '') {
            return [
                'winner' => null,
                'is_tie' => false,
                'errors' => ['Could not find a |win| line in the replay log.'],
            ];
        }

        return ['winner' => $lastWinner, 'is_tie' => false, 'errors' => []];
    }
}
