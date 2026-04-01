<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Matches\Models\Set;

class SuggestP1TeamFromShowdownReplay
{
    /**
     * Returns team id that should be treated as Showdown player 1, or null if coaches must choose manually.
     */
    public function suggest(Set $set, ?string $p1ReplayName, ?string $p2ReplayName): ?int
    {
        $n1 = ShowdownUsernameNormalizer::normalize($p1ReplayName);
        $n2 = ShowdownUsernameNormalizer::normalize($p2ReplayName);
        if ($n1 === null || $n2 === null) {
            return null;
        }

        $set->loadMissing(['team1.user', 'team2.user']);
        $u1 = ShowdownUsernameNormalizer::normalize($set->team1?->user?->showdown_username);
        $u2 = ShowdownUsernameNormalizer::normalize($set->team2?->user?->showdown_username);
        if ($u1 === null || $u2 === null) {
            return null;
        }

        if ($n1 === $u1 && $n2 === $u2) {
            return (int) $set->team1_id;
        }

        if ($n1 === $u2 && $n2 === $u1) {
            return (int) $set->team2_id;
        }

        return null;
    }
}
