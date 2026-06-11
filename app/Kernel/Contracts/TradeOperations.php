<?php

namespace App\Kernel\Contracts;

use Illuminate\Http\Request;

interface TradeOperations
{
    /**
     * @return array<string, mixed>
     */
    public function indexPageProps(int $leagueId, int $userId): array;

    public function createTrade(Request $request, int $leagueId): void;

    public function executeFreeAgency(Request $request, int $leagueId): void;

    public function respondToTrade(Request $request, int $tradeId): void;

    public function setTeamTrades(int $leagueId, int $trades): void;
}
