<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('match')->name('sets.')->group(function (): void {
    Route::post('/{league}/create', function (int $league) {
        return redirect("/match/{$league}/create", 307);
    })->name('create');

    Route::get('/set/{set_id}', function (Request $request, int $set_id) {
        $query = $request->getQueryString();

        return redirect($query ? "/match/set/{$set_id}?{$query}" : "/match/set/{$set_id}");
    })->name('show');

    Route::put('/', function () {
        return redirect('/match', 307);
    })->name('update');

    Route::put('/replays', function () {
        return redirect('/match/replays', 307);
    })->name('update-replays');

    Route::post('/replays/import-teams', function () {
        return redirect('/match/replays/import-teams', 307);
    })->name('import-replay-teams');

    Route::post('/replays/preview-players', function () {
        return redirect('/match/replays/preview-players', 307);
    })->name('preview-replay-players');

    Route::get('/set/{set}/messages', function (Request $request, int $set) {
        $query = $request->getQueryString();

        return redirect($query ? "/match/set/{$set}/messages?{$query}" : "/match/set/{$set}/messages");
    })->name('messages.index');

    Route::post('/set/{set}/messages', function (int $set) {
        return redirect("/match/set/{$set}/messages", 307);
    })->name('messages.store');

    Route::post('/set/{set}/messages/read', function (int $set) {
        return redirect("/match/set/{$set}/messages/read", 307);
    })->name('messages.mark-read');

    Route::post('/set/{set}/schedule-request', function (int $set) {
        return redirect("/match/set/{$set}/schedule-request", 307);
    })->name('schedule-request.store');

    Route::patch('/schedule-request/{scheduleRequest}/respond', function (int $scheduleRequest) {
        return redirect("/match/schedule-request/{$scheduleRequest}/respond", 307);
    })->name('schedule-request.respond');
});

Route::middleware(['auth', 'verified'])->prefix('pools')->name('pools.')->group(function (): void {
    Route::post('/create', function () {
        return redirect('/pools/create', 307);
    })->name('create');

    Route::post('/assign-teams-to-pools', function () {
        return redirect('/pools/assign-teams-to-pools', 307);
    })->name('assign-teams-to-pools');
});
