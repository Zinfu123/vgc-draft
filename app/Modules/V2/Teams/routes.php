<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('teams')->name('teams.')->group(function (): void {
    Route::get('/', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/teams?{$query}" : '/teams');
    })->name('index');

    Route::post('/', function (Request $request) {
        return redirect('/teams', 307);
    })->name('create');

    Route::get('/{team_id}', function (Request $request, int $team_id) {
        $query = $request->getQueryString();

        return redirect($query ? "/teams/{$team_id}?{$query}" : "/teams/{$team_id}");
    })->name('detail');

    Route::post('/{team_id}', function (Request $request, int $team_id) {
        return redirect("/teams/{$team_id}", 307);
    })->name('edit');
});
