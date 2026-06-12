<?php

namespace App\Modules\V2\League\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\UpdateDraftConfigRequest;
use App\Http\Requests\Draft\UpdateDraftPickOrderRequest;
use App\Http\Requests\League\DropTeamFromLeagueRequest;
use App\Http\Requests\League\UpdateTeamAdminRequest;
use App\Http\Requests\Match\ReopenMatchSetRequest;
use App\Kernel\Contracts\LeagueOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function index(LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueIndex', $leagueOperations->indexPageProps());
    }

    public function show(int $league): RedirectResponse
    {
        return redirect()->route('leagues.dashboard', ['league' => $league]);
    }

    public function showDashboard(Request $request, int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/LeagueDashboard',
            $leagueOperations->dashboardPageProps(
                $league,
                (int) $userId,
                $request->filled('team') ? (int) $request->query('team') : null,
            ),
        );
    }

    public function showTeams(int $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailTeams', $leagueOperations->teamsPageProps($league));
    }

    public function showSchedule(Request $request, int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/LeagueDetailSchedule',
            $leagueOperations->schedulePageProps(
                $league,
                (int) $userId,
                $request->filled('team') ? (int) $request->query('team') : null,
                (string) $request->query('view', 'matches'),
            ),
        );
    }

    public function showStats(int $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailStats', [
            ...$leagueOperations->statsPageProps($league),
            'killLeaders' => Inertia::defer($leagueOperations->statsKillLeadersLoader($league)),
        ]);
    }

    public function showDraft(int $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailDraft', $leagueOperations->draftPageProps($league));
    }

    public function createEditShow(Request $request, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueCreateEdit', $leagueOperations->createEditPageProps($request));
    }

    public function create(Request $request, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueId = $leagueOperations->createOrEditLeague($request);

        return redirect()->route('leagues.dashboard', ['league' => $leagueId]);
    }

    public function showAdmin(int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->assertAdmin($league, (int) $userId);

        return redirect()->route('leagues.admin.league-admins', ['league' => $league]);
    }

    public function showAdminMatchConfig(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/MatchConfig',
            $leagueOperations->adminMatchConfigPageProps($league, (int) $userId),
        );
    }

    public function showAdminDiscord(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Discord',
            $leagueOperations->adminDiscordPageProps($league, (int) $userId),
        );
    }

    public function showAdminTrades(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Trades',
            $leagueOperations->adminTradesPageProps($league, (int) $userId),
        );
    }

    public function showAdminWinner(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Winner',
            $leagueOperations->adminWinnerPageProps($league, (int) $userId),
        );
    }

    public function showAdminReopenMatch(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/ReopenMatch',
            $leagueOperations->adminReopenMatchPageProps($league, (int) $userId),
        );
    }

    public function showAdminDraft(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/DraftSettings',
            $leagueOperations->adminDraftPageProps($league, (int) $userId),
        );
    }

    public function showAdminLeagueAdmins(int $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/LeagueAdmins',
            $leagueOperations->adminLeagueAdminsPageProps($league, (int) $userId),
        );
    }

    public function updateDraftConfig(UpdateDraftConfigRequest $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateDraftConfig($league, $request);

        return back()->with('success', 'Draft configuration saved.');
    }

    public function updateDraftPickOrder(UpdateDraftPickOrderRequest $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(fn ($id) => (int) $id, $request->validated('team_ids'));
        $leagueOperations->updateDraftPickOrder($league, $ids);

        return back()->with('success', 'Pick order saved.');
    }

    public function updateTeamAdmin(UpdateTeamAdminRequest $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateTeamAdmin(
            $league,
            $request->integer('team_id'),
            $request->boolean('admin_flag'),
        );

        return back()->with('success', 'Admin access updated.');
    }

    public function dropTeamFromLeague(DropTeamFromLeagueRequest $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->dropTeamFromLeague($league, $request->integer('team_id'));

        return back()->with('success', 'Team removed from the league. Their Pokémon returned to the pool; matches were converted to byes where applicable.');
    }

    public function reopenMatchSet(ReopenMatchSetRequest $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->reopenMatchSet($league, $request->integer('set_id'));

        return redirect()
            ->route('leagues.admin.reopen-match', ['league' => $league])
            ->with('success', 'Match reopened. Standings were updated; coaches can submit a new result.');
    }

    public function updateDiscordWebhook(Request $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateDiscordWebhook($league, $request);

        return back();
    }

    public function updateTradeDeadline(Request $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->updateTradeDeadline($league, (int) $userId, $request);

        $deadline = $request->input('trade_deadline_at');

        return back()->with('success', $deadline ? 'Trade deadline saved.' : 'Trade deadline cleared.');
    }

    public function updateFreeTradeWindow(Request $request, int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->updateFreeTradeWindow($league, (int) $userId, $request);

        return back()->with('success', 'Free trade window updated.');
    }

    public function cancelLeague(int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->cancelLeague($league, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return redirect($result['redirect'] ?? route('leagues.index'))
            ->with('success', 'League has been cancelled.');
    }

    public function startRegularSeason(int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->startRegularSeason($league, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Regular season has started.');
    }

    public function startPlayoffs(int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->startPlayoffs($league, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoffs have started. The bracket is now live.');
    }

    public function finalizeRegularSeason(int $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->finalizeRegularSeason($league, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', $result['success'] ?? 'League finalized.');
    }
}
