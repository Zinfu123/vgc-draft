<?php

namespace App\Modules\Trade\Services;

use App\Kernel\Contracts\TradeOperations;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Actions\CreateTradeAction;
use App\Modules\Trade\Actions\ExecuteFreeAgencyTradeAction;
use App\Modules\Trade\Actions\ReadLeagueTradeHistoryAction;
use App\Modules\Trade\Actions\ReadTradesAction;
use App\Modules\Trade\Actions\RespondToTradeAction;
use App\Modules\Trade\Models\Trade;
use Illuminate\Http\Request;

class TradeOperationsService implements TradeOperations
{
    public function __construct(
        private LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction,
        private ReadTradesAction $readTradesAction,
        private ReadLeagueTradeHistoryAction $readLeagueTradeHistoryAction,
        private ReadLeaguePokemonAction $readLeaguePokemonAction,
        private CreateTradeAction $createTradeAction,
        private ExecuteFreeAgencyTradeAction $executeFreeAgencyTradeAction,
        private RespondToTradeAction $respondToTradeAction,
    ) {}

    public function indexPageProps(int $leagueId, int $userId): array
    {
        $league = League::query()->findOrFail($leagueId);

        $userTeam = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->whereNull('dropped_at')
            ->with('pokemon:id,drafted_by,name,cost,pokedex_id', 'pokemon.pokemon:id,name,sprite_url')
            ->first();

        $leagueTeams = Team::query()
            ->where('league_id', $leagueId)
            ->notDropped()
            ->where('id', '!=', $userTeam?->id)
            ->with('pokemon.pokemon:id,name,sprite_url', 'user:id,name')
            ->get()
            ->map(function (Team $team) {
                $team->coach = $team->user?->name ?? '—';
                unset($team->user);

                return $team;
            });

        $trades = $userTeam
            ? ($this->readTradesAction)(['league_id' => $leagueId, 'team_id' => $userTeam->id])
            : collect();

        $freeAgencyPool = ($this->readLeaguePokemonAction)(['league_id' => $leagueId, 'command' => 'available']);

        $leagueTradeHistory = ($this->readLeagueTradeHistoryAction)($leagueId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'trades',
            'userTeam' => $userTeam,
            'leagueTeams' => $leagueTeams,
            'trades' => $trades,
            'leagueTradeHistory' => $leagueTradeHistory,
            'freeAgencyPool' => $freeAgencyPool,
        ];
    }

    public function createTrade(Request $request, int $leagueId): void
    {
        $request->merge(['league_id' => $leagueId]);
        ($this->createTradeAction)($request);
    }

    public function executeFreeAgency(Request $request, int $leagueId): void
    {
        $request->merge(['league_id' => $leagueId]);
        ($this->executeFreeAgencyTradeAction)($request);
    }

    public function respondToTrade(Request $request, int $tradeId): void
    {
        $trade = Trade::query()->findOrFail($tradeId);
        ($this->respondToTradeAction)($request, $trade);
    }

    public function setTeamTrades(int $leagueId, int $trades): void
    {
        Team::query()->where('league_id', $leagueId)->update(['trades' => $trades]);
    }
}
