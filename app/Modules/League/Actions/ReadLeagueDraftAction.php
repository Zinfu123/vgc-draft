<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class ReadLeagueDraftAction
{
    public function __invoke($data)
    {
        $league = League::find($data['league_id']);
        $draft = Draft::where('league_id', $league->id)->first() ?: null;

        return $draft;
    }
}
