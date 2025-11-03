<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Modules\League\Models\League;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/* End Define Dependencies */

class ReadActiveLeaguesAction
{
    public function __invoke()
    {
        $currentLeagues = League::where('status', 1)->get();
        $currentLeaguesUrl = $currentLeagues->map(function ($league) {
            if ($league->logo !== null) {
                $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
            }

            return $league;
        });

        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeaguesUrl,
        ]);
    }
}
