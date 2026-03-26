<?php

namespace App\Modules\Pokepaste\Services;

use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Support\TeamMatchPokepasteRoster;

class EnforceTeamMatchPokepasteChecker
{
    public function __construct(
        private TeamMatchPokepasteRoster $roster,
    ) {}

    public function poolSetBothSidesHaveData(Set $set): bool
    {
        $teamIds = [];
        if ($set->team1_id !== null) {
            $teamIds[] = (int) $set->team1_id;
        }
        if ($set->team2_id !== null) {
            $teamIds[] = (int) $set->team2_id;
        }
        foreach ($teamIds as $teamId) {
            $paste = SetTeamPokepaste::query()
                ->where('matchable_type', Set::class)
                ->where('matchable_id', $set->id)
                ->where('team_id', $teamId)
                ->first();

            if ($paste === null || ! $this->roster->hasData($paste)) {
                return false;
            }
        }

        return true;
    }

    public function playoffMatchBothSidesHaveData(PlayoffMatch $match): bool
    {
        if ($match->team1_id === null || $match->team2_id === null) {
            return false;
        }

        foreach ([(int) $match->team1_id, (int) $match->team2_id] as $teamId) {
            $paste = SetTeamPokepaste::query()
                ->where('matchable_type', PlayoffMatch::class)
                ->where('matchable_id', $match->id)
                ->where('team_id', $teamId)
                ->first();

            if ($paste === null || ! $this->roster->hasData($paste)) {
                return false;
            }
        }

        return true;
    }
}
