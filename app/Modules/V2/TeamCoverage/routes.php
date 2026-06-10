<?php

use App\Modules\V2\TeamCoverage\Http\Controllers\TeamCoveragePlannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('team-coverage')->name('team-coverage.')->group(function (): void {
    Route::get('/', [TeamCoveragePlannerController::class, 'show'])->name('index');
    Route::get('/pokedex-search', [TeamCoveragePlannerController::class, 'search'])->name('pokedex-search');
    Route::get('/pokedex/{pokedex}/learnset', [TeamCoveragePlannerController::class, 'learnset'])
        ->whereNumber('pokedex')
        ->name('learnset');
    Route::get('/teams/{team}/roster', [TeamCoveragePlannerController::class, 'roster'])
        ->whereNumber('team')
        ->name('roster');
});
