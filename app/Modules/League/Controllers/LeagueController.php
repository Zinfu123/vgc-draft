<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\UpdateDraftConfigRequest;
use App\Http\Requests\Draft\UpdateDraftPickOrderRequest;
use App\Http\Requests\League\DropTeamFromLeagueRequest;
use App\Http\Requests\League\UpdateTeamAdminRequest;
use App\Http\Requests\Match\ReopenMatchSetRequest;
use App\Kernel\Contracts\LeagueOperations;
use App\Modules\League\Models\League;
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

    public function show(League $league): RedirectResponse
    {
        return redirect()->route('leagues.dashboard', ['league' => $league->id]);
    }

    public function showDashboard(Request $request, League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/LeagueDashboard',
            $leagueOperations->dashboardPageProps(
                (int) $league->id,
                (int) $userId,
                $request->filled('team') ? (int) $request->query('team') : null,
            ),
        );
    }

    public function showTeams(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailTeams', $leagueOperations->teamsPageProps((int) $league->id));
    }

    public function showSchedule(Request $request, League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/LeagueDetailSchedule',
            $leagueOperations->schedulePageProps(
                (int) $league->id,
                (int) $userId,
                $request->filled('team') ? (int) $request->query('team') : null,
                (string) $request->query('view', 'matches'),
            ),
        );
    }

    public function showMatches(Request $request, League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/LeagueDetailMatches',
            $leagueOperations->matchesPageProps(
                (int) $league->id,
                (int) $userId,
                $request->filled('team') ? (int) $request->query('team') : null,
            ),
        );
    }

    public function showStandings(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailStandings', $leagueOperations->standingsPageProps((int) $league->id));
    }

    public function showStats(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailStats', [
            ...$leagueOperations->statsPageProps((int) $league->id),
            'killLeaders' => Inertia::defer($leagueOperations->statsKillLeadersLoader((int) $league->id)),
        ]);
    }

    public function showTrades(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailTrades', $leagueOperations->tradesPageProps((int) $league->id));
    }

    public function showDraft(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueDetailDraft', $leagueOperations->draftPageProps((int) $league->id));
    }

    public function showPlayoffs(League $league, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render(
            'league/LeagueDetailPlayoffs',
            $leagueOperations->playoffsPageProps((int) $league->id, Auth::id() !== null ? (int) Auth::id() : null),
        );
    }

    public function showAdmin(League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->assertAdmin((int) $league->id, (int) $userId);

        return redirect()->route('leagues.admin.league-admins', ['league' => $league->id]);
    }

    public function showAdminMatchConfig(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/MatchConfig',
            $leagueOperations->adminMatchConfigPageProps((int) $league->id, (int) $userId),
        );
    }

    public function showAdminDiscord(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Discord',
            $leagueOperations->adminDiscordPageProps((int) $league->id, (int) $userId),
        );
    }

    public function showAdminTrades(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Trades',
            $leagueOperations->adminTradesPageProps((int) $league->id, (int) $userId),
        );
    }

    public function showAdminWinner(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/Winner',
            $leagueOperations->adminWinnerPageProps((int) $league->id, (int) $userId),
        );
    }

    public function showAdminReopenMatch(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/ReopenMatch',
            $leagueOperations->adminReopenMatchPageProps((int) $league->id, (int) $userId),
        );
    }

    public function showAdminDraft(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/DraftSettings',
            $leagueOperations->adminDraftPageProps((int) $league->id, (int) $userId),
        );
    }

    public function updateDraftConfig(UpdateDraftConfigRequest $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateDraftConfig((int) $league->id, $request);

        return back()->with('success', 'Draft configuration saved.');
    }

    public function updateDraftPickOrder(UpdateDraftPickOrderRequest $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(fn ($id) => (int) $id, $request->validated('team_ids'));
        $leagueOperations->updateDraftPickOrder((int) $league->id, $ids);

        return back()->with('success', 'Pick order saved.');
    }

    public function showAdminLeagueAdmins(League $league, LeagueOperations $leagueOperations): Response
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        return Inertia::render(
            'league/admin/LeagueAdmins',
            $leagueOperations->adminLeagueAdminsPageProps((int) $league->id, (int) $userId),
        );
    }

    public function updateTeamAdmin(UpdateTeamAdminRequest $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateTeamAdmin(
            (int) $league->id,
            $request->integer('team_id'),
            $request->boolean('admin_flag'),
        );

        return back()->with('success', 'Admin access updated.');
    }

    public function dropTeamFromLeague(DropTeamFromLeagueRequest $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->dropTeamFromLeague((int) $league->id, $request->integer('team_id'));

        return back()->with('success', 'Team removed from the league. Their Pokémon returned to the pool; matches were converted to byes where applicable.');
    }

    public function reopenMatchSet(ReopenMatchSetRequest $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->reopenMatchSet((int) $league->id, $request->integer('set_id'));

        return redirect()
            ->route('leagues.admin.reopen-match', ['league' => $league->id])
            ->with('success', 'Match reopened. Standings were updated; coaches can submit a new result.');
    }

    public function create(Request $request, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueId = $leagueOperations->createOrEditLeague($request);

        return redirect()->route('leagues.dashboard', ['league' => $leagueId]);
    }

    public function updateDiscordWebhook(Request $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $leagueOperations->updateDiscordWebhook((int) $league->id, $request);

        return back();
    }

    public function createEditShow(Request $request, LeagueOperations $leagueOperations): Response
    {
        return Inertia::render('league/LeagueCreateEdit', $leagueOperations->createEditPageProps($request));
    }

    public function updateTradeDeadline(Request $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->updateTradeDeadline((int) $league->id, (int) $userId, $request);

        $deadline = $request->input('trade_deadline_at');

        return back()->with('success', $deadline ? 'Trade deadline saved.' : 'Trade deadline cleared.');
    }

    public function updateFreeTradeWindow(Request $request, League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $leagueOperations->updateFreeTradeWindow((int) $league->id, (int) $userId, $request);

        return back()->with('success', 'Free trade window updated.');
    }

    public function cancelLeague(League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->cancelLeague((int) $league->id, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return redirect($result['redirect'] ?? route('leagues.index'))
            ->with('success', 'League has been cancelled.');
    }

    public function startRegularSeason(League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->startRegularSeason((int) $league->id, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Regular season has started.');
    }

    public function startPlayoffs(League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->startPlayoffs((int) $league->id, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', 'Playoffs have started. The bracket is now live.');
    }

    public function finalizeRegularSeason(League $league, LeagueOperations $leagueOperations): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        $result = $leagueOperations->finalizeRegularSeason((int) $league->id, (int) $userId);

        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }

        return back()->with('success', $result['success'] ?? 'League finalized.');
    }
}
