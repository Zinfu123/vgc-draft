<?php

namespace App\Modules\Trade\Actions;

use App\Enums\Trade\TradeCounterparty;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use App\Modules\Trade\Models\TradePokemon;
use App\Notifications\TradeRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreateTradeAction
{
    public function __invoke(Request $request): Trade
    {
        $request->validate([
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'target_team_id' => ['required', 'integer', 'exists:teams,id'],
            'offered_pokemon_ids' => ['nullable', 'array'],
            'offered_pokemon_ids.*' => ['integer', 'exists:league_pokemon,id'],
            'offered_draft_points' => ['nullable', 'integer', 'min:0'],
            'requested_pokemon_ids' => ['required', 'array', 'min:1'],
            'requested_pokemon_ids.*' => ['integer', 'exists:league_pokemon,id'],
        ]);

        $league = League::query()->with('draftConfig')->findOrFail($request->integer('league_id'));

        $this->validateLeagueAllowsTrades($league);

        if (! $request->user()->discord_id) {
            throw ValidationException::withMessages([
                'discord' => 'You must connect your Discord account before sending a trade request. Go to Profile Settings to connect.',
            ]);
        }

        $requestingTeam = Team::query()
            ->where('user_id', $request->user()->id)
            ->where('league_id', $request->league_id)
            ->whereNull('dropped_at')
            ->firstOrFail();

        $targetTeam = Team::query()
            ->where('id', $request->target_team_id)
            ->where('league_id', $request->league_id)
            ->whereNull('dropped_at')
            ->firstOrFail();

        $offeredIds = $request->input('offered_pokemon_ids', []);
        $requestedIds = $request->requested_pokemon_ids;
        $offeredDraftPoints = (int) ($request->input('offered_draft_points') ?? 0);

        $this->validateHasOffer($offeredIds, $offeredDraftPoints);
        $this->validateDraftPointsOffer($requestingTeam, $offeredDraftPoints);

        if (count($offeredIds) > 0) {
            $this->validatePokemonOwnership($offeredIds, $requestingTeam->id, $request->league_id, 'offered');
        }
        $this->validatePokemonOwnership($requestedIds, $targetTeam->id, $request->league_id, 'requested');

        if (! $league->isFreeTradeWindowActive()) {
            $this->validateTradeCount($requestingTeam, count($requestedIds));
            $this->validateTargetTradeCount($targetTeam, count($offeredIds));
        }

        $this->validateMinimumRoster($requestingTeam, $request->league_id, count($offeredIds), count($requestedIds));
        $this->validateMinimumRoster($targetTeam, $request->league_id, count($requestedIds), count($offeredIds));

        $trade = Trade::create([
            'league_id' => $request->league_id,
            'requesting_team_id' => $requestingTeam->id,
            'target_team_id' => $targetTeam->id,
            'counterparty' => TradeCounterparty::Team,
            'status' => 'pending',
            'draft_points_delta' => $offeredDraftPoints > 0 ? -$offeredDraftPoints : null,
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

        $targetUser = User::find($targetTeam->user_id);
        $trade->load('league', 'requestingTeam', 'offeredPokemon.leaguePokemon', 'requestedPokemon.leaguePokemon');

        if ($targetUser) {
            $trade->league->notify(new TradeRequestNotification($trade, $targetUser));
        }

        return $trade;
    }

    private function validateLeagueAllowsTrades(League $league): void
    {
        $allowed = match ($league->status) {
            LeagueStatus::RegularSeason, LeagueStatus::Playoffs => true,
            LeagueStatus::Staging => $league->isFreeTradeWindowActive(),
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
     * @param  array<int>  $offeredIds
     */
    private function validateHasOffer(array $offeredIds, int $offeredDraftPoints): void
    {
        if (count($offeredIds) === 0 && $offeredDraftPoints === 0) {
            throw ValidationException::withMessages([
                'offered_pokemon_ids' => 'You must offer at least one Pokémon or draft points.',
            ]);
        }
    }

    private function validateDraftPointsOffer(Team $team, int $offeredDraftPoints): void
    {
        if ($offeredDraftPoints > 0 && $team->draft_points < $offeredDraftPoints) {
            throw ValidationException::withMessages([
                'offered_draft_points' => "Your team only has {$team->draft_points} draft points but you offered {$offeredDraftPoints}.",
            ]);
        }
    }

    /**
     * @param  array<int>  $pokemonIds
     */
    private function validatePokemonOwnership(array $pokemonIds, int $teamId, int $leagueId, string $label): void
    {
        $count = LeaguePokemon::whereIn('id', $pokemonIds)
            ->where('league_id', $leagueId)
            ->where('drafted_by', $teamId)
            ->count();

        if ($count !== count($pokemonIds)) {
            throw ValidationException::withMessages([
                "{$label}_pokemon_ids" => "Some {$label} Pokémon do not belong to the correct team.",
            ]);
        }
    }

    private function validateTradeCount(Team $team, int $pokemonReceiving): void
    {
        if ($team->trades < $pokemonReceiving) {
            throw ValidationException::withMessages([
                'requested_pokemon_ids' => "Your team does not have enough trades remaining. You need {$pokemonReceiving} trade(s) but have {$team->trades}.",
            ]);
        }
    }

    private function validateTargetTradeCount(Team $team, int $pokemonReceiving): void
    {
        if ($team->trades < $pokemonReceiving) {
            throw ValidationException::withMessages([
                'offered_pokemon_ids' => "The target team does not have enough trades remaining to receive your offer. They need {$pokemonReceiving} trade(s) but have {$team->trades}.",
            ]);
        }
    }

    private function validateMinimumRoster(Team $team, int $leagueId, int $giving, int $receiving): void
    {
        $currentCount = LeaguePokemon::where('league_id', $leagueId)
            ->where('drafted_by', $team->id)
            ->count();

        $afterTrade = $currentCount - $giving + $receiving;

        $draftConfig = DraftConfig::where('league_id', $leagueId)->first();
        $minimumDrafts = $draftConfig?->minimum_drafts ?? 1;

        if ($afterTrade < $minimumDrafts) {
            throw ValidationException::withMessages([
                'offered_pokemon_ids' => "This trade would leave a team below the minimum roster size of {$minimumDrafts} Pokémon.",
            ]);
        }
    }
}
