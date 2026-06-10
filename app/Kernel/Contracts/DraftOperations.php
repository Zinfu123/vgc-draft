<?php

namespace App\Kernel\Contracts;

interface DraftOperations
{
    /**
     * @return array{type: 'redirect', league_id: int}|array{type: 'page', props: array<string, mixed>}
     */
    public function indexOutcome(int $leagueId, int $userId): array;

    public function toggleWishlist(int $teamId, int $leaguePokemonId): int;

    /**
     * @param  list<int>  $orderedLeaguePokemonIds
     */
    public function reorderWishlist(int $teamId, array $orderedLeaguePokemonIds): int;

    public function startDraft(int $leagueId): int;

    /**
     * @return array{league_id: int, errors?: array<string, string>, back?: bool}
     */
    public function ban(int $leagueId, int $userId, int $pokemonId): array;

    /**
     * @return array{league_id: int, errors?: array<string, string>, back?: bool}
     */
    public function pick(int $leagueId, int $userId, int $pokemonId, int $pokemonCost): array;

    public function revertLastPick(int $leagueId): int;

    public function abortDraft(int $leagueId): int;

    public function pauseTimer(int $leagueId): int;

    public function resumeTimer(int $leagueId): int;

    public function adjustTimer(int $leagueId, int $deltaSeconds): int;

    public function forceSkip(int $leagueId): int;
}
