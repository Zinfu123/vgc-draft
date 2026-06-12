<?php

namespace App\Kernel\Contracts;

use Closure;
use Illuminate\Http\Request;

interface LeagueOperations
{
    /**
     * @return array<string, mixed>
     */
    public function indexPageProps(): array;

    /**
     * @return array<string, mixed>
     */
    public function createEditPageProps(Request $request): array;

    public function createOrEditLeague(Request $request): int;

    /**
     * @return array<string, mixed>
     */
    public function dashboardPageProps(int $leagueId, int $userId, ?int $requestedTeamId): array;

    /**
     * @return array<string, mixed>
     */
    public function teamsPageProps(int $leagueId): array;

    /**
     * @return array<string, mixed>
     */
    public function schedulePageProps(int $leagueId, int $userId, ?int $requestedTeamId, string $requestedView): array;

    /**
     * @return array<string, mixed>
     */
    public function matchesPageProps(int $leagueId, int $userId, ?int $requestedTeamId): array;

    /**
     * @return array<string, mixed>
     */
    public function standingsPageProps(int $leagueId): array;

    /**
     * @return array<string, mixed>
     */
    public function statsPageProps(int $leagueId): array;

    public function statsKillLeadersLoader(int $leagueId): Closure;

    /**
     * @return array<string, mixed>
     */
    public function tradesPageProps(int $leagueId): array;

    /**
     * @return array<string, mixed>
     */
    public function draftPageProps(int $leagueId): array;

    /**
     * @return array<string, mixed>
     */
    public function playoffsPageProps(int $leagueId, ?int $userId): array;

    public function assertAdmin(int $leagueId, int $userId): void;

    /**
     * @return array<string, mixed>
     */
    public function adminMatchConfigPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminDiscordPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminTradesPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminWinnerPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminReopenMatchPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminDraftPageProps(int $leagueId, int $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function adminLeagueAdminsPageProps(int $leagueId, int $userId): array;

    public function updateDraftConfig(int $leagueId, Request $request): void;

    /**
     * @param  list<int>  $teamIds
     */
    public function updateDraftPickOrder(int $leagueId, array $teamIds): void;

    public function updateTeamAdmin(int $leagueId, int $teamId, bool $adminFlag): void;

    public function dropTeamFromLeague(int $leagueId, int $teamId): void;

    public function reopenMatchSet(int $leagueId, int $setId): void;

    public function updateDiscordWebhook(int $leagueId, Request $request): void;

    /**
     * @return array{errors?: array<string, string>}
     */
    public function updateTradeDeadline(int $leagueId, int $userId, Request $request): array;

    /**
     * @return array{errors?: array<string, string>}
     */
    public function updateFreeTradeWindow(int $leagueId, int $userId, Request $request): array;

    /**
     * @return array{errors?: array<string, string>, redirect?: string}
     */
    public function cancelLeague(int $leagueId, int $userId): array;

    /**
     * @return array{errors?: array<string, string>}
     */
    public function startRegularSeason(int $leagueId, int $userId): array;

    /**
     * @return array{errors?: array<string, string>}
     */
    public function startPlayoffs(int $leagueId, int $userId): array;

    /**
     * @return array{errors?: array<string, string>}
     */
    public function finalizeRegularSeason(int $leagueId, int $userId): array;
}
