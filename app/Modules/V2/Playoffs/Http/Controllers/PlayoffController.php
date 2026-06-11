<?php

namespace App\Modules\V2\Playoffs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Playoff\ClosePlayoffsRequest;
use App\Http\Requests\Playoff\GeneratePlayoffBracketRequest;
use App\Http\Requests\Playoff\RecordPlayoffMatchResultRequest;
use App\Http\Requests\Playoff\RollbackPlayoffMatchRequest;
use App\Http\Requests\Playoff\UpdatePlayoffConfigRequest;
use App\Kernel\Contracts\PlayoffsOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlayoffController extends Controller
{
    public function show(int $league, PlayoffsOperations $playoffsOperations): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        return Inertia::render(
            'league/admin/Playoffs',
            $playoffsOperations->adminPageProps($league, (int) $user->id),
        );
    }

    public function update(UpdatePlayoffConfigRequest $request, int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $result = $playoffsOperations->updateConfig($league, $request->validated());

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoff configuration saved.');
    }

    public function generate(GeneratePlayoffBracketRequest $request, int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $result = $playoffsOperations->generateBracket($league);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoff bracket generated.');
    }

    public function recordResult(RecordPlayoffMatchResultRequest $request, int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $result = $playoffsOperations->recordResult(
            $league,
            (int) $request->validated('playoff_match_id'),
            (int) $request->validated('team1_score'),
            (int) $request->validated('team2_score'),
        );

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoff result saved.');
    }

    public function rollback(RollbackPlayoffMatchRequest $request, int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $match = $request->playoffMatch();
        abort_if($match === null, 404);

        $result = $playoffsOperations->rollbackResult($league, (int) $match->id);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoff result rolled back.');
    }

    public function close(ClosePlayoffsRequest $request, int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $result = $playoffsOperations->closePlayoffs($league);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoffs closed. League champion and medals are set.');
    }

    public function reset(int $league, PlayoffsOperations $playoffsOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $result = $playoffsOperations->resetBracket($league, (int) $user->id);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoff bracket cleared, league medals and champion reset. You can adjust seeds and generate again.');
    }
}
