<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;
use App\Events\DraftDetailEvent;
use Illuminate\Support\Facades\Log;
/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class ReadLeagueDraftAction
{
    public function __invoke($data)
    {

    if ($data['command'] ?? null == 'broadcastdraft') {
        $league_id = $data['league_id'];
        $end_draft = $data['end_draft'];
       DraftDetailEvent::dispatch([
        'league_id' => $league_id,
        'end_draft' => $end_draft,
       ]);

    }

    else{
    $league = League::find($data['league_id']);
    $draft = Draft::where('league_id', $league->id)->first() ?: null;

    return $draft;
}
}
}