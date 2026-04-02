<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Storage;

class ReadDashboardAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'usersActiveLeagues') {
            return Team::where('user_id', $data['user_id'])
                ->whereHas('league', fn ($query) => $query->where('status', 1))
                ->with([
                    'league.draftConfig',
                    'league.winnerUser',
                    'league.teams' => fn ($query) => $query->whereIn('medal_placement', [1, 2, 3])->with('user'),
                ])
                ->get()
                ->map(fn (Team $team) => $this->leagueShape($team));
        }

        if ($data['command'] == 'usersPastLeagues') {
            return Team::where('user_id', $data['user_id'])
                ->whereHas('league', fn ($query) => $query->where('status', 0))
                ->with([
                    'league.draftConfig',
                    'league.winnerUser',
                    'league.teams' => fn ($query) => $query->whereIn('medal_placement', [1, 2, 3])->with('user'),
                ])
                ->get()
                ->map(fn (Team $team) => $this->leagueShape($team));
        }

        if ($data['command'] == 'userStats') {
            $teams = Team::where('user_id', $data['user_id'])->get();

            $goldMedals = $teams->where('medal_placement', 1)->count();
            $silverMedals = $teams->where('medal_placement', 2)->count();
            $bronzeMedals = $teams->where('medal_placement', 3)->count();
            $gameWins = $teams->sum('game_wins');
            $gameLosses = $teams->sum('game_losses');
            $setWins = $teams->sum('set_wins');
            $setLosses = $teams->sum('set_losses');

            $teamIds = $teams->pluck('id')->all();
            $playoffStats = $this->aggregatePlayoffStatsForTeamIds($teamIds);

            return [
                'gold_medals' => $goldMedals,
                'silver_medals' => $silverMedals,
                'bronze_medals' => $bronzeMedals,
                'game_wins' => $gameWins,
                'game_losses' => $gameLosses,
                'set_wins' => $setWins,
                'set_losses' => $setLosses,
                'playoff_game_wins' => $playoffStats['playoff_game_wins'],
                'playoff_game_losses' => $playoffStats['playoff_game_losses'],
                'playoff_set_wins' => $playoffStats['playoff_set_wins'],
                'playoff_set_losses' => $playoffStats['playoff_set_losses'],
            ];
        }

        if ($data['command'] == 'openLeagues') {
            return League::query()
                ->where('status', 1)
                ->where('open', true)
                ->whereDoesntHave('teams', fn ($query) => $query->where('user_id', $data['user_id']))
                ->with('draftConfig')
                ->get()
                ->map(fn (League $league) => $this->openLeagueShape($league));
        }
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     draft_date: string|null,
     *     set_start_date: string,
     *     logo: string|null,
     *     winner: null,
     *     podium: array{first: null, second: null, third: null}
     * }
     */
    private function openLeagueShape(League $league): array
    {
        $logo = $league->logo !== null
            ? str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo))
            : null;

        return [
            'id' => $league->id,
            'name' => $league->name,
            'draft_date' => $league->draftConfig?->draft_date?->toDateString(),
            'set_start_date' => $league->set_start_date,
            'logo' => $logo,
            'winner' => null,
            'podium' => [
                'first' => null,
                'second' => null,
                'third' => null,
            ],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     status: int,
     *     draft_date: string|null,
     *     set_start_date: string,
     *     logo: string|null,
     *     winner: string|null,
     *     podium: array{first: string|null, second: string|null, third: string|null}
     * }
     */
    private function leagueShape(Team $team): array
    {
        $league = $team->league;

        $logo = $league->logo !== null
            ? str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo))
            : null;

        $podiumTeams = $league->teams->keyBy('medal_placement');

        return [
            'id' => $league->id,
            'name' => $league->name,
            'status' => $league->status,
            'draft_date' => $league->draftConfig?->draft_date?->toDateString(),
            'set_start_date' => $league->set_start_date,
            'logo' => $logo,
            'winner' => $league->winnerUser?->name,
            'podium' => [
                'first' => $podiumTeams->get(1)?->user?->name,
                'second' => $podiumTeams->get(2)?->user?->name,
                'third' => $podiumTeams->get(3)?->user?->name,
            ],
        ];
    }

    /**
     * @param  list<int>  $teamIds
     * @return array{playoff_game_wins: int, playoff_game_losses: int, playoff_set_wins: int, playoff_set_losses: int}
     */
    private function aggregatePlayoffStatsForTeamIds(array $teamIds): array
    {
        if ($teamIds === []) {
            return [
                'playoff_game_wins' => 0,
                'playoff_game_losses' => 0,
                'playoff_set_wins' => 0,
                'playoff_set_losses' => 0,
            ];
        }

        $playoffGameWins = 0;
        $playoffGameLosses = 0;
        $playoffSetWins = 0;
        $playoffSetLosses = 0;

        $matches = PlayoffMatch::query()
            ->whereNotNull('winner_team_id')
            ->whereNotNull('completed_at')
            ->where(function ($query) use ($teamIds): void {
                $query->whereIn('team1_id', $teamIds)->orWhereIn('team2_id', $teamIds);
            })
            ->get();

        foreach ($matches as $match) {
            $t1 = (int) $match->team1_id;
            $t2 = (int) $match->team2_id;
            $w = (int) $match->winner_team_id;
            $s1 = (int) ($match->team1_score ?? 0);
            $s2 = (int) ($match->team2_score ?? 0);

            if (in_array($t1, $teamIds, true)) {
                $playoffGameWins += $s1;
                $playoffGameLosses += $s2;
                $playoffSetWins += $w === $t1 ? 1 : 0;
                $playoffSetLosses += $w === $t1 ? 0 : 1;
            } elseif (in_array($t2, $teamIds, true)) {
                $playoffGameWins += $s2;
                $playoffGameLosses += $s1;
                $playoffSetWins += $w === $t2 ? 1 : 0;
                $playoffSetLosses += $w === $t2 ? 0 : 1;
            }
        }

        return [
            'playoff_game_wins' => $playoffGameWins,
            'playoff_game_losses' => $playoffGameLosses,
            'playoff_set_wins' => $playoffSetWins,
            'playoff_set_losses' => $playoffSetLosses,
        ];
    }
}
