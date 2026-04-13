<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use Illuminate\Support\Facades\Storage;

class ReadLeagueAction
{
    public function __invoke($data)
    {
        if ($data['command'] === 'active') {
            $activeStatuses = [
                LeagueStatus::Registration->value,
                LeagueStatus::Staging->value,
                LeagueStatus::RegularSeason->value,
                LeagueStatus::Playoffs->value,
            ];

            $leagues = League::query()
                ->whereIn('status', $activeStatuses)
                ->with('draftConfig:league_id,draft_date,draft_start_at')
                ->get();

            return $leagues->map(function (League $league) {
                if ($league->logo !== null) {
                    $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
                }

                return $league;
            });
        } elseif ($data['command'] === 'league') {
            $league = League::find($data['league_id']);
            if ($league && $league->logo !== null) {
                $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
            }

            return $league;
        } elseif ($data['command'] === 'past') {
            $leagues = League::query()
                ->where('status', LeagueStatus::Completed->value)
                ->with('winnerUser')
                ->get();

            return $leagues->map(function (League $league) {
                if ($league->logo !== null) {
                    $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
                }
                $league->winner = $league->winnerUser?->name;

                return $league;
            });
        }
    }
}
