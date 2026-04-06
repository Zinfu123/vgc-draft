<?php

namespace App\Modules\Calendar\Actions;

use App\Models\User;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

class ReadCalendarEventsAction
{
    /**
     * @return array{
     *     draft_days: list<array{league_id: int, league_name: string, date: string}>,
     *     match_week_starts: list<array{league_id: int, league_name: string, date: string}>,
     *     scheduled_matches: list<array{set_id: int, league_id: int, opponent_team_name: string, scheduled_at: string}>
     * }
     */
    public function __invoke(User $user): array
    {
        $teams = Team::query()
            ->where('user_id', $user->id)
            ->whereHas('league', fn ($query) => $query->where('status', 1))
            ->with(['league.draftConfig'])
            ->get();

        $leagueIds = $teams->pluck('league_id')->all();
        $teamIds = $teams->pluck('id')->all();

        $draftDays = $teams
            ->filter(fn (Team $team) => $team->league?->draftConfig?->draft_date !== null)
            ->map(fn (Team $team) => [
                'league_id' => $team->league->id,
                'league_name' => $team->league->name,
                'date' => $team->league->draftConfig->draft_date->toDateString(),
            ])
            ->values()
            ->all();

        $matchWeekStarts = $teams
            ->filter(fn (Team $team) => $team->league?->set_start_date !== null)
            ->map(fn (Team $team) => [
                'league_id' => $team->league->id,
                'league_name' => $team->league->name,
                'date' => $team->league->set_start_date,
            ])
            ->values()
            ->all();

        $scheduledMatches = [];

        if ($teamIds !== []) {
            $scheduledMatches = Set::query()
                ->whereNotNull('scheduled_at')
                ->whereIn('league_id', $leagueIds)
                ->where(function ($query) use ($teamIds): void {
                    $query->whereIn('team1_id', $teamIds)->orWhereIn('team2_id', $teamIds);
                })
                ->with([
                    'team1:id,name',
                    'team2:id,name',
                ])
                ->get()
                ->map(function (Set $set) use ($teamIds): array {
                    $isTeam1 = in_array($set->team1_id, $teamIds, true);
                    $opponentTeamName = $isTeam1 ? $set->team2->name : $set->team1->name;

                    return [
                        'set_id' => $set->id,
                        'league_id' => $set->league_id,
                        'opponent_team_name' => $opponentTeamName,
                        'scheduled_at' => $set->scheduled_at->toISOString(),
                    ];
                })
                ->all();
        }

        return [
            'draft_days' => $draftDays,
            'match_week_starts' => $matchWeekStarts,
            'scheduled_matches' => $scheduledMatches,
        ];
    }
}
