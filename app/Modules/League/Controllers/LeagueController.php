<?php

namespace App\Modules\League\Controllers;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Enums\PokemonGame;
use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\UpdateDraftConfigRequest;
use App\Http\Requests\Draft\UpdateDraftPickOrderRequest;
use App\Http\Requests\League\DropTeamFromLeagueRequest;
use App\Http\Requests\League\UpdateTeamAdminRequest;
use App\Http\Requests\Match\ReopenMatchSetRequest;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeagueAction;
use App\Modules\League\Models\League;
use App\Modules\League\Services\DropTeamFromLeagueService;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Playoffs\Controllers\PlayoffController;
use App\Modules\Playoffs\Services\PlayoffBracketLayoutService;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeagueController extends Controller
{
    public function index(ReadLeagueAction $readLeagueAction)
    {
        $currentLeagues = $readLeagueAction(['command' => 'active']);
        $pastLeagues = $readLeagueAction(['command' => 'past']);

        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeagues,
            'pastLeagues' => $pastLeagues,
        ]);
    }

    public function show(League $league)
    {
        return redirect()->route('leagues.matches', ['league' => $league->id]);
    }

    public function showTeams(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailTeams', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'teams',
        ]);
    }

    public function showMatches(Request $request, League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ShowSetsAction $showSetsAction)
    {
        $user_team = Team::where('user_id', Auth::user()->id)->where('league_id', $league->id)->select('id')->first();

        $matchesFilterTeamId = null;
        if ($request->filled('team')) {
            $candidate = (int) $request->query('team');
            if (Team::query()->where('league_id', $league->id)->whereKey($candidate)->exists()) {
                $matchesFilterTeamId = $candidate;
            }
        }

        $teamIdForNextSet = $matchesFilterTeamId ?? $user_team?->id;

        return Inertia::render('league/LeagueDetailMatches', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'matches',
            'played_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'played']),
            'upcoming_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'upcoming']),
            'team_next' => $showSetsAction(['league_id' => $league->id, 'command' => 'team_next', 'team_id' => $teamIdForNextSet]),
            'matches_filter_team_id' => $matchesFilterTeamId,
        ]);
    }

    public function showStandings(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ReadTeamAction $readTeamAction)
    {
        return Inertia::render('league/LeagueDetailStandings', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'standings',
            'standings' => $readTeamAction(['league_id' => $league->id, 'command' => 'standings']),
        ]);
    }

    public function showTrades(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailTrades', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'trades',
        ]);
    }

    public function showDraft(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ReadCurrentDraftAction $readCurrentDraftAction): \Inertia\Response
    {
        $data = $leagueDetailLayoutDataAction($league);
        $draft = $data['draft'];
        $draftRecapTeams = null;
        $draftRecapBans = null;

        if ($draft !== null && (int) $draft->status === 0) {
            $draftRecapTeams = $readCurrentDraftAction(['league_id' => $league->id, 'command' => 'teams'])
                ->sortBy('name')
                ->values();
            $draftRecapBans = $readCurrentDraftAction(['league_id' => $league->id, 'command' => 'allbans']);
        }

        return Inertia::render('league/LeagueDetailDraft', [
            ...$data,
            'section' => 'draft',
            'draft_recap_teams' => $draftRecapTeams,
            'draft_recap_bans' => $draftRecapBans,
        ]);
    }

    public function showPlayoffs(
        League $league,
        LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction,
        PlayoffBracketService $playoffBracketService,
        PlayoffBracketLayoutService $playoffBracketLayoutService,
        PlayoffController $playoffController,
    ): \Inertia\Response {
        $data = $leagueDetailLayoutDataAction($league);

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
            $playoff->seed_order = $playoffBracketService->suggestedSeedTeams($league)->pluck('id')->all();
            $playoff->save();
        }

        $playoff->load(['matches.team1', 'matches.team2']);

        $teamsById = $data['teams']->keyBy('id');
        $bracketLayout = $playoffBracketLayoutService->build($playoff, $teamsById);

        $canAdjustPlayoff = Auth::user()?->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Draft;

        $canRecordPlayoffResults = Auth::user()?->can('admin', $league) === true
            && $playoff->status === PlayoffStatus::Active;

        $league->loadMissing('matchConfig');

        return Inertia::render('league/LeagueDetailPlayoffs', [
            ...$data,
            'section' => 'playoffs',
            'playoff' => $playoffController->playoffPayloadWithPokepaste($playoff, $league, Auth::user()),
            'bracketLayout' => $bracketLayout,
            'canAdjustPlayoff' => $canAdjustPlayoff,
            'canRecordPlayoffResults' => $canRecordPlayoffResults,
            'allowedBracketSizes' => PlayoffBracketService::allowedBracketSizes(),
            'doubleEliminationSupported' => false,
        ]);
    }

    public function showAdmin(League $league): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('admin', $league);

        return redirect()->route('leagues.admin.match-config', ['league' => $league->id]);
    }

    public function showAdminMatchConfig(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        return Inertia::render('league/admin/MatchConfig', [
            'league' => $data['league'],
            'matchConfig' => $data['matchConfig'],
        ]);
    }

    public function showAdminDiscord(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        return Inertia::render('league/admin/Discord', [
            'league' => $data['league'],
        ]);
    }

    public function showAdminTrades(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        return Inertia::render('league/admin/Trades', [
            'league' => $data['league'],
            'teams' => $data['teams'],
        ]);
    }

    public function showAdminWinner(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        return Inertia::render('league/admin/Winner', [
            'league' => $data['league'],
            'teams' => $data['teams'],
        ]);
    }

    public function showAdminReopenMatch(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        return Inertia::render('league/admin/ReopenMatch', [
            'league' => $data['league'],
        ]);
    }

    public function showAdminDraft(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

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
        $canReorderPicks = ! Draft::where('league_id', $league->id)->exists();

        return Inertia::render('league/admin/DraftSettings', [
            'league' => $data['league'],
            'draftConfig' => $league->draftConfig,
            'teams' => $teamsForPicks,
            'canReorderPicks' => $canReorderPicks,
        ]);
    }

    public function updateDraftConfig(UpdateDraftConfigRequest $request, League $league): RedirectResponse
    {
        $config = DraftConfig::firstOrCreate(
            ['league_id' => $league->id],
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

        $config->draft_date = $validated['draft_date'] ?? null;
        $config->draft_points = (int) $validated['draft_points'];
        $config->minimum_drafts = (int) $validated['minimum_drafts'];
        $config->ban_enabled = $banEnabled;
        $config->bans_per_user = $banEnabled ? (int) $validated['bans_per_user'] : null;
        $config->minimum_cost_to_ban = $banEnabled ? (int) $validated['minimum_cost_to_ban'] : null;
        $config->save();

        return back()->with('success', 'Draft configuration saved.');
    }

    public function updateDraftPickOrder(UpdateDraftPickOrderRequest $request, League $league): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(fn ($id) => (int) $id, $request->validated('team_ids'));

        DB::transaction(function () use ($league, $ids): void {
            foreach ($ids as $index => $teamId) {
                Team::query()
                    ->where('league_id', $league->id)
                    ->where('id', $teamId)
                    ->update(['pick_position' => $index + 1]);
            }
        });

        return back()->with('success', 'Pick order saved.');
    }

    public function showAdminLeagueAdmins(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction): \Inertia\Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);
        $isLeagueOwner = (int) Auth::id() === (int) $league->league_owner;

        return Inertia::render('league/admin/LeagueAdmins', [
            'league' => $data['league'],
            'teams' => $data['teams'],
            'isLeagueOwner' => $isLeagueOwner,
            'isLeagueAdmin' => Auth::user()->can('admin', $league),
        ]);
    }

    public function updateTeamAdmin(UpdateTeamAdminRequest $request, League $league): RedirectResponse
    {
        $team = Team::query()
            ->where('league_id', $league->id)
            ->where('id', $request->integer('team_id'))
            ->firstOrFail();

        $team->admin_flag = $request->boolean('admin_flag') ? 1 : 0;
        $team->save();

        return back()->with('success', 'Admin access updated.');
    }

    public function dropTeamFromLeague(DropTeamFromLeagueRequest $request, League $league, DropTeamFromLeagueService $dropTeamFromLeagueService): RedirectResponse
    {
        $team = Team::query()
            ->where('league_id', $league->id)
            ->where('id', $request->integer('team_id'))
            ->whereNull('dropped_at')
            ->firstOrFail();

        $dropTeamFromLeagueService($team);

        return back()->with('success', 'Team removed from the league. Their Pokémon returned to the pool; matches were converted to byes where applicable.');
    }

    public function reopenMatchSet(ReopenMatchSetRequest $request, League $league, CreateEditSetsAction $createEditSetsAction): \Illuminate\Http\RedirectResponse
    {
        $createEditSetsAction([
            'command' => 'reopen',
            'set_id' => $request->integer('set_id'),
        ]);

        return redirect()
            ->route('leagues.admin.reopen-match', ['league' => $league->id])
            ->with('success', 'Match reopened. Standings were updated; coaches can submit a new result.');
    }

    public function create(Request $request)
    {
        $action = new CreateEditLeagueAction;
        $isEditingExistingLeague = $request->integer('league_id') > 0;
        $league = $isEditingExistingLeague ? $action->edit($request) : $action->create($request);

        return redirect()->route('leagues.matches', ['league' => $league->id]);
    }

    public function setWinner(Request $request, League $league)
    {
        $this->authorize('admin', $league);

        $request->validate([
            'winner_user_id' => 'required|integer|exists:users,id',
        ]);

        $league->winner = $request->winner_user_id;
        $league->status = 0;
        $league->save();

        return back();
    }

    public function updateDiscordWebhook(Request $request, League $league)
    {
        $request->validate([
            'discord_webhook_url' => 'nullable|url|max:500',
            'discord_replay_webhook_url' => 'nullable|url|max:500',
        ]);

        $league->discord_webhook_url = $request->discord_webhook_url ?: null;
        $league->discord_replay_webhook_url = $request->discord_replay_webhook_url ?: null;
        $league->save();

        return back();
    }

    public function createEditShow(Request $request, ReadLeagueAction $readLeagueAction)
    {
        $league = $readLeagueAction(['league_id' => $request->league_id, 'command' => 'league']);
        $league?->loadMissing('playoff');

        $draftConfig = $league?->draftConfig;
        $matchConfig = $league?->matchConfig;
        $playoff = $league?->playoff;

        return Inertia::render('league/LeagueCreateEdit', [
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
            'pokemon_game_options' => collect(PokemonGame::cases())->map(fn (PokemonGame $game) => [
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
        ]);
    }
}
