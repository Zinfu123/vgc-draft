<?php

namespace App\Modules\Trade\Actions;

use App\Enums\Trade\TradeCounterparty;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use App\Modules\Trade\Models\TradePokemon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExecuteFreeAgencyTradeAction
{
    public function __invoke(Request $request): Trade
    {
        $request->validate([
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'offered_pokemon_ids' => ['required', 'array', 'min:1'],
            'offered_pokemon_ids.*' => ['integer', 'exists:league_pokemon,id'],
            'requested_pokemon_ids' => ['required', 'array', 'min:1'],
            'requested_pokemon_ids.*' => ['integer', 'exists:league_pokemon,id'],
        ]);

        if (! $request->user()->discord_id) {
            throw ValidationException::withMessages([
                'discord' => 'You must connect your Discord account before trading. Go to Profile Settings to connect.',
            ]);
        }

        $leagueId = $request->integer('league_id');

        $league = League::query()->with('draftConfig')->findOrFail($leagueId);

        $isFreeWindow = $league->isFreeTradeWindowActive();

        $this->validateLeagueAllowsTrades($league, $isFreeWindow);

        $requestingTeam = Team::query()
            ->where('user_id', $request->user()->id)
            ->where('league_id', $leagueId)
            ->whereNull('dropped_at')
            ->firstOrFail();

        $offeredIds = $request->offered_pokemon_ids;
        $requestedIds = $request->requested_pokemon_ids;

        $this->validatePokemonOwnership($offeredIds, $requestingTeam->id, $leagueId, 'offered');
        $this->validateFreeAgencyPoolPokemon($requestedIds, $leagueId);

        $offeredSum = (int) LeaguePokemon::query()->whereIn('id', $offeredIds)->where('league_id', $leagueId)->sum('cost');
        $requestedSum = (int) LeaguePokemon::query()->whereIn('id', $requestedIds)->where('league_id', $leagueId)->sum('cost');
        $pointsDelta = $offeredSum - $requestedSum;

        if ($pointsDelta < 0) {
            $this->validateDraftPointsForShortfall($requestingTeam, abs($pointsDelta));
        }

        $tradeTokenCost = count($offeredIds) + count($requestedIds);

        if (! $isFreeWindow) {
            $this->validateTradeCount($requestingTeam, $tradeTokenCost);
        }

        $this->validateMinimumRoster($requestingTeam, $leagueId, count($offeredIds), count($requestedIds));

        return DB::transaction(function () use ($requestingTeam, $leagueId, $offeredIds, $requestedIds, $tradeTokenCost, $isFreeWindow, $pointsDelta): Trade {
            $trade = Trade::create([
                'league_id' => $leagueId,
                'requesting_team_id' => $requestingTeam->id,
                'target_team_id' => null,
                'counterparty' => TradeCounterparty::FreeAgency,
                'status' => 'accepted',
                'draft_points_delta' => $pointsDelta !== 0 ? $pointsDelta : null,
            ]);

            foreach ($offeredIds as $pokemonId) {
                TradePokemon::create([
                    'trade_id' => $trade->id,
                    'league_pokemon_id' => $pokemonId,
                    'direction' => 'offered',
                ]);
            }

            foreach ($requestedIds as $pokemonId) {
                TradePokemon::create([
                    'trade_id' => $trade->id,
                    'league_pokemon_id' => $pokemonId,
                    'direction' => 'requested',
                ]);
            }

            LeaguePokemon::query()->whereIn('id', $offeredIds)->update([
                'drafted_by' => null,
                'is_drafted' => false,
            ]);

            LeaguePokemon::query()->whereIn('id', $requestedIds)->update([
                'drafted_by' => $requestingTeam->id,
                'is_drafted' => true,
            ]);

            if (! $isFreeWindow) {
                $requestingTeam->decrement('trades', $tradeTokenCost);
            }

            if ($pointsDelta !== 0) {
                $requestingTeam->increment('draft_points', $pointsDelta);
            }

            return $trade->fresh();
        });
    }

    private function validateLeagueAllowsTrades(League $league, bool $isFreeWindow): void
    {
        $allowed = match ($league->status) {
            LeagueStatus::RegularSeason, LeagueStatus::Playoffs => true,
            LeagueStatus::Staging => $isFreeWindow,
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'league_id' => 'Trades are not allowed during the current league phase.',
            ]);
        }

        if ($league->isTradeDeadlinePassed()) {
            throw ValidationException::withMessages([
                'league_id' => 'The trade deadline for this league has passed.',
            ]);
        }
    }

    /**
     * @param  array<int>  $pokemonIds
     */
    private function validatePokemonOwnership(array $pokemonIds, int $teamId, int $leagueId, string $label): void
    {
        $count = LeaguePokemon::query()->whereIn('id', $pokemonIds)
            ->where('league_id', $leagueId)
            ->where('drafted_by', $teamId)
            ->count();

        if ($count !== count($pokemonIds)) {
            throw ValidationException::withMessages([
                "{$label}_pokemon_ids" => "Some {$label} Pokémon do not belong to your team.",
            ]);
        }
    }

    /**
     * @param  array<int>  $pokemonIds
     */
    private function validateFreeAgencyPoolPokemon(array $pokemonIds, int $leagueId): void
    {
        $count = LeaguePokemon::query()
            ->whereIn('id', $pokemonIds)
            ->where('league_id', $leagueId)
            ->freeAgencyEligible()
            ->count();

        if ($count !== count($pokemonIds)) {
            throw ValidationException::withMessages([
                'requested_pokemon_ids' => 'Some requested Pokémon are not available in the free-agent pool.',
            ]);
        }
    }

    private function validateDraftPointsForShortfall(Team $team, int $shortfall): void
    {
        if ($team->draft_points < $shortfall) {
            throw ValidationException::withMessages([
                'requested_pokemon_ids' => "Your team needs {$shortfall} draft points to cover this trade but has only {$team->draft_points}.",
            ]);
        }
    }

    private function validateTradeCount(Team $team, int $tradeTokenCost): void
    {
        if ($team->trades < $tradeTokenCost) {
            throw ValidationException::withMessages([
                'offered_pokemon_ids' => "Your team does not have enough trades remaining. You need {$tradeTokenCost} trade(s) but have {$team->trades}.",
            ]);
        }
    }

    private function validateMinimumRoster(Team $team, int $leagueId, int $giving, int $receiving): void
    {
        $currentCount = LeaguePokemon::query()->where('league_id', $leagueId)
            ->where('drafted_by', $team->id)
            ->count();

        $afterTrade = $currentCount - $giving + $receiving;

        $draftConfig = DraftConfig::query()->where('league_id', $leagueId)->first();
        $minimumDrafts = $draftConfig?->minimum_drafts ?? 1;

        if ($afterTrade < $minimumDrafts) {
            throw ValidationException::withMessages([
                'offered_pokemon_ids' => "This trade would leave your team below the minimum roster size of {$minimumDrafts} Pokémon.",
            ]);
        }
    }
}
