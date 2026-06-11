<?php

namespace App\Modules\V2\Trade\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\TradeOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    public function index(int $league, TradeOperations $tradeOperations): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        return Inertia::render(
            'league/LeagueDetailTrades',
            $tradeOperations->indexPageProps($league, (int) $user->id),
        );
    }

    public function create(Request $request, int $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->createTrade($request, $league);

        return back()->with('success', 'Trade request sent.');
    }

    public function freeAgency(Request $request, int $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->executeFreeAgency($request, $league);

        return back()->with('success', 'Free-agency trade completed.');
    }

    public function respond(Request $request, int $league, int $trade, TradeOperations $tradeOperations): RedirectResponse
    {
        $tradeOperations->respondToTrade($request, $trade);

        return back()->with('success', 'Trade updated.');
    }

    public function setTeamTrades(Request $request, int $league, TradeOperations $tradeOperations): RedirectResponse
    {
        $validated = $request->validate([
            'trades' => ['required', 'integer', 'min:0'],
        ]);

        $tradeOperations->setTeamTrades($league, (int) $validated['trades']);

        return back()->with('success', 'Trades updated for all teams.');
    }
}
