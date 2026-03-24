<?php

namespace App\Modules\League\Controllers;

use App\Enums\PokemonGame;
use App\Http\Controllers\Controller;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeagueAction;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function showMatches(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ShowSetsAction $showSetsAction)
    {
        $user_team = Team::where('user_id', Auth::user()->id)->where('league_id', $league->id)->select('id')->first();

        return Inertia::render('league/LeagueDetailMatches', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'matches',
            'played_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'played']),
            'upcoming_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'upcoming']),
            'team_next' => $showSetsAction(['league_id' => $league->id, 'command' => 'team_next', 'team_id' => $user_team?->id]),
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

    public function showDraft(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailDraft', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'draft',
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

    public function create(Request $request)
    {
        $action = new CreateEditLeagueAction;
        $league = $request->filled('league_id') ? $action->edit($request) : $action->create($request);

        return redirect()->route('leagues.matches', ['league' => $league->id]);
    }

    public function setWinner(Request $request, League $league)
    {
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

        $draftConfig = $league?->draftConfig;
        $matchConfig = $league?->matchConfig;

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
        ]);
    }
}
