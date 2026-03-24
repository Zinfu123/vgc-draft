<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftEndedNotification;

class CreateEditDraftAction
{
    public function __invoke($data)
    {
        // Create Draft
        if ($data['command'] == 'create') {
            if (League::with('draftConfig')->find($data['league_id'])->draftConfig->ban_enabled == true) {
                $status = 2;
            } else {
                $status = 1;
            }
            $draft = Draft::create([
                'league_id' => $data['league_id'],
                'round_number' => 1,
                'status' => $status,
                'pick_number' => 1,
            ]);
            $draft->save();
            $league = League::where('id', $data['league_id'])->first();
            $league->open = false;
            $league->save();

            return $draft;
        }
        // Create Ban Placeholders
        elseif ($data['command'] == 'create_ban') {
            $draftConfig = League::with('draftConfig')->find($data['league_id'])->draftConfig;
            $teams = Team::where('league_id', $data['league_id'])->get();

            for ($round = 1; $round <= $draftConfig->bans_per_user; $round++) {
                foreach ($teams as $team) {
                    Bans::create([
                        'league_id' => $data['league_id'],
                        'team_id' => $team->id,
                        'round_number' => $round,
                        'status' => 0,
                    ]);
                }
            }
        }
        // Next Round
        elseif ($data['command'] == 'next_round') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->round_number++;
            $draft->save();

            (new CreateEditDraftOrderAction)->__invoke([
                'league_id' => $data['league_id'],
            ]);
        }
        // End Draft
        elseif ($data['command'] == 'finalize_draft') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();

            $league = League::find($data['league_id']);
            $league->notify(new DraftEndedNotification($league));
        } elseif ($data['command'] == 'revert_last_pick') {
            /* Revert the last picked pokemon */
            $lastPickedPokemonID = DraftPick::where('league_id', $data['league_id'])->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->first()->league_pokemon_id;
            $lastPick = DraftPick::where('league_id', $data['league_id'])->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->first();
            $lastPick->delete();
            /* Revert the league pokemon is_drafted */
            $pokemonReversion = LeaguePokemon::where('id', $lastPickedPokemonID)->first();
            $pokemonReversion->is_drafted = 0;
            $pokemonReversion->drafted_by = null;
            $pokemonReversion->save();
            /* Revert the team draft points */
            $team = Team::where('id', $lastPick->team_id)->first();
            $team->draft_points = $team->draft_points + $pokemonReversion->cost;
            $team->save();
            /* Revert the draft order status */
            $draftOrder = DraftOrder::where('league_id', $data['league_id'])->where('team_id', $lastPick->team_id)->orderBy('round_number', 'desc')->orderBy('pick_number', 'desc')->where('status', 0)->first();
            $draftOrder->status = 1;
            $draftOrder->save();
            /* Revert the draft pick number */
            if ($lastPick->pick_number > 1 && $lastPick->round_number > 1) {
                $draft = Draft::where('league_id', $data['league_id'])->first();
                $draft->pick_number = $draftOrder->pick_number;
                $draft->round_number = $draftOrder->round_number;
                $draft->save();
            }
        }
        // Abort Draft
        elseif ($data['command'] == 'abort_draft') {
            $draft = Draft::where('league_id', $data['league_id'])->get();
            foreach ($draft as $draft) {
                $draft->delete();
            }

            $draftPicks = DraftPick::where('league_id', $data['league_id'])->get();
            foreach ($draftPicks as $draftPick) {
                $draftPick->delete();
            }

            $bans = Bans::where('league_id', $data['league_id'])->get();
            foreach ($bans as $ban) {
                $ban->delete();
            }

            $banOrders = BanOrder::where('league_id', $data['league_id'])->get();
            foreach ($banOrders as $banOrder) {
                $banOrder->delete();
            }

            $leaguePokemon = LeaguePokemon::where('league_id', $data['league_id'])
                ->where(fn ($q) => $q->where('is_drafted', 1)->orWhere('banned', true))
                ->get();
            foreach ($leaguePokemon as $pokemon) {
                $pokemon->is_drafted = 0;
                $pokemon->drafted_by = null;
                $pokemon->banned = false;
                $pokemon->save();
            }

            $draftOrder = DraftOrder::where('league_id', $data['league_id'])->get();
            foreach ($draftOrder as $order) {
                $order->delete();
            }

            $draftPoints = League::with('draftConfig')->find($data['league_id'])->draftConfig->draft_points;
            $teams = Team::where('league_id', $data['league_id'])->get();
            foreach ($teams as $team) {
                $team->draft_points = $draftPoints;
                $team->save();
            }

            $league = League::where('id', $data['league_id'])->first();
            $league->open = true;
            $league->save();
        }
    }
}
