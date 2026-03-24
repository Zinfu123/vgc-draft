<?php

/* Define Controllers */
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Draft\Controllers\DraftController;
use App\Modules\League\Controllers\LeagueController;
use App\Modules\League\Controllers\LeaguePokemonController;
use App\Modules\Matches\Controllers\MatchConfigController;
use App\Modules\Matches\Controllers\PoolController;
use App\Modules\Matches\Controllers\SetController;
use App\Modules\MatchPrep\Controllers\MatchPrepController;
use App\Modules\Pokedex\Controllers\PokedexController;
use App\Modules\Pokepaste\Controllers\PokepasteController;
use App\Modules\Teams\Controllers\TeamController;
use App\Modules\Trade\Controllers\TradeController;
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

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/match-prep/share/{share_uuid}', [MatchPrepController::class, 'showShare'])
    ->whereUuid('share_uuid')
    ->name('match-prep.share.show');

// Pokemon Routes
Route::get('pokedex', [PokedexController::class, 'index'])->middleware(['auth', 'verified'])->name('pokedex.index');
Route::get('pokedex/{pokedex}', [PokedexController::class, 'show'])->middleware(['auth', 'verified'])->name('pokedex.show');

// League Routes
Route::prefix('leagues')->group(function () {
    Route::get('/', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.index');
    Route::get('/create-edit', [LeagueController::class, 'createEditShow'])->middleware(['auth', 'verified'])->name('leagues.create-edit');
    Route::get('/{league}', [LeagueController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.detail');
    Route::get('/{league}/teams', [LeagueController::class, 'showTeams'])->middleware(['auth', 'verified'])->name('leagues.teams');
    Route::get('/{league}/matches', [LeagueController::class, 'showMatches'])->middleware(['auth', 'verified'])->name('leagues.matches');
    Route::get('/{league}/standings', [LeagueController::class, 'showStandings'])->middleware(['auth', 'verified'])->name('leagues.standings');
    Route::get('/{league}/trades', [TradeController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.trades');
    Route::post('/{league}/trades', [TradeController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.trades.create');
    Route::put('/{league}/trades/{trade}', [TradeController::class, 'respond'])->middleware(['auth', 'verified'])->name('leagues.trades.respond');
    Route::post('/{league}/trades/set-team-trades', [TradeController::class, 'setTeamTrades'])->middleware(['auth', 'verified'])->name('leagues.trades.set-team-trades');
    Route::get('/{league}/draft', [LeagueController::class, 'showDraft'])->middleware(['auth', 'verified'])->name('leagues.draft');
    Route::post('/', [LeagueController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.create');
    Route::post('/{league}/set-winner', [LeagueController::class, 'setWinner'])->middleware(['auth', 'verified'])->name('leagues.set-winner');
    Route::post('/{league}/discord-webhook', [LeagueController::class, 'updateDiscordWebhook'])->middleware(['auth', 'verified'])->name('leagues.discord-webhook');
    Route::get('/{league}/admin', [LeagueController::class, 'showAdmin'])->middleware(['auth', 'verified'])->name('leagues.admin');
    Route::get('/{league}/admin/match-config', [LeagueController::class, 'showAdminMatchConfig'])->middleware(['auth', 'verified'])->name('leagues.admin.match-config');
    Route::get('/{league}/admin/discord', [LeagueController::class, 'showAdminDiscord'])->middleware(['auth', 'verified'])->name('leagues.admin.discord');
    Route::get('/{league}/admin/trades', [LeagueController::class, 'showAdminTrades'])->middleware(['auth', 'verified'])->name('leagues.admin.trades');
    Route::get('/{league}/admin/winner', [LeagueController::class, 'showAdminWinner'])->middleware(['auth', 'verified'])->name('leagues.admin.winner');
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
    Route::post('/ban', [DraftController::class, 'ban'])->middleware(['auth', 'verified'])->name('draft.ban');
    Route::post('/revert-last-pick', [DraftController::class, 'revertLastPick'])->middleware(['auth', 'verified'])->name('draft.revert-last-pick');
    Route::post('/abort-draft', [DraftController::class, 'abortDraft'])->middleware(['auth', 'verified'])->name('draft.abort-draft');
});

// Match Routes
Route::prefix('match')->group(function () {
    Route::post('/{league}/create', [SetController::class, 'create'])->middleware(['auth', 'verified'])->name('sets.create');
    Route::get('/set/{set_id}', [SetController::class, 'show'])->middleware(['auth', 'verified'])->name('sets.show');
    Route::put('/', [SetController::class, 'update'])->middleware(['auth', 'verified'])->name('sets.update');
    Route::put('/replays', [SetController::class, 'updateReplays'])->middleware(['auth', 'verified'])->name('sets.update-replays');
});

Route::get('/pokepaste/{pokepaste}', [PokepasteController::class, 'show'])->name('pokepaste.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/pokepaste/{pokepaste}', [PokepasteController::class, 'update'])->name('pokepaste.update');
    Route::post('/pokepaste/{pokepaste}/parse', [PokepasteController::class, 'parse'])->name('pokepaste.parse');
});

Route::prefix('pools')->group(function () {
    Route::post('/create', [PoolController::class, 'create'])->middleware(['auth', 'verified'])->name('pools.create');
    Route::get('/{pool_id}', [PoolController::class, 'show'])->middleware(['auth', 'verified'])->name('pools.detail');
    Route::post('/assign-teams-to-pools', [PoolController::class, 'assignTeamsToPools'])->middleware(['auth', 'verified'])->name('pools.assign-teams-to-pools');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/match-prep', [MatchPrepController::class, 'index'])->name('match-prep.index');
    Route::put('/match-prep/{set}', [MatchPrepController::class, 'update'])->name('match-prep.update');
    Route::post('/match-prep/{set}/share', [MatchPrepController::class, 'updateShare'])->name('match-prep.share');
});

Broadcast::routes();

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
