<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;

class BanPokemonAction
{
    public function __invoke(array $data): void
    {
        $leagueId = $data['league_id'];
        $teamId = $data['team_id'];
        $pokemonId = $data['pokemon_id'];

        $banOrder = BanOrder::where('league_id', $leagueId)
            ->where('team_id', $teamId)
            ->where('status', 1)
            ->orderBy('round_number', 'asc')
            ->orderBy('ban_number', 'asc')
            ->first();

        if (! $banOrder) {
            throw new \Exception('No active ban order found for this team.');
        }

        $leaguePokemon = LeaguePokemon::find($pokemonId);

        if (! $leaguePokemon || $leaguePokemon->banned || $leaguePokemon->drafted_by !== null) {
            throw new \Exception('Pokemon is not eligible for banning.');
        }

        $draftConfig = League::with('draftConfig')->find($leagueId)->draftConfig;

        if ($leaguePokemon->cost < $draftConfig->minimum_cost_to_ban) {
            throw new \Exception('Pokemon does not meet the minimum cost to ban.');
        }

        $leaguePokemon->banned = true;
        $leaguePokemon->save();

        $ban = Bans::where('league_id', $leagueId)
            ->where('team_id', $teamId)
            ->where('round_number', $banOrder->round_number)
            ->whereNull('pokedex_id')
            ->first();

        if ($ban) {
            $ban->pokedex_id = $leaguePokemon->pokedex_id;
            $ban->status = 1;
            $ban->save();
        }

        $banOrder->status = 0;
        $banOrder->save();

        $pendingBans = BanOrder::where('league_id', $leagueId)->where('status', 1)->count();

        if ($pendingBans === 0) {
            $draft = Draft::where('league_id', $leagueId)->first();
            $draft->status = 1;
            $draft->save();

            (new CreateEditDraftOrderAction)(['league_id' => $leagueId]);
        }

        (new DraftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);

        (new NotifyDraftNextTurnAction)(['league_id' => $leagueId]);
    }
}
