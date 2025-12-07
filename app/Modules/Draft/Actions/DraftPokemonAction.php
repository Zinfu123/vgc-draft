<?php

namespace App\Modules\Draft\Actions;

/* Define Models */
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;

/* End Define Models */

class DraftPokemonAction
{
    public function __invoke($data)
    {    /* check if the team has enough points to draft the pokemon */
        $team = Team::find($data['team_id']);
        if ($team->draft_points < $data['pokemon_cost'] + $data['mandatory_picks']) {
            throw new \Exception('Team does not have enough points to draft the pokemon');
        }

        $leaguePokemon = LeaguePokemon::where('id', $data['pokemon_id'])->first();
        if (! $leaguePokemon) {
            throw new \Exception('Pokemon not found');
        }

        /* draft the pokemon */
        $draft = DraftPick::create([
            'draft_id' => $data['draft_id'],
            'team_id' => $data['team_id'],
            'league_id' => $data['league_id'],
            'league_pokemon_id' => $leaguePokemon->id,
            'round_number' => $data['round_number'],
            'pick_number' => $data['pick_number'],
        ]);

        /* update the team draft_points */
        $team->draft_points = $team->draft_points - $data['pokemon_cost'];
        $team->save();

        /* update active draft order */
        $draftOrder = DraftOrder::where('team_id', $data['team_id'])->where('pick_number', $data['pick_number'])->where('status', 1)->first();
        $draftOrder->status = 0;
        $draftOrder->save();

        $leaguePokemon->drafted_by = $data['team_id'];
        $leaguePokemon->is_drafted = 1;
        $leaguePokemon->save();

        $draftIncrement = Draft::where('league_id', $data['league_id'])->first();
        $draftIncrement->pick_number = $draftIncrement->pick_number + 1;
        $draftIncrement->save();

        /* if is the last pick, update the draft order */
        if ($data['is_last_pick'] == 1) {
            /* increment the round number */
            $roundNumber = Draft::where('league_id', $data['league_id'])->first();
            $roundNumber->round_number = $roundNumber->round_number + 1;
            $roundNumber->pick_number = 1;
            $roundNumber->save();
            /* create new draft order */
            (new CreateEditDraftOrderAction)->__invoke(['league_id' => $data['league_id']]);
        }
    }
}
