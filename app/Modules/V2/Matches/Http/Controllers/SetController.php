<?php

namespace App\Modules\V2\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\ImportSetReplayTeamsRequest;
use App\Http\Requests\Match\PreviewSetReplayPlayersRequest;
use App\Http\Requests\Match\UpdateSetReplaysRequest;
use App\Http\Requests\Match\UpdateSetRequest;
use App\Kernel\Contracts\MatchSetOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SetController extends Controller
{
    public function create(Request $request, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $leagueId = $matchSetOperations->createSetsForLeague((int) $request->integer('league_id'));

        return redirect()->route('leagues.detail', ['league' => $leagueId]);
    }

    public function show(int $set_id, MatchSetOperations $matchSetOperations): \Inertia\Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $props = $matchSetOperations->showPageProps($set_id, (int) $user->id);
        if ($props === null) {
            abort(404, 'Set not found');
        }

        return Inertia::render('match/MatchDetail', $props);
    }

    public function update(UpdateSetRequest $request, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $result = $matchSetOperations->updateSet($request->validated());

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'set_result' => 'The match result could not be saved. Please try again.',
            ]);
        }

        return redirect()->route('v2.sets.show', ['set_id' => $result['set_id']]);
    }

    public function updateReplays(UpdateSetReplaysRequest $request, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $setId = $matchSetOperations->updateReplays($request->validated());

        return redirect()->route('v2.sets.show', ['set_id' => $setId]);
    }

    public function previewReplayPlayers(
        PreviewSetReplayPlayersRequest $request,
        MatchSetOperations $matchSetOperations,
    ): JsonResponse {
        $result = $matchSetOperations->previewReplayPlayers($request->validated());

        return response()->json($result['body'], $result['status']);
    }

    public function importReplayTeams(
        ImportSetReplayTeamsRequest $request,
        MatchSetOperations $matchSetOperations,
    ): RedirectResponse {
        return $matchSetOperations->importReplayTeams(
            (int) $request->validated('set_id'),
            (int) $request->validated('replay_slot'),
            (int) $request->validated('p1_team_id'),
        );
    }
}
