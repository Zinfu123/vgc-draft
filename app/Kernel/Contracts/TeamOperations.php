<?php

namespace App\Kernel\Contracts;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface TeamOperations
{
    /**
     * @return Collection<int, mixed>
     */
    public function teamsForLeague(int $leagueId): Collection;

    public function createTeam(Request $request): int;

    /**
     * @return array{league_id: int, team_id: int}
     */
    public function showRedirectTarget(int $teamId): array;

    public function editTeam(Request $request, int $teamId): int;
}
