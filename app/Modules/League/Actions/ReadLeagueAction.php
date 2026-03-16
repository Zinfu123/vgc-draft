<?php

namespace App\Modules\League\Actions;

/* Define Models */
use App\Models\User;
use App\Modules\League\Models\League;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Storage;

/* End Define Dependencies */

class ReadLeagueAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'active') {
            $league = League::where('status', 1)->get();
            $league = $league->map(function ($league) {
                if ($league->logo !== null) {
                    $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
                }

                return $league;
            });

            return $league;
        } elseif ($data['command'] == 'league') {
            $league = League::find($data['league_id']);
            $league = $league->map(function ($league) {
                if ($league->logo !== null) {
                    $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
                }

                return $league;
            });

            return $league;
        } elseif ($data['command'] == 'past') {
            $league = League::where('status', 0)->get();
            $league = $league->map(function ($league) {
                if ($league->logo !== null) {
                    $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
                }

                return $league;
            });
            $league = $league->map(function ($league) {
                $league->winner = User::find($league->winner)->name;

                return $league;
            });

            return $league;
        }
    }
}
