<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Services\PoolSetStandingsAdjuster;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Facades\DB;

class DropTeamFromLeagueService
{
    public function __construct(
        private PoolSetStandingsAdjuster $poolSetStandingsAdjuster,
        private PlayoffBracketService $playoffBracketService,
    ) {}

    public function __invoke(Team $team): void
    {
        if ($team->dropped_at !== null) {
            return;
        }

        DB::transaction(function () use ($team): void {
            $teamId = $team->id;
            $leagueId = $team->league_id;

            Trade::query()
                ->where('league_id', $leagueId)
                ->where('status', 'pending')
                ->where(function ($q) use ($teamId): void {
                    $q->where('requesting_team_id', $teamId)->orWhere('target_team_id', $teamId);
                })
                ->update(['status' => 'cancelled']);

            $sets = Set::query()
                ->where('league_id', $leagueId)
                ->where(function ($q) use ($teamId): void {
                    $q->where('team1_id', $teamId)->orWhere('team2_id', $teamId);
                })
                ->get();

            foreach ($sets as $set) {
                $this->convertSetToByeAgainstDroppedTeam($set, $teamId);
            }

            LeaguePokemon::query()
                ->where('league_id', $leagueId)
                ->where('drafted_by', $teamId)
                ->update(['drafted_by' => null, 'is_drafted' => false]);

            $this->reconcilePlayoffsAfterDrop($team);

            $team->dropped_at = now();
            $team->user_id = null;
            if (! str_starts_with((string) $team->name, '[Withdrawn]')) {
                $team->name = '[Withdrawn] '.$team->name;
            }
            $team->save();
        });
    }

    private function convertSetToByeAgainstDroppedTeam(Set $set, int $droppedTeamId): void
    {
        if ($set->team2_id === null) {
            return;
        }

        if ((int) $set->team1_id === $droppedTeamId) {
            $this->swapSetSidesForOrientation($set);
        }

        if ((int) $set->team2_id !== $droppedTeamId) {
            return;
        }

        if ($set->is_bye) {
            $this->poolSetStandingsAdjuster->revertByeStandings($set);
        } elseif ((int) $set->status === 0) {
            $this->poolSetStandingsAdjuster->revertCompletedMatchStandings($set);
        }

        $survivor = Team::query()->find($set->team1_id);
        if ($survivor === null) {
            return;
        }

        $set->team2_id = null;
        $set->team1_score = 2;
        $set->team2_score = 0;
        $set->winner_id = $set->team1_id;
        $set->status = 0;
        $set->is_bye = true;
        $set->team2_pokepaste = null;
        $set->replay1 = null;
        $set->replay2 = null;
        $set->replay3 = null;
        $set->save();

        $this->poolSetStandingsAdjuster->applyByeWinToSurvivor($survivor->fresh());
    }

    private function swapSetSidesForOrientation(Set $set): void
    {
        $t1 = $set->team1_id;
        $set->team1_id = $set->team2_id;
        $set->team2_id = $t1;

        $s1 = $set->team1_score;
        $set->team1_score = $set->team2_score;
        $set->team2_score = $s1;

        $p1 = $set->team1_pokepaste;
        $set->team1_pokepaste = $set->team2_pokepaste;
        $set->team2_pokepaste = $p1;
    }

    private function reconcilePlayoffsAfterDrop(Team $team): void
    {
        $playoff = Playoff::query()->where('league_id', $team->league_id)->first();
        if ($playoff === null) {
            return;
        }

        $seeds = array_values(array_filter(
            array_map('intval', $playoff->seed_order ?? []),
            fn (int $id): bool => $id !== $team->id
        ));

        $referencedInMatch = PlayoffMatch::query()
            ->where('playoff_id', $playoff->id)
            ->where(function ($q) use ($team): void {
                $q->where('team1_id', $team->id)
                    ->orWhere('team2_id', $team->id)
                    ->orWhere('winner_team_id', $team->id);
            })
            ->exists();

        if ($referencedInMatch) {
            $this->playoffBracketService->resetBracketAndReopenLeague($playoff);
        }

        $playoff->refresh();
        $playoff->seed_order = $seeds;
        $playoff->save();
    }
}
