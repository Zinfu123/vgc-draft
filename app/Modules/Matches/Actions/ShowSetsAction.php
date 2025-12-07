<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Shared\Actions\LogoToUrlAction;

/* End Define Models */

/* Define Dependencies */

/* End Define Dependencies */

class ShowSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'all') {
            $sets = Set::where('league_id', $data['league_id'])->orderBy('round', 'asc')->get();

            return $sets;
        } elseif ($data['command'] == 'detail') {
            $set = Set::where('id', $data['set_id'])->with('team1', 'team2')->first();
            if (! $set) {
                return null;
            }
            if ($set->team1 && $set->team1->logo !== null) {
                $action = new LogoToUrlAction;
                $set->team1->logo = $action->logoToUrl($set->team1->logo);
            }
            if ($set->team2 && $set->team2->logo !== null) {
                $action = new LogoToUrlAction;
                $set->team2->logo = $action->logoToUrl($set->team2->logo);
            }

            $set->team1->pokemon = LeaguePokemon::where('drafted_by', $set->team1->id)->with('pokemon')->get();
            $set->team2->pokemon = LeaguePokemon::where('drafted_by', $set->team2->id)->with('pokemon')->get();

            return $set;
        } elseif ($data['command'] == 'round') {
            $sets = Set::where('league_id', $data['league_id'])->where('round', $data['round'])->get();

            return $sets;
        } elseif ($data['command'] == 'team') {
            $sets = Set::where('league_id', $data['league_id'])->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])->get();

            return $sets;
        } elseif ($data['command'] == 'team_active') {
            $sets = Set::where('league_id', $data['league_id'])
                ->where('status', 1)
                ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
                ->orderBy('round', 'asc')->first();

            return $sets;
        } elseif ($data['command'] == 'team_played') {
            $sets = Set::where('league_id', $data['league_id'])
                ->where('status', 0)
                ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
                ->orderBy('round', 'asc')->get();

            return $sets;
        } elseif ($data['command'] == 'team_next') {
            $set = Set::where('league_id', $data['league_id'])
                ->where('status', 1)
                ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
                ->with('team1', 'team2')
                ->orderBy('round', 'asc')
                ->first();
            if ($set) {
                if ($set->team1 && $set->team1->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team1->logo = $action->logoToUrl($set->team1->logo);
                }
                if ($set->team2 && $set->team2->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team2->logo = $action->logoToUrl($set->team2->logo);
                }
            }

            return $set;
        } elseif ($data['command'] == 'played') {
            $sets = Set::where('league_id', $data['league_id'])
                ->where('status', 0)
                ->with('team1', 'team2')
                ->orderBy('round', 'asc')
                ->get();

            $sets = $sets->map(function ($set) {
                if ($set->team1 && $set->team1->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team1->logo = $action->logoToUrl($set->team1->logo);
                }
                if ($set->team2 && $set->team2->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team2->logo = $action->logoToUrl($set->team2->logo);
                }

                return $set;
            });
            $grouped = $sets->mapToGroups(function ($set) {
                return [$set->round => $set];
            });

            return $grouped;
        } elseif ($data['command'] == 'upcoming') {
            $sets = Set::where('league_id', $data['league_id'])
                ->where('status', 1)
                ->with('team1', 'team2')
                ->orderBy('round', 'asc')
                ->get();
            $sets = $sets->map(function ($set) {
                if ($set->team1->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team1->logo = $action->logoToUrl($set->team1->logo);
                }
                if ($set->team2 && $set->team2->logo !== null) {
                    $action = new LogoToUrlAction;
                    $set->team2->logo = $action->logoToUrl($set->team2->logo);
                }

                return $set;
            });
            $grouped = $sets->mapToGroups(function ($set) {
                return [$set->round => $set];
            });

            return $grouped;
        }
    }
}
