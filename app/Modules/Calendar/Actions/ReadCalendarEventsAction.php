<?php

namespace App\Modules\Calendar\Actions;

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Carbon\Carbon;

class ReadCalendarEventsAction
{
    /**
     * @return array{
     *     draft_days: list<array{league_id: int, league_name: string, date: string}>,
     *     match_week_starts: list<array{league_id: int, league_name: string, date: string, round_label: string, event_type: string}>,
     *     scheduled_matches: list<array{set_id: int, league_id: int, opponent_team_name: string, scheduled_at: string}>
     * }
     */
    public function __invoke(User $user): array
    {
        $activeStatuses = [
            LeagueStatus::Registration->value,
            LeagueStatus::Staging->value,
            LeagueStatus::RegularSeason->value,
            LeagueStatus::Playoffs->value,
        ];

        $teams = Team::query()
            ->where('user_id', $user->id)
            ->whereHas('league', fn ($query) => $query->whereIn('status', $activeStatuses))
            ->with(['league.draftConfig', 'league.matchConfig', 'league.draft'])
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

        $matchWeekStarts = [];

        if ($leagueIds !== []) {
            $roundsByLeague = Set::query()
                ->whereIn('league_id', $leagueIds)
                ->select('league_id', 'round')
                ->distinct()
                ->get()
                ->groupBy('league_id');

            foreach ($teams->unique('league_id') as $team) {
                $league = $team->league;

                if ($league === null || $league->set_start_date === null) {
                    continue;
                }

                $draft = $league->draft;
                if ($draft === null || (int) $draft->status !== 0) {
                    continue;
                }

                $matchConfig = $league->matchConfig;
                $frequencyType = (int) ($matchConfig?->frequency_type ?? 2);
                $frequencyValue = (int) ($matchConfig?->frequency_value ?? 1);

                $leagueRounds = $roundsByLeague->get($league->id);

                if ($leagueRounds === null || $leagueRounds->isEmpty()) {
                    continue;
                }

                $startDate = Carbon::parse($league->set_start_date);
                $sortedRounds = $leagueRounds->pluck('round')->sort()->values();
                $maxRound = $sortedRounds->last();

                foreach ($sortedRounds as $roundNumber) {
                    $roundStart = $this->calculateRoundDate($startDate, (int) $roundNumber, $frequencyType, $frequencyValue);

                    // Round end: one day before the next round starts, or use set_end_date for the final round
                    if ((int) $roundNumber === (int) $maxRound && $league->set_end_date !== null) {
                        $roundEnd = Carbon::parse($league->set_end_date);
                    } else {
                        $roundEnd = $this->calculateRoundDate($startDate, (int) $roundNumber + 1, $frequencyType, $frequencyValue)->subDay();
                    }

                    $matchWeekStarts[] = [
                        'league_id' => $league->id,
                        'league_name' => $league->name,
                        'date' => $roundStart->toDateString(),
                        'round_label' => 'Round '.$roundNumber.' Start',
                        'event_type' => 'round_start',
                    ];

                    $matchWeekStarts[] = [
                        'league_id' => $league->id,
                        'league_name' => $league->name,
                        'date' => $roundEnd->toDateString(),
                        'round_label' => 'Round '.$roundNumber.' End',
                        'event_type' => 'round_end',
                    ];
                }
            }
        }

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

    private function calculateRoundDate(Carbon $startDate, int $roundNumber, int $frequencyType, int $frequencyValue): Carbon
    {
        $offset = $roundNumber - 1;

        return match ($frequencyType) {
            1 => $startDate->copy()->addDays($offset),
            2 => $startDate->copy()->addWeeks($offset),
            3 => $startDate->copy(),
            4 => $startDate->copy()->addDays($offset * max(1, $frequencyValue)),
            default => $startDate->copy()->addWeeks($offset),
        };
    }
}
