<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Matches\Models\Set;

class SetReplayUrlDuplicateChecker
{
    /**
     * @param  array{0?: ?string, 1?: ?string, 2?: ?string}  $urls  replay1, replay2, replay3
     * @return list<string> validation error messages (empty if ok)
     */
    public function validateSubmission(Set $set, array $urls): array
    {
        $errors = [];
        $keys = [];
        foreach ($urls as $i => $raw) {
            $raw = is_string($raw) ? trim($raw) : '';
            if ($raw === '') {
                continue;
            }
            $key = ShowdownReplayLogUrl::battleKeyFromReplayUrl($raw);
            if ($key === null) {
                $errors[] = 'Replay '.($i + 1).' is not a valid Pokémon Showdown replay URL.';

                continue;
            }
            if (isset($keys[$key])) {
                $errors[] = 'The same Showdown replay cannot be used more than once on this match.';
                break;
            }
            $keys[$key] = true;
        }

        if ($errors !== []) {
            return $errors;
        }

        if ($keys === []) {
            return [];
        }

        $otherSets = Set::query()
            ->where('league_id', $set->league_id)
            ->where('id', '!=', $set->id)
            ->get(['id', 'replay1', 'replay2', 'replay3']);

        foreach ($otherSets as $other) {
            foreach ([$other->replay1, $other->replay2, $other->replay3] as $existing) {
                if (! is_string($existing) || trim($existing) === '') {
                    continue;
                }
                $ek = ShowdownReplayLogUrl::battleKeyFromReplayUrl($existing);
                if ($ek === null) {
                    continue;
                }
                if (isset($keys[$ek])) {
                    $errors[] = 'This replay is already linked to another match in this league.';

                    return $errors;
                }
            }
        }

        return $errors;
    }
}
