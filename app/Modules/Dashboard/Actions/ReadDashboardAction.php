<?php

namespace App\Modules\Dashboard\Actions;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Storage;

class ReadDashboardAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'usersActiveLeagues') {
            return Team::where('user_id', $data['user_id'])
                ->whereHas('league', fn ($query) => $query->where('status', 1))
                ->with('league.draftConfig')
                ->get()
                ->map(fn (Team $team) => $this->leagueShape($team));
        }

        if ($data['command'] == 'usersPastLeagues') {
            return Team::where('user_id', $data['user_id'])
                ->whereHas('league', fn ($query) => $query->where('status', 0))
                ->with('league.draftConfig')
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

            return [
                'gold_medals' => $goldMedals,
                'silver_medals' => $silverMedals,
                'bronze_medals' => $bronzeMedals,
                'game_wins' => $gameWins,
                'game_losses' => $gameLosses,
                'set_wins' => $setWins,
                'set_losses' => $setLosses,
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

    /** @return array{id: int, name: string, draft_date: string, set_start_date: string, logo: string|null, winner: null} */
    private function openLeagueShape(League $league): array
    {
        $logo = $league->logo !== null
            ? str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo))
            : null;

        return [
            'id' => $league->id,
            'name' => $league->name,
            'draft_date' => $league->draftConfig?->draft_date,
            'set_start_date' => $league->set_start_date,
            'logo' => $logo,
            'winner' => null,
        ];
    }

    /** @return array{id: int, name: string, status: int, draft_date: string, set_start_date: string, logo: string|null, winner: string|null} */
    private function leagueShape(Team $team): array
    {
        $league = $team->league;

        $logo = $league->logo !== null
            ? str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo))
            : null;

        $winner = $league->winner !== null
            ? User::find($league->winner)?->name
            : null;

        return [
            'id' => $league->id,
            'name' => $league->name,
            'status' => $league->status,
            'draft_date' => $league->draftConfig?->draft_date,
            'set_start_date' => $league->set_start_date,
            'logo' => $logo,
            'winner' => $winner,
        ];
    }
}
