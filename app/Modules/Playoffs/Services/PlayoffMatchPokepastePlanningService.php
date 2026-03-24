<?php

namespace App\Modules\Playoffs\Services;

use App\Modules\Playoffs\Models\PlayoffMatch;
use Illuminate\Support\Collection;

class PlayoffMatchPokepastePlanningService
{
    /**
     * Whether the coach of {@param $teamId} may create or edit a playoff paste for this match row
     * (including planning before the team appears on the cell when they could still reach it by winning out).
     */
    public function mayCoachPlanPokepaste(PlayoffMatch $match, int $teamId): bool
    {
        if ($match->isComplete()) {
            return false;
        }

        if ($match->team1_id === $teamId || $match->team2_id === $teamId) {
            return true;
        }

        $playoff = $match->playoff;
        if ($playoff === null) {
            return false;
        }

        $seedOrder = $playoff->seed_order ?? [];
        if (! in_array($teamId, $seedOrder, true)) {
            return false;
        }

        $playoff->loadMissing(['matches']);
        $bySlot = $playoff->matches->keyBy('slot');
        $seedIds = array_map(intval(...), $seedOrder);

        $t1 = $this->resolveFeedPlanning($bySlot, $seedIds, $match->feeds['team1'] ?? [], $teamId);
        $t2 = $this->resolveFeedPlanning($bySlot, $seedIds, $match->feeds['team2'] ?? [], $teamId);

        return $t1 === $teamId || $t2 === $teamId;
    }

    /**
     * @param  Collection<string, PlayoffMatch>  $bySlot
     * @param  list<int>  $seedIds
     * @param  array<string, mixed>  $feed
     */
    private function resolveFeedPlanning(Collection $bySlot, array $seedIds, array $feed, int $teamId): ?int
    {
        $kind = $feed['kind'] ?? null;
        if ($kind === 'seed') {
            $index = (int) ($feed['index'] ?? -1);

            return $seedIds[$index] ?? null;
        }

        if ($kind === 'winner_of') {
            $slot = (string) ($feed['slot'] ?? '');
            $parent = $bySlot->get($slot);
            if (! $parent instanceof PlayoffMatch) {
                return null;
            }

            if ($parent->isComplete()) {
                return $parent->winner_team_id !== null ? (int) $parent->winner_team_id : null;
            }

            $a = $this->resolveFeedPlanning($bySlot, $seedIds, $parent->feeds['team1'] ?? [], $teamId);
            $b = $this->resolveFeedPlanning($bySlot, $seedIds, $parent->feeds['team2'] ?? [], $teamId);
            if ($a === null || $b === null) {
                return null;
            }

            if ($a === $teamId || $b === $teamId) {
                return $teamId;
            }

            return null;
        }

        if ($kind === 'loser_of') {
            $slot = (string) ($feed['slot'] ?? '');
            $parent = $bySlot->get($slot);
            if (! $parent instanceof PlayoffMatch || ! $parent->isComplete()) {
                return null;
            }

            $w = (int) $parent->winner_team_id;
            $a = (int) $parent->team1_id;
            $b = (int) $parent->team2_id;

            return $w === $a ? $b : $a;
        }

        return null;
    }
}
