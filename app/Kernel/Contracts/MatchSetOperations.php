<?php

namespace App\Kernel\Contracts;

use Illuminate\Http\RedirectResponse;

interface MatchSetOperations
{
    /**
     * @return array<string, mixed>|null
     */
    public function showPageProps(int $setId, int $userId): ?array;

    public function createSetsForLeague(int $leagueId): int;

    /**
     * @param  array<string, mixed>  $validated
     * @return array{set_id: int, success: bool}
     */
    public function updateSet(array $validated): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateReplays(array $data): int;

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function previewReplayPlayers(array $validated): array;

    public function importReplayTeams(int $setId, int $replaySlot, int $p1TeamId): RedirectResponse;

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessages(int $setId): array;

    public function storeMessage(int $setId, int $userId, string $body): void;

    /**
     * @return array{ok: true}
     */
    public function markMessagesRead(int $setId, int $userId): array;

    /**
     * @return array{set_id: int, flash: array{success: string}}
     */
    public function storeScheduleRequest(int $setId, int $userId, string $proposedAt): array;

    /**
     * @param  array<string, mixed>  $validated
     * @return array{set_id: int, flash: array{success: string}}
     */
    public function respondScheduleRequest(int $scheduleRequestId, int $userId, array $validated): array;
}
