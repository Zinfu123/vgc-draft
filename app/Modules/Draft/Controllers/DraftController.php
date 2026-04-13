<?php

namespace App\Modules\Draft\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Draft\Actions\BanPokemonAction;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\DraftPokemonAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftStartedBroadcastNotification;
use App\Notifications\DraftStartedNotification;
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
        $userTeam = Team::where('user_id', $user->id)->select('id', 'admin_flag')->where('league_id', $league_id)->first();
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
        ]);
    }

    public function create(Request $request, CreateEditDraftAction $createEditDraftAction, CreateEditDraftOrderAction $createEditDraftOrderAction)
    {
        $leagueid = $request->league_id;
        $league = League::with('draftConfig')->find($leagueid);

        $createEditDraftAction(['league_id' => $leagueid, 'command' => 'create']);

        if ($league->draftConfig->ban_enabled == true) {
            $createEditDraftAction(['league_id' => $leagueid, 'command' => 'create_ban']);
            $createEditDraftOrderAction(['league_id' => $leagueid, 'command' => 'create_ban_order']);
        } else {
            $createEditDraftOrderAction(['league_id' => $leagueid]);
        }

        $league->notify(new DraftStartedNotification($league));

        $league->load('teams.user');
        foreach ($league->teams as $team) {
            if ($team->user !== null) {
                $team->user->notifyNow(new DraftStartedBroadcastNotification($league));
            }
        }

        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
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

        $mandatoryPicks = $league->draftConfig->minimum_drafts - $draft->round_number;
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
}
