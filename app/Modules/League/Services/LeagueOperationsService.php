<?php

namespace App\Modules\League\Services;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Enums\PokemonGame;
use App\Jobs\EnforceTradeDeadlineJob;
use App\Kernel\Contracts\LeagueOperations;
use App\Kernel\Contracts\PlayoffsOperations;
use App\Models\User;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeagueAction;
use App\Modules\League\Actions\ReadLeagueKillLeadersAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Actions\StartRegularSeasonAction;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Services\PlayoffBracketLayoutService;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use App\Modules\Shared\Actions\LogoToUrlAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Actions\ReadTradesAction;
use App\Modules\Trade\Models\Trade;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueOperationsService implements LeagueOperations
{
    public function __construct(
        private LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction,
        private ReadLeagueAction $readLeagueAction,
        private ReadTeamAction $readTeamAction,
        private ReadTradesAction $readTradesAction,
        private ReadLeaguePokemonAction $readLeaguePokemonAction,
        private ShowSetsAction $showSetsAction,
        private ReadLeagueKillLeadersAction $readLeagueKillLeadersAction,
        private ReadCurrentDraftAction $readCurrentDraftAction,
        private PlayoffBracketService $playoffBracketService,
        private PlayoffBracketLayoutService $playoffBracketLayoutService,
        private PlayoffsOperations $playoffsOperations,
        private DropTeamFromLeagueService $dropTeamFromLeagueService,
        private CreateEditSetsAction $createEditSetsAction,
        private StartRegularSeasonAction $startRegularSeasonAction,
    ) {}

    public function indexPageProps(): array
    {
        $currentLeagues = ($this->readLeagueAction)(['command' => 'active']);
        $pastLeagues = ($this->readLeagueAction)(['command' => 'past']);

        return [
            'currentLeagues' => $currentLeagues,
            'pastLeagues' => $pastLeagues,
        ];
    }

    public function createEditPageProps(Request $request): array
    {
        $league = ($this->readLeagueAction)(['league_id' => $request->league_id, 'command' => 'league']);
        $league?->loadMissing('playoff');

        $draftConfig = $league?->draftConfig;
        $matchConfig = $league?->matchConfig;
        $playoff = $league?->playoff;

        return [
            'command' => $request->command,
            'league_id' => $request->league_id ?? 0,
            'league_name' => $league?->name ?? '',
            'draft_date' => $draftConfig?->draft_date ?? null,
            'set_start_date' => $league?->set_start_date ?? null,
            'set_frequency' => $league?->set_frequency ?? 3,
            'enforce_round_count' => (bool) ($matchConfig?->enforce_round_count ?? false),
            'round_count' => $matchConfig?->round_count ?? null,
            'draft_points' => $draftConfig?->draft_points ?? 80,
            'minimum_drafts' => $draftConfig?->minimum_drafts ?? 1,
            'ban_enabled' => (bool) ($draftConfig?->ban_enabled ?? false),
            'bans_per_user' => $draftConfig?->bans_per_user ?? null,
            'minimum_cost_to_ban' => $draftConfig?->minimum_cost_to_ban ?? null,
            'logo' => $league?->logo ?? null,
            'pokemon_generation' => $league?->pokemon_generation ?? (int) config('pokemon.default_league_generation'),
            'pokemon_game' => $league?->pokemon_game instanceof PokemonGame
                ? $league->pokemon_game->value
                : (string) config('pokemon.default_league_game'),
            'pokemon_game_options' => collect(PokemonGame::cases())
                ->filter(fn (PokemonGame $game) => $game->isAvailable())
                ->map(fn (PokemonGame $game) => [
                    'value' => $game->value,
                    'label' => $game->label(),
                    'generation' => $game->generation(),
                ])->values()->all(),
            'pokemon_generation_options' => collect(range(1, 9))
                ->filter(fn (int $generation) => count(PokemonGame::forGeneration($generation)) > 0)
                ->values()
                ->all(),
            'discord_webhook_url' => $league?->discord_webhook_url ?? '',
            'discord_replay_webhook_url' => $league?->discord_replay_webhook_url ?? '',
            'require_showdown_username' => (bool) ($league?->require_showdown_username ?? false),
            'playoff_format' => $playoff?->format?->value ?? PlayoffFormat::SingleElimination->value,
            'playoff_bracket_size' => $playoff?->bracket_size ?? 4,
            'playoff_format_options' => collect(PlayoffFormat::cases())->map(fn (PlayoffFormat $f) => [
                'value' => $f->value,
                'label' => match ($f) {
                    PlayoffFormat::SingleElimination => 'Single elimination',
                    PlayoffFormat::DoubleElimination => 'Double elimination',
                },
                'bracket_generation_supported' => $f === PlayoffFormat::SingleElimination,
            ])->values()->all(),
            'playoff_bracket_size_options' => PlayoffBracketService::allowedBracketSizes(),
            'playoffs_enabled' => (bool) ($league?->playoffs_enabled ?? true),
            'free_trade_window_hours' => $league?->free_trade_window_hours ?? 24,
        ];
    }

    public function createOrEditLeague(Request $request): int
    {
        $action = new CreateEditLeagueAction;
        $isEditingExistingLeague = $request->integer('league_id') > 0;
        $league = $isEditingExistingLeague ? $action->edit($request) : $action->create($request);

        return (int) $league->id;
    }

    public function dashboardPageProps(int $leagueId, int $userId, ?int $requestedTeamId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $data = ($this->leagueDetailLayoutDataAction)($league);

        $userTeamBasic = $data['teams']->first(fn ($t) => $t->user_id === $userId);

        $selectedTeamId = null;
        if ($requestedTeamId !== null && $data['teams']->firstWhere('id', $requestedTeamId)) {
            $selectedTeamId = $requestedTeamId;
        }

        $selectedTeamId = $selectedTeamId ?? $userTeamBasic?->id ?? $data['teams']->first()?->id;

        $selectedTeam = $selectedTeamId !== null
            ? ($this->readTeamAction)(['command' => 'team', 'team_id' => $selectedTeamId])
            : null;

        $userTradesTeam = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->whereNull('dropped_at')
            ->with('pokemon:id,drafted_by,name,cost,pokedex_id', 'pokemon.pokemon:id,name,sprite_url')
            ->first();

        $leagueTeamsForTrades = Team::query()
            ->where('league_id', $leagueId)
            ->notDropped()
            ->when($userTradesTeam, fn ($q) => $q->where('id', '!=', $userTradesTeam->id))
            ->with('pokemon.pokemon:id,name,sprite_url', 'user:id,name')
            ->get()
            ->map(function (Team $team): Team {
                $team->coach = $team->user?->name ?? '—';
                unset($team->user);

                return $team;
            });

        $userTrades = $userTradesTeam
            ? ($this->readTradesAction)(['league_id' => $leagueId, 'team_id' => $userTradesTeam->id])
            : collect();

        $freeAgencyPool = ($this->readLeaguePokemonAction)(['league_id' => $leagueId, 'command' => 'available']);

        $nextSet = $selectedTeamId !== null
            ? Set::query()
                ->where('league_id', $leagueId)
                ->where(function ($q) use ($selectedTeamId): void {
                    $q->where('team1_id', $selectedTeamId)
                        ->orWhere('team2_id', $selectedTeamId);
                })
                ->whereNull('winner_id')
                ->where('is_bye', false)
                ->with(['team1', 'team2'])
                ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('scheduled_at')
                ->orderBy('round')
                ->first()
            : null;

        $isUserInNextSet = $nextSet !== null
            && $userTradesTeam !== null
            && ($nextSet->team1_id === $userTradesTeam->id || $nextSet->team2_id === $userTradesTeam->id);

        $nextSetPendingScheduleRequest = ($nextSet && $isUserInNextSet)
            ? MatchScheduleRequest::query()
                ->where('set_id', $nextSet->id)
                ->where('status', ScheduleRequestStatus::Pending->value)
                ->latest()
                ->first()
            : null;

        $nextSetData = $nextSet ? [
            'id' => $nextSet->id,
            'round' => $nextSet->round,
            'scheduled_at' => $nextSet->scheduled_at?->toIso8601String(),
            'opponent_name' => $nextSet->team1_id === $selectedTeamId
                ? ($nextSet->team2?->name ?? '—')
                : ($nextSet->team1?->name ?? '—'),
            'unread_message_count' => $isUserInNextSet
                ? MatchMessage::query()
                    ->where('set_id', $nextSet->id)
                    ->where('user_id', '!=', $userId)
                    ->where('is_read', false)
                    ->count()
                : 0,
            'pending_schedule_request' => $nextSetPendingScheduleRequest ? [
                'id' => $nextSetPendingScheduleRequest->id,
                'proposed_at' => $nextSetPendingScheduleRequest->proposed_at?->toISOString(),
                'is_mine' => $nextSetPendingScheduleRequest->proposed_by_user_id === $userId,
            ] : null,
        ] : null;

        $leagueTransactions = Trade::query()
            ->where('league_id', $leagueId)
            ->where('status', 'accepted')
            ->with([
                'requestingTeam:id,name,user_id',
                'targetTeam:id,name,user_id',
                'offeredPokemon.leaguePokemon.pokemon:id,name,sprite_url',
                'requestedPokemon.leaguePokemon.pokemon:id,name,sprite_url',
            ])
            ->latest()
            ->take(80)
            ->get();

        return [
            ...$data,
            'section' => 'dashboard',
            'selected_team' => $selectedTeam,
            'selected_team_id' => $selectedTeamId,
            'userTradesTeam' => $userTradesTeam,
            'leagueTeamsForTrades' => $leagueTeamsForTrades,
            'trades' => $userTrades,
            'freeAgencyPool' => $freeAgencyPool,
            'freeTradeWindowEndsAt' => $league->freeTradeWindowEndsAt()?->toIso8601String(),
            'nextSet' => $nextSetData,
            'leagueTransactions' => $leagueTransactions,
            'team_sets_by_round' => $selectedTeamId !== null
                ? ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'team_by_round', 'team_id' => $selectedTeamId])
                : [],
        ];
    }

    public function teamsPageProps(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $logoAction = new LogoToUrlAction;

        $rosterTeams = Team::query()
            ->where('league_id', $leagueId)
            ->notDropped()
            ->with([
                'user:id,name,discord_avatar_url',
                'pokemon.pokemon:id,name,sprite_url,type1,type2',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'league_id' => $team->league_id,
                'name' => $team->name,
                'logo' => $team->logo !== null && trim($team->logo) !== ''
                    ? $logoAction->logoToUrl($team->logo)
                    : null,
                'coach' => $team->user?->name ?? '—',
                'coach_discord_avatar_url' => $team->user?->discord_avatar_url,
                'pokemon' => $team->pokemon
                    ->map(fn ($tp) => [
                        'id' => $tp->id,
                        'name' => $tp->name,
                        'sprite_url' => $tp->pokemon?->sprite_url,
                        'type1' => $tp->pokemon?->type1,
                    ])
                    ->all(),
            ])
            ->all();

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'rosters',
            'rosterTeams' => $rosterTeams,
        ];
    }

    public function schedulePageProps(int $leagueId, int $userId, ?int $requestedTeamId, string $requestedView): array
    {
        $league = League::query()->findOrFail($leagueId);
        $user = User::query()->findOrFail($userId);

        $userTeam = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->select('id')
            ->first();

        $matchesFilterTeamId = null;
        if ($requestedTeamId !== null && Team::query()->where('league_id', $leagueId)->whereKey($requestedTeamId)->exists()) {
            $matchesFilterTeamId = $requestedTeamId;
        }

        $teamIdForNextSet = $matchesFilterTeamId ?? $userTeam?->id;

        $data = ($this->leagueDetailLayoutDataAction)($league);

        $playoff = $league->playoff()->firstOrCreate(
            ['league_id' => $league->id],
            [
                'format' => PlayoffFormat::SingleElimination,
                'bracket_size' => 4,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]
        );

        if ($playoff->status === PlayoffStatus::Draft && $playoff->seed_order === null) {
            $playoff->seed_order = $this->playoffBracketService->suggestedSeedTeams($league)->pluck('id')->all();
            $playoff->save();
        }

        $playoff->load(['matches.team1', 'matches.team2']);

        $teamsById = $data['teams']->keyBy('id');
        $bracketLayout = $this->playoffBracketLayoutService->build($playoff, $teamsById);

        $canAdjustPlayoff = $user->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Draft;

        $canRecordPlayoffResults = $user->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Active;

        $league->loadMissing('matchConfig');

        $hasActivePlayoffs = $playoff->status === PlayoffStatus::Active;

        $scheduleView = in_array($requestedView, ['matches', 'standings', 'playoffs']) ? $requestedView : 'matches';

        return [
            ...$data,
            'section' => 'schedule',
            'played_sets' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'played']),
            'upcoming_sets' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'upcoming']),
            'team_next' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'team_next', 'team_id' => $teamIdForNextSet]),
            'matches_filter_team_id' => $matchesFilterTeamId,
            'standings' => ($this->readTeamAction)(['league_id' => $leagueId, 'command' => 'standings']),
            'playoff' => $this->playoffsOperations->playoffPayloadWithPokepaste(
                (int) $playoff->id,
                $leagueId,
                $userId,
            ),
            'bracketLayout' => $bracketLayout,
            'canAdjustPlayoff' => $canAdjustPlayoff,
            'canRecordPlayoffResults' => $canRecordPlayoffResults,
            'allowedBracketSizes' => PlayoffBracketService::allowedBracketSizes(),
            'doubleEliminationSupported' => false,
            'hasActivePlayoffs' => $hasActivePlayoffs,
            'scheduleView' => $scheduleView,
        ];
    }

    public function matchesPageProps(int $leagueId, int $userId, ?int $requestedTeamId): array
    {
        $userTeam = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->select('id')
            ->first();

        $matchesFilterTeamId = null;
        if ($requestedTeamId !== null && Team::query()->where('league_id', $leagueId)->whereKey($requestedTeamId)->exists()) {
            $matchesFilterTeamId = $requestedTeamId;
        }

        $teamIdForNextSet = $matchesFilterTeamId ?? $userTeam?->id;
        $league = League::query()->findOrFail($leagueId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'matches',
            'played_sets' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'played']),
            'upcoming_sets' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'upcoming']),
            'team_next' => ($this->showSetsAction)(['league_id' => $leagueId, 'command' => 'team_next', 'team_id' => $teamIdForNextSet]),
            'matches_filter_team_id' => $matchesFilterTeamId,
        ];
    }

    public function standingsPageProps(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'standings',
            'standings' => ($this->readTeamAction)(['league_id' => $leagueId, 'command' => 'standings']),
        ];
    }

    public function statsPageProps(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'stats',
        ];
    }

    public function statsKillLeadersLoader(int $leagueId): Closure
    {
        return function () use ($leagueId) {
            $league = League::query()->findOrFail($leagueId);

            return ($this->readLeagueKillLeadersAction)($league);
        };
    }

    public function tradesPageProps(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
            'section' => 'trades',
        ];
    }

    public function draftPageProps(int $leagueId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $data = ($this->leagueDetailLayoutDataAction)($league);
        $draft = $data['draft'];
        $draftRecapTeams = null;
        $draftRecapBans = null;

        if ($draft !== null && (int) $draft->status === 0) {
            $draftRecapTeams = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'teams'])
                ->sortBy('name')
                ->values();
            $draftRecapBans = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'allbans']);
        }

        return [
            ...$data,
            'section' => 'draft',
            'draft_recap_teams' => $draftRecapTeams,
            'draft_recap_bans' => $draftRecapBans,
        ];
    }

    public function playoffsPageProps(int $leagueId, ?int $userId): array
    {
        $league = League::query()->findOrFail($leagueId);
        $user = $userId !== null ? User::query()->find($userId) : null;
        $data = ($this->leagueDetailLayoutDataAction)($league);

        $playoff = $league->playoff()->firstOrCreate(
            ['league_id' => $league->id],
            [
                'format' => PlayoffFormat::SingleElimination,
                'bracket_size' => 4,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]
        );

        if ($playoff->status === PlayoffStatus::Draft && $playoff->seed_order === null) {
            $playoff->seed_order = $this->playoffBracketService->suggestedSeedTeams($league)->pluck('id')->all();
            $playoff->save();
        }

        $playoff->load(['matches.team1', 'matches.team2']);

        $teamsById = $data['teams']->keyBy('id');
        $bracketLayout = $this->playoffBracketLayoutService->build($playoff, $teamsById);

        $canAdjustPlayoff = $user !== null
            && $user->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Draft;

        $canRecordPlayoffResults = $user !== null
            && $user->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Active;

        $league->loadMissing('matchConfig');

        return [
            ...$data,
            'section' => 'playoffs',
            'playoff' => $this->playoffsOperations->playoffPayloadWithPokepaste(
                (int) $playoff->id,
                $leagueId,
                $userId,
            ),
            'bracketLayout' => $bracketLayout,
            'canAdjustPlayoff' => $canAdjustPlayoff,
            'canRecordPlayoffResults' => $canRecordPlayoffResults,
            'allowedBracketSizes' => PlayoffBracketService::allowedBracketSizes(),
            'doubleEliminationSupported' => false,
        ];
    }

    public function assertAdmin(int $leagueId, int $userId): void
    {
        $this->leagueForAdmin($leagueId, $userId);
    }

    public function adminMatchConfigPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
        ];
    }

    public function adminDiscordPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
        ];
    }

    public function adminTradesPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
        ];
    }

    public function adminWinnerPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);
        $data = ($this->leagueDetailLayoutDataAction)($league);

        $groupedStandings = ($this->readTeamAction)(['league_id' => $leagueId, 'command' => 'standings']);
        $flatStandings = $groupedStandings->flatten(1)->sortByDesc('victory_points')->values();

        return [
            ...$data,
            'standings' => $flatStandings,
        ];
    }

    public function adminReopenMatchPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        return [
            ...($this->leagueDetailLayoutDataAction)($league),
        ];
    }

    public function adminDraftPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);
        $data = ($this->leagueDetailLayoutDataAction)($league);

        DraftConfig::firstOrCreate(
            ['league_id' => $league->id],
            [
                'draft_points' => 80,
                'minimum_drafts' => 0,
                'ban_enabled' => false,
                'bans_per_user' => null,
                'minimum_cost_to_ban' => null,
            ]
        );

        $league->refresh();
        $league->load('draftConfig');

        $teamsForPicks = $data['teams']->sortBy('pick_position')->values()->all();
        $draftExists = Draft::where('league_id', $league->id)->exists();
        $canReorderPicks = ! $draftExists;
        $activeTeamCount = Team::query()
            ->where('league_id', $league->id)
            ->whereNull('dropped_at')
            ->count();
        $canStartDraft = ! $draftExists && $activeTeamCount > 0;

        return [
            ...$data,
            'draftConfig' => $league->draftConfig,
            'teams' => $teamsForPicks,
            'canReorderPicks' => $canReorderPicks,
            'canStartDraft' => $canStartDraft,
            'draftExists' => $draftExists,
            'activeTeamCount' => $activeTeamCount,
        ];
    }

    public function adminLeagueAdminsPageProps(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);
        $user = User::query()->findOrFail($userId);
        $data = ($this->leagueDetailLayoutDataAction)($league);
        $isLeagueOwner = $userId === (int) $league->league_owner;

        return [
            ...$data,
            'isLeagueOwner' => $isLeagueOwner,
            'isLeagueAdmin' => $user->can('admin', $league),
        ];
    }

    public function updateDraftConfig(int $leagueId, Request $request): void
    {
        $config = DraftConfig::firstOrCreate(
            ['league_id' => $leagueId],
            [
                'draft_points' => 80,
                'minimum_drafts' => 0,
                'ban_enabled' => false,
                'bans_per_user' => null,
                'minimum_cost_to_ban' => null,
            ]
        );

        $validated = $request->validated();
        $banEnabled = $request->boolean('ban_enabled');
        $pickTimerEnabled = $request->boolean('pick_timer_enabled');
        $quietHoursEnabled = $request->boolean('quiet_hours_enabled');

        $config->draft_date = $validated['draft_date'] ?? null;
        $config->draft_start_at = $validated['draft_start_at'] ?? null;
        $config->draft_points = (int) $validated['draft_points'];
        $config->minimum_drafts = (int) $validated['minimum_drafts'];
        $config->ban_enabled = $banEnabled;
        $config->bans_per_user = $banEnabled ? (int) $validated['bans_per_user'] : null;
        $config->minimum_cost_to_ban = $banEnabled ? (int) $validated['minimum_cost_to_ban'] : null;
        $config->pick_timer_enabled = $pickTimerEnabled;
        $config->pick_timer_seconds = $pickTimerEnabled ? (int) $validated['pick_timer_seconds'] : null;
        $config->quiet_hours_enabled = $quietHoursEnabled;
        $config->quiet_hours_start = $quietHoursEnabled ? ($validated['quiet_hours_start'] ?? null) : null;
        $config->quiet_hours_end = $quietHoursEnabled ? ($validated['quiet_hours_end'] ?? null) : null;
        $config->quiet_hours_timezone = $quietHoursEnabled ? ($validated['quiet_hours_timezone'] ?? null) : null;
        $config->save();
    }

    public function updateDraftPickOrder(int $leagueId, array $teamIds): void
    {
        DB::transaction(function () use ($leagueId, $teamIds): void {
            foreach ($teamIds as $index => $teamId) {
                Team::query()
                    ->where('league_id', $leagueId)
                    ->where('id', $teamId)
                    ->update(['pick_position' => $index + 1]);
            }
        });
    }

    public function updateTeamAdmin(int $leagueId, int $teamId, bool $adminFlag): void
    {
        $team = Team::query()
            ->where('league_id', $leagueId)
            ->where('id', $teamId)
            ->firstOrFail();

        $team->admin_flag = $adminFlag ? 1 : 0;
        $team->save();
    }

    public function dropTeamFromLeague(int $leagueId, int $teamId): void
    {
        $team = Team::query()
            ->where('league_id', $leagueId)
            ->where('id', $teamId)
            ->whereNull('dropped_at')
            ->firstOrFail();

        ($this->dropTeamFromLeagueService)($team);
    }

    public function reopenMatchSet(int $leagueId, int $setId): void
    {
        ($this->createEditSetsAction)([
            'command' => 'reopen',
            'set_id' => $setId,
        ]);
    }

    public function updateDiscordWebhook(int $leagueId, Request $request): void
    {
        $request->validate([
            'discord_webhook_url' => 'nullable|url|max:500',
            'discord_replay_webhook_url' => 'nullable|url|max:500',
        ]);

        $league = League::query()->findOrFail($leagueId);
        $league->discord_webhook_url = $request->discord_webhook_url ?: null;
        $league->discord_replay_webhook_url = $request->discord_replay_webhook_url ?: null;
        $league->save();
    }

    public function updateTradeDeadline(int $leagueId, int $userId, Request $request): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        $request->validate([
            'trade_deadline_at' => ['nullable', 'date'],
        ]);

        $deadline = $request->input('trade_deadline_at')
            ? Carbon::parse($request->input('trade_deadline_at'))
            : null;

        $league->trade_deadline_at = $deadline;
        $league->save();

        if ($deadline !== null && ! $deadline->isPast()) {
            EnforceTradeDeadlineJob::dispatch($league->id, $deadline)->delay($deadline);
        }

        return [];
    }

    public function updateFreeTradeWindow(int $leagueId, int $userId, Request $request): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        $request->validate([
            'free_trade_window_hours' => ['required', 'integer', 'min:0', 'max:8760'],
        ]);

        $league->free_trade_window_hours = $request->integer('free_trade_window_hours');
        $league->save();

        return [];
    }

    public function cancelLeague(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        if ($league->status === LeagueStatus::Completed) {
            return ['errors' => ['league' => 'A completed league cannot be cancelled.']];
        }

        $league->status = LeagueStatus::Cancelled;
        $league->save();

        return ['redirect' => route('leagues.index')];
    }

    public function startRegularSeason(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        if ($league->status !== LeagueStatus::Staging) {
            return ['errors' => ['league' => 'The league must be in the Staging phase to start the regular season.']];
        }

        if (! ($this->startRegularSeasonAction)($league)) {
            return ['errors' => ['league' => 'The draft must be completed before the regular season can start.']];
        }

        return [];
    }

    public function startPlayoffs(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        if ($league->status !== LeagueStatus::RegularSeason) {
            return ['errors' => ['league' => 'The league must be in the Regular Season phase to start playoffs.']];
        }

        if (! $league->playoffs_enabled) {
            return ['errors' => ['league' => 'Playoffs are not enabled for this league.']];
        }

        $playoff = $league->playoff()->firstOrCreate(
            ['league_id' => $league->id],
            [
                'format' => PlayoffFormat::SingleElimination,
                'bracket_size' => 4,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]
        );

        if ($playoff->status !== PlayoffStatus::Draft) {
            return ['errors' => ['league' => 'The playoff bracket must be in draft status before activating playoffs.']];
        }

        if (! $playoff->matches()->exists()) {
            return ['errors' => ['league' => 'Generate the playoff bracket before starting playoffs.']];
        }

        $playoff->status = PlayoffStatus::Active;
        $playoff->save();

        $league->status = LeagueStatus::Playoffs;
        $league->save();

        return [];
    }

    public function finalizeRegularSeason(int $leagueId, int $userId): array
    {
        $league = $this->leagueForAdmin($leagueId, $userId);

        if ($league->status !== LeagueStatus::RegularSeason) {
            return ['errors' => ['league' => 'The league must be in the Regular Season phase to finalize.']];
        }

        if ($league->playoffs_enabled) {
            return ['errors' => ['league' => 'This league has playoffs enabled. Use "Start Playoffs" instead.']];
        }

        $groupedStandings = ($this->readTeamAction)(['league_id' => $leagueId, 'command' => 'standings']);
        $topTeam = $groupedStandings->flatten(1)->sortByDesc('victory_points')->first();

        if ($topTeam === null) {
            return ['errors' => ['league' => 'No teams found in standings.']];
        }

        $league->winner = $topTeam->user_id;
        $league->status = LeagueStatus::Completed;
        $league->save();

        return ['success' => 'League finalized. '.$topTeam->name.' is the champion!'];
    }

    private function leagueForAdmin(int $leagueId, int $userId): League
    {
        $league = League::query()->findOrFail($leagueId);
        $user = User::query()->findOrFail($userId);
        abort_unless($user->can('admin', $league), 403);

        return $league;
    }
}
