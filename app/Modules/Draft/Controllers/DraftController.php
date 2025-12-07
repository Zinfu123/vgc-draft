<?php

namespace App\Modules\Draft\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\DraftPokemonAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
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
        $draftorder = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'draftorder']);
        $currentpicker = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'currentpicker']);
        $pokemon = $readLeaguePokemonAction(['league_id' => $league_id, 'command' => 'draftedpokemon']);
        $league = League::find($league_id);
        $costHeaders = $pokemon->unique('cost')->pluck('cost');
        $teams = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'teams']);
        $userTeam = Team::where('user_id', Auth::user()->id)->select('id', 'admin_flag')->where('league_id', $league_id)->first();
        $lastPick = $readCurrentDraftAction(['league_id' => $league_id, 'command' => 'lastpick']);

        return Inertia::render('draft/DraftDetail', [
            'league' => fn () => $league,
            'pokemon' => fn () => $pokemon,
            'costHeaders' => fn () => $costHeaders,
            'draftOrders' => fn () => $draftorder,
            'currentPicker' => fn () => $currentpicker,
            'lastPick' => fn () => $lastPick,
            'userTeam' => fn () => $userTeam,
            'teams' => fn () => $teams,
            'draft' => fn () => $draft,
        ]);
    }

    public function create(Request $request, CreateEditDraftAction $createEditDraftAction, CreateEditDraftOrderAction $createEditDraftOrderAction)
    {
        $leagueid = $request->league_id;
        $createEditDraftAction(['league_id' => $leagueid, 'command' => 'create']);
        $createEditDraftOrderAction(['league_id' => $leagueid]);

        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
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

        $league = League::where('id', $leagueId)->first();
        if (! $league) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'League not found.']);
        }

        $mandatoryPicks = $league->minimum_drafts - $draft->round_number;
        $draftOrder = DraftOrder::where('league_id', $leagueId)->where('team_id', $team->id)->where('status', 1)->first();
        if (! $draftOrder) {
            return redirect()->route('draft.detail', ['league_id' => $leagueId])->withErrors(['error' => 'Draft order not found for this team.']);
        }

        $draftPokemonAction(['league_id' => $leagueId, 'team_id' => $team->id, 'pokemon_cost' => $request->pokemon_cost, 'pokemon_id' => $request->pokemon_id, 'is_last_pick' => $draftOrder->is_last_pick, 'draft_id' => $draft->id, 'round_number' => $draft->round_number, 'pick_number' => $draftOrder->pick_number, 'mandatory_picks' => $mandatoryPicks]);
        $broadcast = $readLeagueDraftAction(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $leagueId]);
    }

    public function revertLastPick(Request $request, CreateEditDraftAction $createEditDraftAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $createEditDraftAction(['league_id' => $request->league_id, 'command' => 'revert_last_pick']);
        $broadcast = $readLeagueDraftAction(['league_id' => $request->league_id, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
    }

    public function abortDraft(Request $request, CreateEditDraftAction $createEditDraftAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $createEditDraftAction(['league_id' => $request->league_id, 'command' => 'abort_draft']);
        $broadcast = $readLeagueDraftAction(['league_id' => $request->league_id, 'command' => 'broadcastdraft', 'end_draft' => 1]);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }
}
