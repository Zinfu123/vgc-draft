<?php

namespace App\Modules\Draft\Controllers;

use App\Events\DraftDetailEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\AdjustDraftTimerRequest;
use App\Http\Requests\Draft\ManageDraftTimerRequest;
use App\Http\Requests\Draft\ReorderDraftWishlistRequest;
use App\Http\Requests\Draft\StartDraftRequest;
use App\Http\Requests\Draft\ToggleDraftWishlistRequest;
use App\Modules\Draft\Actions\BanPokemonAction;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\DraftPokemonAction;
use App\Modules\Draft\Actions\DraftTimerAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Actions\ReorderDraftWishlistAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Actions\StartDraftAction;
use App\Modules\Draft\Actions\ToggleDraftWishlistAction;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DraftController extends Controller
{
    public function index(ReadCurrentDraftAction $readCurrentDraftAction, ReadLeaguePokemonAction $readLeaguePokemonAction, $league_id)
    {
        $draft = Draft::where('league_id', $league_id)->first();
        if ($draft !== null && (int) $draft->status === 0) {
            return redirect()->route('leagues.draft', ['league' => $league_id]);
        }

        $league = League::with('draftConfig')->find($league_id);
        $pokemon = $readLeaguePokemonAction(['league_id' => $league_id, 'command' => 'all_with_status']);
        $costHeaders = $pokemon->unique('cost')->pluck('cost')->sortDesc()->values();
        $teams = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'teams']);
        $user = Auth::user();
        $userTeam = Team::where('user_id', $user->id)->select('id', 'admin_flag')->where('league_id', $league_id)->whereNull('dropped_at')->first();
        $wishlistLeaguePokemonIds = $userTeam !== null
            ? DraftWishlistItem::query()
                ->where('team_id', $userTeam->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('league_pokemon_id')
                ->all()
            : [];
        $canManageDraftAsAdmin = (int) $user->id === (int) $league->league_owner
            || ($userTeam !== null && (int) $userTeam->admin_flag === 1);

        $currentBanner = null;
        $banOrders = collect([]);
        $lastBan = null;
        $draftorder = collect([]);
        $currentpicker = null;
        $lastPick = null;
        $allBans = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'allbans']);

        if ($draft && $draft->status === 2) {
            $currentBanner = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'currentbanner']);
            $banOrders = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'banorder']);
            $lastBan = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'lastban']);
        } else {
            $draftorder = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'draftorder']);
            $currentpicker = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'currentpicker']);
            $lastPick = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'lastpick']);
        }

        return Inertia::render('draft/DraftDetail', [
            'league' => fn () => $league,
            'draftConfig' => fn () => $league->draftConfig,
            'pokemon' => fn () => $pokemon,
            'costHeaders' => fn () => $costHeaders,
            'draftOrders' => fn () => $draftorder,
            'currentPicker' => fn () => $currentpicker,
            'currentBanner' => fn () => $currentBanner,
            'banOrders' => fn () => $banOrders,
            'lastPick' => fn () => $lastPick,
            'lastBan' => fn () => $lastBan,
            'allBans' => fn () => $allBans,
            'userTeam' => fn () => $userTeam,
            'canManageDraftAsAdmin' => fn () => $canManageDraftAsAdmin,
            'teams' => fn () => $teams,
            'draft' => fn () => $draft,
            'wishlist_league_pokemon_ids' => fn () => $wishlistLeaguePokemonIds,
        ]);
    }

    public function toggleWishlist(ToggleDraftWishlistRequest $request, ToggleDraftWishlistAction $toggleDraftWishlistAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');
        $toggleDraftWishlistAction($request->team(), (int) $request->validated('league_pokemon_id'));

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function reorderWishlist(ReorderDraftWishlistRequest $request, ReorderDraftWishlistAction $reorderDraftWishlistAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');
        $reorderDraftWishlistAction($request->team(), $request->orderedLeaguePokemonIds());

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function create(StartDraftRequest $request, StartDraftAction $startDraftAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');

        $startDraftAction($leagueId);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function ban(Request $request, BanPokemonAction $banPokemonAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $leagueId = $request->league_id;
        $user = Auth::user();
        $team = Team::where('user_id', $user->id)->where('league_id', $leagueId)->first();

        if (! $team) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Team not found for this user and league.']);
        }

        $draft = Draft::where('league_id', $leagueId)->first();

        if (! $draft || $draft->status !== 2) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Draft is not in the ban phase.']);
        }

        $currentBanOrder = BanOrder::where('league_id', $leagueId)
            ->where('status', 1)
            ->orderBy('round_number', 'asc')
            ->orderBy('ban_number', 'asc')
            ->first();

        if (! $currentBanOrder || $currentBanOrder->team_id !== $team->id) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'It is not your turn to ban.']);
        }

        $banPokemonAction(['league_id' => $leagueId, 'team_id' => $team->id, 'pokemon_id' => $request->pokemon_id]);
        $readLeagueDraftAction(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function pick(Request $request, DraftPokemonAction $draftPokemonAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $leagueId = $request->league_id;
        $user = Auth::user();
        $team = Team::where('user_id', $user->id)->where('league_id', $leagueId)->first();
        if (! $team) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Team not found for this user and league.']);
        }

        $draft = Draft::where('league_id', $leagueId)->first();
        if (! $draft) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Draft not found for this league.']);
        }

        $league = League::with('draftConfig')->find($leagueId);
        if (! $league) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'League not found.']);
        }

        $picksMadeByTeam = \App\Modules\Draft\Models\DraftPick::query()
            ->where('league_id', $leagueId)
            ->where('team_id', $team->id)
            ->count();
        $mandatoryPicks = max(0, (int) $league->draftConfig->minimum_drafts - $picksMadeByTeam - 1);
        $draftOrder = DraftOrder::where('league_id', $leagueId)->where('team_id', $team->id)->where('status', 1)->first();
        if (! $draftOrder) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Draft order not found for this team.']);
        }

        try {
            $draftPokemonAction(['league_id' => $leagueId, 'team_id' => $team->id, 'pokemon_cost' => $request->pokemon_cost, 'pokemon_id' => $request->pokemon_id, 'is_last_pick' => $draftOrder->is_last_pick, 'draft_id' => $draft->id, 'round_number' => $draft->round_number, 'pick_number' => $draftOrder->pick_number, 'mandatory_picks' => $mandatoryPicks]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        $readLeagueDraftAction(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function revertLastPick(Request $request, CreateEditDraftAction $createEditDraftAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $createEditDraftAction(['league_id' => $request->league_id, 'command' => 'revert_last_pick']);
        $readLeagueDraftAction(['league_id' => $request->league_id, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
    }

    public function abortDraft(Request $request, CreateEditDraftAction $createEditDraftAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $createEditDraftAction(['league_id' => $request->league_id, 'command' => 'abort_draft']);
        $readLeagueDraftAction(['league_id' => $request->league_id, 'command' => 'broadcastdraft', 'end_draft' => 1]);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }

    public function pauseTimer(ManageDraftTimerRequest $request, DraftTimerAction $draftTimerAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');

        $draftTimerAction(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_PAUSE]);

        activity()
            ->withProperties(['league_id' => $leagueId])
            ->log('Draft timer paused by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function resumeTimer(ManageDraftTimerRequest $request, DraftTimerAction $draftTimerAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');

        $draftTimerAction(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_RESUME]);

        activity()
            ->withProperties(['league_id' => $leagueId])
            ->log('Draft timer resumed by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function adjustTimer(AdjustDraftTimerRequest $request, DraftTimerAction $draftTimerAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');
        $delta = (int) $request->validated('delta_seconds');

        $draftTimerAction([
            'league_id' => $leagueId,
            'command' => DraftTimerAction::COMMAND_ADJUST,
            'delta_seconds' => $delta,
        ]);

        activity()
            ->withProperties(['league_id' => $leagueId, 'delta_seconds' => $delta])
            ->log('Draft timer adjusted by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function forceSkip(ManageDraftTimerRequest $request, SkipCurrentTurnAction $skipCurrentTurnAction): \Illuminate\Http\RedirectResponse
    {
        $leagueId = (int) $request->validated('league_id');

        $skipCurrentTurnAction([
            'league_id' => $leagueId,
            'reason' => 'commissioner_force_skip',
        ]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }
}
