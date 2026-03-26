<?php

namespace App\Modules\Trade\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Actions\CreateTradeAction;
use App\Modules\Trade\Actions\ExecuteFreeAgencyTradeAction;
use App\Modules\Trade\Actions\ReadTradesAction;
use App\Modules\Trade\Actions\RespondToTradeAction;
use App\Modules\Trade\Models\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    public function index(
        League $league,
        LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction,
        ReadTradesAction $readTradesAction,
        ReadLeaguePokemonAction $readLeaguePokemonAction,
    ): Response {
        $userTeam = Team::query()
            ->where('user_id', Auth::id())
            ->where('league_id', $league->id)
            ->whereNull('dropped_at')
            ->with('pokemon:id,drafted_by,name,cost,pokedex_id', 'pokemon.pokemon:id,name,sprite_url')
            ->first();

        $leagueTeams = Team::query()
            ->where('league_id', $league->id)
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
            ? $readTradesAction(['league_id' => $league->id, 'team_id' => $userTeam->id])
            : collect();

        $freeAgencyPool = $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'available']);

        return Inertia::render('league/LeagueDetailTrades', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'trades',
            'userTeam' => $userTeam,
            'leagueTeams' => $leagueTeams,
            'trades' => $trades,
            'freeAgencyPool' => $freeAgencyPool,
        ]);
    }

    public function create(Request $request, League $league, CreateTradeAction $createTradeAction): RedirectResponse
    {
        $request->merge(['league_id' => $league->id]);
        $createTradeAction($request);

        return back()->with('success', 'Trade request sent.');
    }

    public function freeAgency(Request $request, League $league, ExecuteFreeAgencyTradeAction $executeFreeAgencyTradeAction): RedirectResponse
    {
        $request->merge(['league_id' => $league->id]);
        $executeFreeAgencyTradeAction($request);

        return back()->with('success', 'Free-agency trade completed.');
    }

    public function respond(Request $request, League $league, Trade $trade, RespondToTradeAction $respondToTradeAction): RedirectResponse
    {
        $respondToTradeAction($request, $trade);

        return back()->with('success', 'Trade updated.');
    }

    public function setTeamTrades(Request $request, League $league): RedirectResponse
    {
        $request->validate([
            'trades' => ['required', 'integer', 'min:0'],
        ]);

        Team::where('league_id', $league->id)->update(['trades' => $request->trades]);

        return back()->with('success', 'Trades updated for all teams.');
    }
}
