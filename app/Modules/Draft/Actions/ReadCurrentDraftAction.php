<?php

namespace App\Modules\Draft\Actions;

/* Define Models */
use App\Modules\Teams\Models\Team;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\Draft;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
/* End Define Models */

class ReadCurrentDraftAction
{
    public function __invoke($data)
    /* Drafted Pokemon */
    {
        if($data['command'] == 'draftedpokemon') {
        $draftedpokemon = DraftPick::where('league_id', $data['league_id'])->with('leaguePokemon.pokemon')->get();
        $draftedpokemon =  $draftedpokemon->pluck('league_pokemon_id')->all();
        return $draftedpokemon;
    }

    /* Draft Order */
    elseif($data['command'] == 'draftorder') {
        $roundnumber = Draft::where('league_id', $data['league_id'])->first();
        $roundnumber = $roundnumber->round_number;
        $draftorder = DraftOrder::where('league_id', $data['league_id'])->with('team')
        ->where('round_number', $roundnumber)
        ->get();
        $draftorder = $draftorder->map(function ($draftorder) {
            if ($draftorder->team->logo ?? null !== null) {
                $draftorder->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($draftorder->team->logo));
            }
            return $draftorder;
        });
        return $draftorder;
    }

    /* Current Picker */
    elseif($data['command'] == 'currentpicker') {
        $currentpicker = DraftOrder::where('league_id', $data['league_id'])->with('team')->select('team_id', 'team_name')->where('status', 1)->first();
        if ($currentpicker->team ?? null !== null) {
            if ($currentpicker->team->logo ?? null !== null) {
                $currentpicker->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($currentpicker->team->logo));
            }
        }
        return $currentpicker;
    }

    elseif($data['command'] == 'teams') {
        $teams = Team::where('league_id', $data['league_id'])->with('draftPicks.leaguePokemon.pokemon')->get();
        $teams = $teams->map(function ($team) {
            if ($team->logo !== null) {
                $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($team->logo));
            }
            return $team;
        });
        $teams = $teams->sortBy(function ($team) {  
            return $team->draftPicks->pluck('round_number', 'pick_number');
        });
        $teams = $teams->chunk(6)
        ->map(function ($chunk) {
            return $chunk->values();
        });
        return $teams;
    }
    }
}