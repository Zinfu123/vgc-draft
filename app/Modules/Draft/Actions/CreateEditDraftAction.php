<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use Illuminate\Support\Facades\Log;

class CreateEditDraftAction
{
    public function __invoke($data)
    {
        // Create Draft
        if ($data['command'] == 'create') {
            $draft = Draft::create([
                'league_id' => $data['league_id'],
                'round_number' => 1,
                'status' => 1,
                'pick_number' => 1,
            ]);
            $draft->save();

            return $draft;
        }
        // Next Round
        elseif ($data['command'] == 'next_round') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->round_number++;
            $draft->save();

            (new CreateEditDraftOrderAction)->__invoke([
                'league_id' => $data['league_id'],
            ]);
        }
        // End Draft
        elseif ($data['command'] == 'finalize_draft') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();
        }

        elseif ($data['command'] == 'revert_last_pick') {
            /* Revert the last picked pokemon */
            $lastPick = DraftPick::where('league_id', $data['league_id'])->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->first();
            $lastPickedPokemon = $lastPick->pluck('league_pokemon_id');
            $lastPick->delete();
            /* Revert the league pokemon is_drafted */
            $pokemonReversion = LeaguePokemon::where('id', $lastPickedPokemon)->first();
            $pokemonReversion->is_drafted = 0;
            $pokemonReversion->drafted_by = null;
            $pokemonReversion->save();
            /* Revert the team draft points */
            $team = Team::where('id', $lastPick->team_id)->first();
            $team->draft_points = $team->draft_points + $pokemonReversion->cost;
            $team->save();
            /* Revert the draft order status */
            $draftOrder = DraftOrder::where('league_id', $data['league_id'])->where('team_id', $lastPick->team_id)->where('pick_number', $lastPick->pick_number)->first();
            $draftOrder->status = 1;
            $draftOrder->save();
            /* Revert the draft pick number */
            if ($lastPick->pick_number > 1 && $lastPick->round_number > 1) {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->pick_number = $draftOrder->pick_number;
            $draft->round_number = $draftOrder->round_number;
            $draft->save();
        }
    }

    elseif ($data['command'] == 'abort_draft') {
        $draft = Draft::where('league_id', $data['league_id'])->first();
        $draft->delete();

        $draftPicks = DraftPick::where('league_id', $data['league_id'])->get();
        $leaguePokemonIDs = $draftPicks->pluck('league_pokemon_id');
        $leaguePokemon = LeaguePokemon::whereIn('id', $leaguePokemonIDs)->get();
        foreach ($leaguePokemon as $pokemon) {
            $pokemon->is_drafted = 0;
            $pokemon->drafted_by = null;
            $pokemon->save();
        }
        foreach ($draftPicks as $draftPick) {
            $draftPick->delete();
        }
        $draftOrder = DraftOrder::where('league_id', $data['league_id'])->get();
        foreach ($draftOrder as $order) {
            $order->delete();
        }

        $draftPoints = League::where('id', $data['league_id'])->first();
        $draftPoints = $draftPoints->draft_points;
        $teams = Team::where('league_id', $data['league_id'])->get();
        foreach ($teams as $team) {
            $team->draft_points = $draftPoints;
            $team->save();
        }
    }
}
}