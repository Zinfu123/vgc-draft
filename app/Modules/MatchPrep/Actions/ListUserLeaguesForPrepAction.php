<?php

namespace App\Modules\MatchPrep\Actions;

use App\Modules\Teams\Models\Team;
use Illuminate\Support\Collection as SupportCollection;

class ListUserLeaguesForPrepAction
{
    /**
     * @return list<array{id: int, name: string, status: int, team_id: int}>
     */
    public function __invoke(int $userId): array
    {
        /** @var SupportCollection<int, Team> $teams */
        $teams = Team::query()
            ->where('user_id', $userId)
            ->whereHas('league')
            ->with('league')
            ->get();

        $seen = [];

        $rows = $teams
            ->filter(fn (Team $team) => $team->league !== null)
            ->map(function (Team $team) use (&$seen) {
                $leagueId = (int) $team->league_id;
                if (isset($seen[$leagueId])) {
                    return null;
                }
                $seen[$leagueId] = true;

                return [
                    'id' => $leagueId,
                    'name' => (string) $team->league->name,
                    'status' => $team->league->status->value,
                    'team_id' => (int) $team->id,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return collect($rows)
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }
}
