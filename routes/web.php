<?php

/* Define Controllers */
use App\Modules\Draft\Controllers\DraftController;
use App\Modules\League\Controllers\LeagueController;
use App\Modules\League\Controllers\LeaguePokemonController;
use App\Modules\Pokedex\Controllers\PokedexController;
use App\Modules\Teams\Controllers\TeamController;
/* End Define Controllers */

/* Dependencies */
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* End Dependencies */

/* Routes */
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Pokemon Routes
Route::get('pokedex', [PokedexController::class, 'index'])->middleware(['auth', 'verified'])->name('pokedex.index');

// League Routes
Route::prefix('leagues')->group(function () {
    Route::get('/', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.index');
    Route::get('/{league}', [LeagueController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.detail');
    Route::post('/', [LeagueController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.create');
    Route::get('/{league}/pokemon', [LeaguePokemonController::class, 'read'])->middleware(['auth', 'verified'])->name('leagues.pokemon');
    Route::post('/pokemon', [LeaguePokemonController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.pokemon.create');
});

// Team Routes
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->middleware(['auth', 'verified'])->name('teams.index');
    Route::post('/', [TeamController::class, 'create'])->middleware(['auth', 'verified'])->name('teams.create');
    Route::get('/{team_id}', [TeamController::class, 'show'])->middleware(['auth', 'verified'])->name('teams.detail');
});

// Draft Routes
Route::prefix('draft')->group(function () {
    Route::get('/{league_id}', [DraftController::class, 'index'])->middleware(['auth', 'verified'])->name('draft.detail');
    Route::post('/create', [DraftController::class, 'create'])->middleware(['auth', 'verified'])->name('draft.create');
    Route::post('/', [DraftController::class, 'pick'])->middleware(['auth', 'verified'])->name('draft.pick');
    Route::post('/revert-last-pick', [DraftController::class, 'revertLastPick'])->middleware(['auth', 'verified'])->name('draft.revert-last-pick');
    Route::post('/abort-draft', [DraftController::class, 'abortDraft'])->middleware(['auth', 'verified'])->name('draft.abort-draft');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
