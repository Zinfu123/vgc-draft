<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('team-coverage')->name('team-coverage.')->group(function (): void {
    Route::get('/', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/team-coverage?{$query}" : '/team-coverage');
    })->name('index');

    Route::get('/pokedex-search', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/team-coverage/pokedex-search?{$query}" : '/team-coverage/pokedex-search');
    })->name('pokedex-search');

    Route::get('/pokedex/{pokedex}/learnset', function (Request $request, int $pokedex) {
        $query = $request->getQueryString();

        return redirect($query ? "/team-coverage/pokedex/{$pokedex}/learnset?{$query}" : "/team-coverage/pokedex/{$pokedex}/learnset");
    })
        ->whereNumber('pokedex')
        ->name('learnset');

    Route::get('/teams/{team}/roster', function (Request $request, int $team) {
        $query = $request->getQueryString();

        return redirect($query ? "/team-coverage/teams/{$team}/roster?{$query}" : "/team-coverage/teams/{$team}/roster");
    })
        ->whereNumber('team')
        ->name('roster');
});
