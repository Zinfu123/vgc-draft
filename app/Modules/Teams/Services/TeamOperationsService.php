<?php

namespace App\Modules\Teams\Services;

use App\Kernel\Contracts\TeamOperations;
use App\Modules\Teams\Actions\CreateEditTeamAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeamOperationsService implements TeamOperations
{
    public function __construct(
        private CreateEditTeamAction $createEditTeamAction,
        private ReadTeamAction $readTeamAction,
    ) {}

    public function teamsForLeague(int $leagueId): Collection
    {
        return Team::query()->where('league_id', $leagueId)->get();
    }

    public function createTeam(Request $request): int
    {
        $pickPosition = Team::query()->where('league_id', $request->integer('league_id'))->count() + 1;
        $request->merge(['pick_position' => $pickPosition]);

        $team = $this->createEditTeamAction->create($request);

        return (int) $team->league_id;
    }

    public function showRedirectTarget(int $teamId): array
    {
        $team = ($this->readTeamAction)(['team_id' => $teamId, 'command' => 'team']);

        return [
            'league_id' => (int) $team->league_id,
            'team_id' => (int) $team->id,
        ];
    }

    public function editTeam(Request $request, int $teamId): int
    {
        $team = $this->createEditTeamAction->edit($request->merge(['team_id' => $teamId]));

        return (int) $team->league_id;
    }
}
