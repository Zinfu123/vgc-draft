<?php

namespace App\Modules\V2\Draft\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\AdjustDraftTimerRequest;
use App\Http\Requests\Draft\ManageDraftTimerRequest;
use App\Http\Requests\Draft\ReorderDraftWishlistRequest;
use App\Http\Requests\Draft\StartDraftRequest;
use App\Http\Requests\Draft\ToggleDraftWishlistRequest;
use App\Kernel\Contracts\DraftOperations;
use App\Kernel\Support\DraftRedirectHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DraftController extends Controller
{
    public function index(DraftOperations $draftOperations, int $league_id): Response|RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $outcome = $draftOperations->indexOutcome($league_id, (int) $user->id);

        if ($outcome['type'] === 'redirect') {
            return redirect()->route('leagues.draft', ['league' => $outcome['league_id']]);
        }

        return Inertia::render('draft/DraftDetail', $outcome['props']);
    }

    public function toggleWishlist(ToggleDraftWishlistRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->toggleWishlist(
            (int) $request->team()->id,
            (int) $request->validated('league_pokemon_id'),
        );

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function reorderWishlist(ReorderDraftWishlistRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->reorderWishlist(
            (int) $request->team()->id,
            $request->orderedLeaguePokemonIds(),
        );

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function create(StartDraftRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->startDraft((int) $request->validated('league_id'));

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function ban(Request $request, DraftOperations $draftOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $result = $draftOperations->ban(
            (int) $request->integer('league_id'),
            (int) $user->id,
            (int) $request->integer('pokemon_id'),
        );

        return DraftRedirectHelper::fromActionResult($result, 'v2.draft.detail');
    }

    public function pick(Request $request, DraftOperations $draftOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $result = $draftOperations->pick(
            (int) $request->integer('league_id'),
            (int) $user->id,
            (int) $request->integer('pokemon_id'),
            (int) $request->integer('pokemon_cost'),
        );

        return DraftRedirectHelper::fromActionResult($result, 'v2.draft.detail');
    }

    public function revertLastPick(Request $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->revertLastPick((int) $request->integer('league_id'));

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function abortDraft(Request $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->abortDraft((int) $request->integer('league_id'));

        return redirect()->route('leagues.detail', ['league' => $leagueId]);
    }

    public function pauseTimer(ManageDraftTimerRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->pauseTimer((int) $request->validated('league_id'));

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function resumeTimer(ManageDraftTimerRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->resumeTimer((int) $request->validated('league_id'));

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function adjustTimer(AdjustDraftTimerRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->adjustTimer(
            (int) $request->validated('league_id'),
            (int) $request->validated('delta_seconds'),
        );

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }

    public function forceSkip(ManageDraftTimerRequest $request, DraftOperations $draftOperations): RedirectResponse
    {
        $leagueId = $draftOperations->forceSkip((int) $request->validated('league_id'));

        return redirect()->route('v2.draft.detail', ['league_id' => $leagueId]);
    }
}
