<?php

namespace App\Kernel\Contracts;

interface PlayoffsOperations
{
    /**
     * @return array<string, mixed>
     */
    public function adminPageProps(int $leagueId, int $userId): array;

    /**
     * @param  array{format: mixed, bracket_size: int, seed_order: list<int>}  $validated
     * @return array{errors?: array<string, string>}
     */
    public function updateConfig(int $leagueId, array $validated): array;

    /** @return array{errors?: array<string, string>} */
    public function generateBracket(int $leagueId): array;

    /** @return array{errors?: array<string, string>} */
    public function recordResult(int $leagueId, int $playoffMatchId, int $team1Score, int $team2Score): array;

    /** @return array{errors?: array<string, string>} */
    public function rollbackResult(int $leagueId, int $playoffMatchId): array;

    /** @return array{errors?: array<string, string>} */
    public function closePlayoffs(int $leagueId): array;

    /** @return array{errors?: array<string, string>} */
    public function resetBracket(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function playoffPayloadWithPokepaste(int $playoffId, int $leagueId, ?int $userId): array;
}
