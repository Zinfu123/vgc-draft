<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('leagues')->name('leagues.')->group(function (): void {
    Route::get('/', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues?{$query}" : '/leagues');
    })->name('index');

    Route::get('/create-edit', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/create-edit?{$query}" : '/leagues/create-edit');
    })->name('create-edit');

    Route::get('/{league}', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}?{$query}" : "/leagues/{$league}");
    })->name('detail');

    Route::get('/{league}/dashboard', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/dashboard?{$query}" : "/leagues/{$league}/dashboard");
    })->name('dashboard');

    Route::get('/{league}/rosters', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/rosters?{$query}" : "/leagues/{$league}/rosters");
    })->name('rosters');

    Route::get('/{league}/schedule', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/schedule?{$query}" : "/leagues/{$league}/schedule");
    })->name('schedule');

    Route::get('/{league}/stats', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/stats?{$query}" : "/leagues/{$league}/stats");
    })->name('stats');

    Route::get('/{league}/draft', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/draft?{$query}" : "/leagues/{$league}/draft");
    })->name('draft');

    Route::post('/', function () {
        return redirect('/leagues', 307);
    })->name('create');

    Route::post('/{league}/cancel', function (int $league) {
        return redirect("/leagues/{$league}/cancel", 307);
    })->name('cancel');

    Route::post('/{league}/start-regular-season', function (int $league) {
        return redirect("/leagues/{$league}/start-regular-season", 307);
    })->name('start-regular-season');

    Route::post('/{league}/start-playoffs', function (int $league) {
        return redirect("/leagues/{$league}/start-playoffs", 307);
    })->name('start-playoffs');

    Route::post('/{league}/finalize', function (int $league) {
        return redirect("/leagues/{$league}/finalize", 307);
    })->name('finalize');

    Route::patch('/{league}/trade-deadline', function (int $league) {
        return redirect("/leagues/{$league}/trade-deadline", 307);
    })->name('trade-deadline.update');

    Route::patch('/{league}/free-trade-window', function (int $league) {
        return redirect("/leagues/{$league}/free-trade-window", 307);
    })->name('free-trade-window.update');

    Route::post('/{league}/discord-webhook', function (int $league) {
        return redirect("/leagues/{$league}/discord-webhook", 307);
    })->name('discord-webhook');

    Route::get('/{league}/admin', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin?{$query}" : "/leagues/{$league}/admin");
    })->name('admin');

    Route::get('/{league}/admin/match-config', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/match-config?{$query}" : "/leagues/{$league}/admin/match-config");
    })->name('admin.match-config');

    Route::get('/{league}/admin/discord', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/discord?{$query}" : "/leagues/{$league}/admin/discord");
    })->name('admin.discord');

    Route::get('/{league}/admin/trades', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/trades?{$query}" : "/leagues/{$league}/admin/trades");
    })->name('admin.trades');

    Route::get('/{league}/admin/winner', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/winner?{$query}" : "/leagues/{$league}/admin/winner");
    })->name('admin.winner');

    Route::get('/{league}/admin/reopen-match', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/reopen-match?{$query}" : "/leagues/{$league}/admin/reopen-match");
    })->name('admin.reopen-match');

    Route::post('/{league}/admin/reopen-match', function (int $league) {
        return redirect("/leagues/{$league}/admin/reopen-match", 307);
    })->name('admin.reopen-match.store');

    Route::get('/{league}/admin/draft', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/draft?{$query}" : "/leagues/{$league}/admin/draft");
    })->name('admin.draft');

    Route::patch('/{league}/admin/draft-config', function (int $league) {
        return redirect("/leagues/{$league}/admin/draft-config", 307);
    })->name('admin.draft-config.update');

    Route::patch('/{league}/admin/draft-pick-order', function (int $league) {
        return redirect("/leagues/{$league}/admin/draft-pick-order", 307);
    })->name('admin.draft-pick-order.update');

    Route::get('/{league}/admin/league-admins', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/league-admins?{$query}" : "/leagues/{$league}/admin/league-admins");
    })->name('admin.league-admins');

    Route::patch('/{league}/admin/team-admin', function (int $league) {
        return redirect("/leagues/{$league}/admin/team-admin", 307);
    })->name('admin.team-admin.update');

    Route::post('/{league}/admin/drop-team', function (int $league) {
        return redirect("/leagues/{$league}/admin/drop-team", 307);
    })->name('admin.drop-team');
});
