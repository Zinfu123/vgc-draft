<?php

namespace App\Modules\Trade\Actions;

use App\Enums\Trade\TradeCounterparty;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Trade\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RespondToTradeAction
{
    public function __invoke(Request $request, Trade $trade): Trade
    {
        $request->validate([
            'response' => ['required', 'string', 'in:accepted,declined,cancelled'],
        ]);

        if ($trade->status !== 'pending') {
            throw ValidationException::withMessages([
                'trade' => 'This trade is no longer pending.',
            ]);
        }

        $trade->loadMissing(['league.draftConfig']);
        $this->validateLeagueAllowsTrades($trade->league);

        if ($trade->counterparty === TradeCounterparty::FreeAgency) {
            throw ValidationException::withMessages([
                'trade' => 'Free-agency trades are completed immediately and cannot be updated here.',
            ]);
        }

        $response = $request->response;

        if ($response === 'cancelled') {
            $this->authorizeCancellation($request, $trade);
            $trade->update(['status' => 'cancelled']);

            return $trade;
        }

        $this->authorizeResponse($request, $trade);

        if ($response === 'declined') {
            $trade->update(['status' => 'declined']);

            return $trade;
        }

        $this->executeAcceptedTrade($trade);

        return $trade->fresh();
    }

    private function authorizeCancellation(Request $request, Trade $trade): void
    {
        $requestingTeam = $trade->requestingTeam;
        if ($requestingTeam->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'trade' => 'Only the trade requester can cancel this trade.',
            ]);
        }
    }

    private function authorizeResponse(Request $request, Trade $trade): void
    {
        $targetTeam = $trade->targetTeam;
        if ($targetTeam->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'trade' => 'Only the trade target can accept or decline this trade.',
            ]);
        }
    }

    private function validateLeagueAllowsTrades(?\App\Modules\League\Models\League $league): void
    {
        if ($league === null) {
            return;
        }

        $allowed = match ($league->status) {
            LeagueStatus::RegularSeason, LeagueStatus::Playoffs => true,
            LeagueStatus::Staging => $league->isFreeTradeWindowActive(),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'trade' => 'Trades are not allowed during the current league phase.',
            ]);
        }

        if ($league->isTradeDeadlinePassed()) {
            throw ValidationException::withMessages([
                'trade' => 'The trade deadline for this league has passed.',
            ]);
        }
    }

    private function executeAcceptedTrade(Trade $trade): void
    {
        $trade->load([
            'requestingTeam',
            'targetTeam',
            'offeredPokemon',
            'requestedPokemon',
            'league.draftConfig',
        ]);

        $requestingTeam = $trade->requestingTeam;
        $targetTeam = $trade->targetTeam;
        $isFreeWindow = $trade->league?->isFreeTradeWindowActive() ?? false;

        $offeredIds = $trade->offeredPokemon->pluck('league_pokemon_id')->toArray();
        $requestedIds = $trade->requestedPokemon->pluck('league_pokemon_id')->toArray();

        $draftPointsDelta = $trade->draft_points_delta ?? 0;

        if ($draftPointsDelta < 0) {
            $this->validateDraftPointsOffer($requestingTeam, abs($draftPointsDelta));
        }

        if (! $isFreeWindow) {
            $this->validateTradeCount($requestingTeam, count($requestedIds));
            $this->validateTradeCount($targetTeam, count($offeredIds));
        }
        $this->validateMinimumRoster($requestingTeam, $trade->league_id, count($offeredIds), count($requestedIds));
        $this->validateMinimumRoster($targetTeam, $trade->league_id, count($requestedIds), count($offeredIds));

        if (count($offeredIds) > 0) {
            LeaguePokemon::whereIn('id', $offeredIds)->update(['drafted_by' => $targetTeam->id]);
        }

        if (count($requestedIds) > 0) {
            LeaguePokemon::whereIn('id', $requestedIds)->update(['drafted_by' => $requestingTeam->id]);
        }

        if (! $isFreeWindow) {
            $requestingTeam->decrement('trades', count($requestedIds));
            $targetTeam->decrement('trades', count($offeredIds));
        }

        if ($draftPointsDelta !== 0) {
            $requestingTeam->increment('draft_points', $draftPointsDelta);
            $targetTeam->increment('draft_points', -$draftPointsDelta);
        }

        $trade->update(['status' => 'accepted']);
    }

    private function validateDraftPointsOffer(\App\Modules\Teams\Models\Team $team, int $offeredDraftPoints): void
    {
        if ($team->draft_points < $offeredDraftPoints) {
            throw ValidationException::withMessages([
                'trade' => "{$team->name} no longer has enough draft points to complete this trade. They need {$offeredDraftPoints} but have {$team->draft_points}.",
            ]);
        }
    }

    private function validateTradeCount(\App\Modules\Teams\Models\Team $team, int $pokemonReceiving): void
    {
        if ($team->trades < $pokemonReceiving) {
            throw ValidationException::withMessages([
                'trade' => "{$team->name} does not have enough trades remaining to complete this trade. They need {$pokemonReceiving} trade(s) but have {$team->trades}.",
            ]);
        }
    }

    private function validateMinimumRoster(\App\Modules\Teams\Models\Team $team, int $leagueId, int $giving, int $receiving): void
    {
        $currentCount = LeaguePokemon::where('league_id', $leagueId)
            ->where('drafted_by', $team->id)
            ->count();

        $afterTrade = $currentCount - $giving + $receiving;

        $draftConfig = DraftConfig::where('league_id', $leagueId)->first();
        $minimumDrafts = $draftConfig?->minimum_drafts ?? 1;

        if ($afterTrade < $minimumDrafts) {
            throw ValidationException::withMessages([
                'trade' => "This trade would leave a team below the minimum roster size of {$minimumDrafts} Pokémon.",
            ]);
        }
    }
}
