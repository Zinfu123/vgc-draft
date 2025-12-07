<?php

namespace App\Modules\Draft\Actions;

/* Define Models */
use App\Modules\Teams\Models\Team;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\Draft;
use App\Models\User;
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
        if ($roundnumber === null) {
            return collect([]);
        }
        $roundnumber = $roundnumber->round_number;
        $draftorder = DraftOrder::where('league_id', $data['league_id'])->with('team')
        ->where('round_number', $roundnumber)
        ->orderBy('pick_number', 'asc')
        ->get();
        $draftorder = $draftorder->map(function ($draftorder) {
            if ($draftorder->team && $draftorder->team->logo !== null) {
                $draftorder->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($draftorder->team->logo));
            }
            return $draftorder;
        });
        return $draftorder;
    }

    /* Current Picker */
    elseif($data['command'] == 'currentpicker') {
        $currentpicker = DraftOrder::where('league_id', $data['league_id'])->with('team')->where('status', 1)->orderBy('pick_number', 'asc')->first();
        if ($currentpicker && $currentpicker->team !== null) {
            if ($currentpicker->team->logo !== null) {
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
        return $teams;
    }
    elseif($data['command'] == 'lastpick') {
        $lastpick = DraftPick::where('league_id', $data['league_id'])->with('leaguePokemon.pokemon')->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->first();
        if($lastpick !== null) {
            $lastpick->team = Team::where('id', $lastpick->team_id)->where('league_id', $lastpick->league_id)->first();
            if($lastpick->team !== null) {
                if($lastpick->team->logo !== null) {
                    $lastpick->team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($lastpick->team->logo));
                }
                $user = User::where('id', $lastpick->team->user_id)->value('name');
                $lastpick->team->coach = $user ?? null;
            }
        }
        return $lastpick;
    }
}
}