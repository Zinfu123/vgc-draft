<?php

/* Define Controllers */
use App\Modules\Draft\Controllers\DraftController;
use App\Modules\League\Controllers\LeagueController;
use App\Modules\League\Controllers\LeaguePokemonController;
use App\Modules\Matches\Controllers\MatchConfigController;
use App\Modules\Matches\Controllers\PoolController;
use App\Modules\Matches\Controllers\SetController;
use App\Modules\Pokedex\Controllers\PokedexController;
use App\Modules\Teams\Controllers\TeamController;
/* End Define Controllers */

/* Dependencies */
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* End Dependencies */

/* Routes */
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// Pokemon Routes
Route::get('pokedex', [PokedexController::class, 'index'])->middleware(['auth', 'verified'])->name('pokedex.index');

// League Routes
Route::prefix('leagues')->group(function () {
    Route::get('/', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.index');
    Route::get('/{league}', [LeagueController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.detail');
    Route::post('/', [LeagueController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.create');
    Route::get('/{league}/pokemon', [LeaguePokemonController::class, 'read'])->middleware(['auth', 'verified'])->name('leagues.pokemon');
    Route::post('/pokemon', [LeaguePokemonController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.pokemon.create');
    Route::get('/{league}/pools', [PoolController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.pools');
    Route::get('/{league}/match-config', [MatchConfigController::class, 'createEditShow'])->middleware(['auth', 'verified'])->name('leagues.match-config.show');
    Route::post('/{league}/match-config/create-edit-show', [MatchConfigController::class, 'createEditShow'])->middleware(['auth', 'verified'])->name('leagues.match-config.create-edit-show');
});

// Team Routes
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->middleware(['auth', 'verified'])->name('teams.index');
    Route::post('/', [TeamController::class, 'create'])->middleware(['auth', 'verified'])->name('teams.create');
    Route::get('/{team_id}', [TeamController::class, 'show'])->middleware(['auth', 'verified'])->name('teams.detail');
    Route::post('/{team_id}', [TeamController::class, 'edit'])->middleware(['auth', 'verified'])->name('teams.edit');
});

// Draft Routes
Route::prefix('draft')->group(function () {
    Route::get('/{league_id}', [DraftController::class, 'index'])->middleware(['auth', 'verified'])->name('draft.detail');
    Route::post('/create', [DraftController::class, 'create'])->middleware(['auth', 'verified'])->name('draft.create');
    Route::post('/', [DraftController::class, 'pick'])->middleware(['auth', 'verified'])->name('draft.pick');
    Route::post('/revert-last-pick', [DraftController::class, 'revertLastPick'])->middleware(['auth', 'verified'])->name('draft.revert-last-pick');
    Route::post('/abort-draft', [DraftController::class, 'abortDraft'])->middleware(['auth', 'verified'])->name('draft.abort-draft');
});

// Match Routes
Route::prefix('match')->group(function () {
    Route::post('/{league}/create', [SetController::class, 'create'])->middleware(['auth', 'verified'])->name('sets.create');
    Route::get('/set/{set_id}', [SetController::class, 'show'])->middleware(['auth', 'verified'])->name('sets.show');
    Route::put('/', [SetController::class, 'update'])->middleware(['auth', 'verified'])->name('sets.update');
});

Route::prefix('pools')->group(function () {
    Route::post('/create', [PoolController::class, 'create'])->middleware(['auth', 'verified'])->name('pools.create');
    Route::get('/{pool_id}', [PoolController::class, 'show'])->middleware(['auth', 'verified'])->name('pools.detail');
    Route::post('/assign-teams-to-pools', [PoolController::class, 'assignTeamsToPools'])->middleware(['auth', 'verified'])->name('pools.assign-teams-to-pools');
});

Broadcast::routes();

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
