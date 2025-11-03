<?php

namespace App\Modules\Draft\Actions;

/* Define Models */
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Log;

/* End Define Models */

class ReadCurrentDraftAction
{
    public function __invoke($data)
    {
        $draftedpokemon = Team::when($data['league_id'], function ($query) use ($data) {
            $query->where('league_id', $data['league_id']);
        })
            ->with('draftPicks')
            ->with('draftPicks.leaguePokemon')
            ->get();
        Log::info($draftedpokemon);
        return $draftedpokemon;
    }
}
