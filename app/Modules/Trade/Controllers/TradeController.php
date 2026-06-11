<?php

namespace App\Modules\Trade\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\TradeOperations;
use App\Modules\League\Models\League;
use App\Modules\Trade\Models\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    public function index(League $league, TradeOperations $tradeOperations): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        return Inertia::render(
            'league/LeagueDetailTrades',
            $tradeOperations->indexPageProps((int) $league->id, (int) $user->id),
        );
    }

    public function create(Request $request, League $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->createTrade($request, (int) $league->id);

        return back()->with('success', 'Trade request sent.');
    }

    public function freeAgency(Request $request, League $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->executeFreeAgency($request, (int) $league->id);

        return back()->with('success', 'Free-agency trade completed.');
    }

    public function respond(Request $request, League $league, Trade $trade, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->respondToTrade($request, (int) $trade->id);

        return back()->with('success', 'Trade updated.');
    }

    public function setTeamTrades(Request $request, League $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $validated = $request->validate([
            'trades' => ['required', 'integer', 'min:0'],
        ]);

        $tradeOperations->setTeamTrades((int) $league->id, (int) $validated['trades']);

        return back()->with('success', 'Trades updated for all teams.');
    }
}
