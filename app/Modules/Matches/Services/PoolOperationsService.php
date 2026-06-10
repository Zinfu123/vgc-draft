<?php

namespace App\Modules\Matches\Services;

use App\Kernel\Contracts\PoolOperations;
use App\Modules\Matches\Actions\CreateEditPoolAction;
use App\Modules\Matches\Actions\TeamsToPoolsAction;

class PoolOperationsService implements PoolOperations
{
    public function __construct(
        private CreateEditPoolAction $createEditPoolAction,
        private TeamsToPoolsAction $teamsToPoolsAction,
    ) {}

    public function createPools(int $leagueId): int
    {
        ($this->createEditPoolAction)(['league_id' => $leagueId, 'command' => 'create']);

        return $leagueId;
    }

    public function assignTeamsToPools(int $leagueId): int
    {
        ($this->teamsToPoolsAction)(['league_id' => $leagueId]);

        return $leagueId;
    }
}
