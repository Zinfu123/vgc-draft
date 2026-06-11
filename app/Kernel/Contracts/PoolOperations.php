<?php

namespace App\Kernel\Contracts;

interface PoolOperations
{
    public function createPools(int $leagueId): int;

    public function assignTeamsToPools(int $leagueId): int;
}
