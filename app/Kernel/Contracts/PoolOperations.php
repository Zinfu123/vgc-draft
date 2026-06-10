<?php

namespace App\Kernel\Contracts;

interface PoolOperations
{
    public function poolForDetail(int $poolId): mixed;

    public function createPools(int $leagueId): int;

    public function assignTeamsToPools(int $leagueId): int;
}
