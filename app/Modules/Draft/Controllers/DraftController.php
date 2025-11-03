<?php

namespace App\Modules\Draft\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\DraftPokemonAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Actions\SortPokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Modules\Draft\Models\Draft;
use Illuminate\Support\Facades\Auth;
use App\Modules\Pokedex\Actions\QueryPokedexAction; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DraftController extends Controller
{
    public function index(ReadCurrentDraftAction $readCurrentDraftAction, QueryPokedexAction $queryPokedexAction, SortPokemonAction $sortPokemonAction, $league_id)
    {
        $teams = $readCurrentDraftAction(['league_id' => $league_id]);
        $pokemon = $queryPokedexAction(['league_id' => $league_id, 'headers' => true]);
        $league = League::find($league_id);
        $costHeaders = $pokemon->unique('league.0.pivot.cost')->pluck('league.0.pivot.cost');
        $sortedPokemon = $sortPokemonAction(['pokemon' => $pokemon, 'costHeaders' => $costHeaders]);

        return Inertia::render('draft/DraftDetail', [
            'teams' => $teams,
            'league' => $league,
            'pokemon' => $pokemon,
            'costHeaders' => $costHeaders,
        ]);
    }

    public function create(Request $request, CreateEditDraftAction $createEditDraftAction, CreateEditDraftOrderAction $createEditDraftOrderAction)
    {
        Log::info($request->all());
        $createEditDraftAction($request->all());
        $createEditDraftOrderAction($request->all());

        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
    }

    public function pick(Request $request, DraftPokemonAction $draftPokemonAction)
    {
        $user = Auth::user();
        $team = Team::where('user_id', $user->id)->where('league_id', $request->league_id)->first();
        $draft = Draft::where('league_id', $request->league_id)->first();
        $draftOrder = DraftOrder::where('league_id', $request->league_id)->where('team_id', $team->id)->where('status', 1)->first();
        $draftPokemonAction(['league_id' => $request->league_id, 'team_id' => $team->id, 'pokemon_cost' => $request->pokemon_cost, 'pokemon_id' => $request->pokemon_id, 'is_last_pick' => $team->is_last_pick, 'draft_id' => $draft->id, 'round_number' => $draft->round_number, 'pick_number' => $draftOrder->pick_number]);
        return redirect()->route('draft.detail', ['league_id' => $request->league_id]);
    }
}
