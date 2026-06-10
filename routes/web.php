<?php

/* Define Controllers */
use App\Modules\Calendar\Controllers\CalendarController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\League\Controllers\LeagueController;
use App\Modules\League\Controllers\LeaguePokemonAdminController;
use App\Modules\League\Controllers\LeaguePokemonController;
use App\Modules\League\Controllers\PoolTemplateCatalogController;
use App\Modules\Matches\Controllers\MatchConfigController;
use App\Modules\Matches\Controllers\MatchMessageController;
use App\Modules\Matches\Controllers\MatchScheduleRequestController;
use App\Modules\Matches\Controllers\PoolController;
use App\Modules\Matches\Controllers\SetController;
use App\Modules\MatchPrep\Controllers\MatchPrepController;
use App\Modules\Playoffs\Controllers\PlayoffController;
use App\Modules\Pokepaste\Controllers\PokepasteController;
use App\Modules\Stats\Controllers\PokemonUsageStatsController;
use App\Modules\Trade\Controllers\TradeController;
use App\Modules\V2\Draft\Http\Controllers\DraftController;
use App\Modules\V2\Pokedex\Http\Controllers\PokedexAbilityController;
use App\Modules\V2\Pokedex\Http\Controllers\PokedexController;
use App\Modules\V2\Pokedex\Http\Controllers\PokedexItemController;
use App\Modules\V2\TeamCoverage\Http\Controllers\TeamCoveragePlannerController;
use App\Modules\V2\Teams\Http\Controllers\TeamController;
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

Route::get('docs', function () {
    return Inertia::render('Docs');
})->name('docs');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::get('calendar', [CalendarController::class, 'index'])->middleware(['auth', 'verified'])->name('calendar.index');

Route::get('/match-prep/share/{share_uuid}', [MatchPrepController::class, 'showShare'])
    ->whereUuid('share_uuid')
    ->name('match-prep.share.show');

// Pokemon Routes
Route::get('pokedex', [PokedexController::class, 'index'])->middleware(['auth', 'verified'])->name('pokedex.index');
Route::get('pokedex/abilities/{id}', [PokedexAbilityController::class, 'show'])
    ->whereNumber('id')
    ->middleware(['auth', 'verified'])
    ->name('pokedex.abilities.show');
Route::get('pokedex/items/{id}', [PokedexItemController::class, 'show'])
    ->whereNumber('id')
    ->middleware(['auth', 'verified'])
    ->name('pokedex.items.show');
Route::get('pokedex/{pokedex}', [PokedexController::class, 'show'])->middleware(['auth', 'verified'])->name('pokedex.show');

Route::get('pool-templates', [PoolTemplateCatalogController::class, 'index'])->middleware(['auth', 'verified'])->name('pool-templates.index');
Route::get('pool-templates/{slug}/preview', [PoolTemplateCatalogController::class, 'preview'])
    ->middleware(['auth', 'verified'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('pool-templates.preview');

Route::get('usage-stats', [PokemonUsageStatsController::class, 'index'])->middleware(['auth', 'verified'])->name('usage-stats.index');
Route::get('usage-stats/{pokedex_id}', [PokemonUsageStatsController::class, 'show'])->middleware(['auth', 'verified'])->name('usage-stats.show');

// League Routes
Route::prefix('leagues')->group(function () {
    Route::get('/', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.index');
    Route::get('/create-edit', [LeagueController::class, 'createEditShow'])->middleware(['auth', 'verified'])->name('leagues.create-edit');
    Route::get('/{league}', [LeagueController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.detail');
    Route::get('/{league}/dashboard', [LeagueController::class, 'showDashboard'])->middleware(['auth', 'verified'])->name('leagues.dashboard');
    Route::get('/{league}/rosters', [LeagueController::class, 'showTeams'])->middleware(['auth', 'verified'])->name('leagues.rosters');
    Route::get('/{league}/schedule', [LeagueController::class, 'showSchedule'])->middleware(['auth', 'verified'])->name('leagues.schedule');
    // Legacy redirects — keep named routes working for any existing links
    Route::get('/{league}/teams', fn ($league) => redirect()->route('leagues.rosters', ['league' => $league]))->middleware(['auth', 'verified'])->name('leagues.teams');
    Route::get('/{league}/matches', function ($league) {
        return redirect()->route('leagues.schedule', array_filter([
            'league' => $league,
            'view' => 'matches',
            'team' => request()->query('team'),
        ], fn ($value) => $value !== null && $value !== ''));
    })->middleware(['auth', 'verified'])->name('leagues.matches');
    Route::get('/{league}/standings', fn ($league) => redirect()->route('leagues.schedule', ['league' => $league, 'view' => 'standings']))->middleware(['auth', 'verified'])->name('leagues.standings');
    Route::get('/{league}/playoffs', fn ($league) => redirect()->route('leagues.schedule', ['league' => $league, 'view' => 'playoffs']))->middleware(['auth', 'verified'])->name('leagues.playoffs');
    Route::get('/{league}/stats', [LeagueController::class, 'showStats'])->middleware(['auth', 'verified'])->name('leagues.stats');
    Route::get('/{league}/trades', [TradeController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.trades');
    Route::post('/{league}/trades', [TradeController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.trades.create');
    Route::post('/{league}/trades/free-agency', [TradeController::class, 'freeAgency'])->middleware(['auth', 'verified'])->name('leagues.trades.free-agency');
    Route::put('/{league}/trades/{trade}', [TradeController::class, 'respond'])->middleware(['auth', 'verified'])->name('leagues.trades.respond');
    Route::post('/{league}/trades/set-team-trades', [TradeController::class, 'setTeamTrades'])->middleware(['auth', 'verified'])->name('leagues.trades.set-team-trades');
    Route::get('/{league}/draft', [LeagueController::class, 'showDraft'])->middleware(['auth', 'verified'])->name('leagues.draft');
    Route::post('/', [LeagueController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.create');
    Route::post('/{league}/cancel', [LeagueController::class, 'cancelLeague'])->middleware(['auth', 'verified'])->name('leagues.cancel');
    Route::post('/{league}/start-regular-season', [LeagueController::class, 'startRegularSeason'])->middleware(['auth', 'verified'])->name('leagues.start-regular-season');
    Route::post('/{league}/start-playoffs', [LeagueController::class, 'startPlayoffs'])->middleware(['auth', 'verified'])->name('leagues.start-playoffs');
    Route::post('/{league}/finalize', [LeagueController::class, 'finalizeRegularSeason'])->middleware(['auth', 'verified'])->name('leagues.finalize');
    Route::patch('/{league}/trade-deadline', [LeagueController::class, 'updateTradeDeadline'])->middleware(['auth', 'verified'])->name('leagues.trade-deadline.update');
    Route::patch('/{league}/free-trade-window', [LeagueController::class, 'updateFreeTradeWindow'])->middleware(['auth', 'verified'])->name('leagues.free-trade-window.update');
    Route::post('/{league}/discord-webhook', [LeagueController::class, 'updateDiscordWebhook'])->middleware(['auth', 'verified'])->name('leagues.discord-webhook');
    Route::get('/{league}/admin', [LeagueController::class, 'showAdmin'])->middleware(['auth', 'verified'])->name('leagues.admin');
    Route::get('/{league}/admin/match-config', [LeagueController::class, 'showAdminMatchConfig'])->middleware(['auth', 'verified'])->name('leagues.admin.match-config');
    Route::get('/{league}/admin/discord', [LeagueController::class, 'showAdminDiscord'])->middleware(['auth', 'verified'])->name('leagues.admin.discord');
    Route::get('/{league}/admin/trades', [LeagueController::class, 'showAdminTrades'])->middleware(['auth', 'verified'])->name('leagues.admin.trades');
    Route::get('/{league}/admin/winner', [LeagueController::class, 'showAdminWinner'])->middleware(['auth', 'verified'])->name('leagues.admin.winner');
    Route::get('/{league}/admin/reopen-match', [LeagueController::class, 'showAdminReopenMatch'])->middleware(['auth', 'verified'])->name('leagues.admin.reopen-match');
    Route::post('/{league}/admin/reopen-match', [LeagueController::class, 'reopenMatchSet'])->middleware(['auth', 'verified'])->name('leagues.admin.reopen-match.store');
    Route::get('/{league}/admin/draft', [LeagueController::class, 'showAdminDraft'])->middleware(['auth', 'verified'])->name('leagues.admin.draft');
    Route::patch('/{league}/admin/draft-config', [LeagueController::class, 'updateDraftConfig'])->middleware(['auth', 'verified'])->name('leagues.admin.draft-config.update');
    Route::patch('/{league}/admin/draft-pick-order', [LeagueController::class, 'updateDraftPickOrder'])->middleware(['auth', 'verified'])->name('leagues.admin.draft-pick-order.update');
    Route::get('/{league}/admin/league-admins', [LeagueController::class, 'showAdminLeagueAdmins'])->middleware(['auth', 'verified'])->name('leagues.admin.league-admins');
    Route::patch('/{league}/admin/team-admin', [LeagueController::class, 'updateTeamAdmin'])->middleware(['auth', 'verified'])->name('leagues.admin.team-admin.update');
    Route::post('/{league}/admin/drop-team', [LeagueController::class, 'dropTeamFromLeague'])->middleware(['auth', 'verified'])->name('leagues.admin.drop-team');
    Route::get('/{league}/admin/playoffs', [PlayoffController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs');
    Route::patch('/{league}/admin/playoffs', [PlayoffController::class, 'update'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.update');
    Route::post('/{league}/admin/playoffs/generate', [PlayoffController::class, 'generate'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.generate');
    Route::post('/{league}/admin/playoffs/record', [PlayoffController::class, 'recordResult'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.record');
    Route::post('/{league}/admin/playoffs/rollback', [PlayoffController::class, 'rollback'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.rollback');
    Route::post('/{league}/admin/playoffs/close', [PlayoffController::class, 'close'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.close');
    Route::post('/{league}/admin/playoffs/reset', [PlayoffController::class, 'reset'])->middleware(['auth', 'verified'])->name('leagues.admin.playoffs.reset');
    Route::get('/{league}/admin/pokemon-pool', [LeaguePokemonAdminController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-pool');
    Route::get('/{league}/admin/pokemon-templates', [LeaguePokemonAdminController::class, 'templatesIndex'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-templates.index');
    Route::get('/{league}/admin/pokemon-templates/{template}/preview', [LeaguePokemonAdminController::class, 'templatePreview'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-templates.preview');
    Route::post('/{league}/admin/pokemon-templates/{template}/apply', [LeaguePokemonAdminController::class, 'applyTemplate'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-templates.apply');
    Route::patch('/{league}/admin/pokemon-pool/{leaguePokemon}', [LeaguePokemonAdminController::class, 'updatePokemon'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-pool.update');
    Route::delete('/{league}/admin/pokemon-pool/{leaguePokemon}', [LeaguePokemonAdminController::class, 'destroy'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-pool.destroy');
    Route::post('/{league}/admin/pokemon-pool', [LeaguePokemonAdminController::class, 'store'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-pool.store');
    Route::post('/{league}/admin/pokemon-pool/import-csv', [LeaguePokemonAdminController::class, 'importCsv'])->middleware(['auth', 'verified'])->name('leagues.admin.pokemon-pool.import-csv');
    Route::get('/{league}/admin/pokedex-search', [LeaguePokemonAdminController::class, 'pokedexSearch'])->middleware(['auth', 'verified'])->name('leagues.admin.pokedex-search');
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
    Route::post('/wishlist/toggle', [DraftController::class, 'toggleWishlist'])->middleware(['auth', 'verified'])->name('draft.wishlist.toggle');
    Route::post('/wishlist/reorder', [DraftController::class, 'reorderWishlist'])->middleware(['auth', 'verified'])->name('draft.wishlist.reorder');
    Route::post('/timer/pause', [DraftController::class, 'pauseTimer'])->middleware(['auth', 'verified'])->name('draft.timer.pause');
    Route::post('/timer/resume', [DraftController::class, 'resumeTimer'])->middleware(['auth', 'verified'])->name('draft.timer.resume');
    Route::post('/timer/adjust', [DraftController::class, 'adjustTimer'])->middleware(['auth', 'verified'])->name('draft.timer.adjust');
    Route::post('/timer/skip', [DraftController::class, 'forceSkip'])->middleware(['auth', 'verified'])->name('draft.timer.skip');
});

// Match Routes
Route::prefix('match')->group(function () {
    Route::post('/{league}/create', [SetController::class, 'create'])->middleware(['auth', 'verified'])->name('sets.create');
    Route::get('/set/{set_id}', [SetController::class, 'show'])->middleware(['auth', 'verified'])->name('sets.show');
    Route::put('/', [SetController::class, 'update'])->middleware(['auth', 'verified'])->name('sets.update');
    Route::put('/replays', [SetController::class, 'updateReplays'])->middleware(['auth', 'verified'])->name('sets.update-replays');
    Route::post('/replays/import-teams', [SetController::class, 'importReplayTeams'])->middleware(['auth', 'verified'])->name('sets.import-replay-teams');
    Route::post('/replays/preview-players', [SetController::class, 'previewReplayPlayers'])->middleware(['auth', 'verified'])->name('sets.preview-replay-players');

    Route::get('/set/{set}/messages', [MatchMessageController::class, 'index'])->middleware(['auth', 'verified'])->name('sets.messages.index');
    Route::post('/set/{set}/messages', [MatchMessageController::class, 'store'])->middleware(['auth', 'verified'])->name('sets.messages.store');
    Route::post('/set/{set}/messages/read', [MatchMessageController::class, 'markRead'])->middleware(['auth', 'verified'])->name('sets.messages.mark-read');

    Route::post('/set/{set}/schedule-request', [MatchScheduleRequestController::class, 'store'])->middleware(['auth', 'verified'])->name('sets.schedule-request.store');
    Route::patch('/schedule-request/{scheduleRequest}/respond', [MatchScheduleRequestController::class, 'respond'])->middleware(['auth', 'verified'])->name('sets.schedule-request.respond');

});

Route::get('/pokepaste/{pokepaste}', [PokepasteController::class, 'show'])->name('pokepaste.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/pokepaste/{pokepaste}', [PokepasteController::class, 'update'])->name('pokepaste.update');
    Route::patch('/pokepaste/{pokepaste}/details-visible', [PokepasteController::class, 'updateDetailsVisible'])->name('pokepaste.update-details-visible');
    Route::post('/pokepaste/{pokepaste}/parse', [PokepasteController::class, 'parse'])->name('pokepaste.parse');
});

Route::prefix('pools')->group(function () {
    Route::post('/create', [PoolController::class, 'create'])->middleware(['auth', 'verified'])->name('pools.create');
    Route::post('/assign-teams-to-pools', [PoolController::class, 'assignTeamsToPools'])->middleware(['auth', 'verified'])->name('pools.assign-teams-to-pools');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/match-prep', [MatchPrepController::class, 'index'])->name('match-prep.index');
    Route::put('/match-prep/{set}', [MatchPrepController::class, 'update'])->name('match-prep.update');
    Route::post('/match-prep/{set}/share', [MatchPrepController::class, 'updateShare'])->name('match-prep.share');

    Route::prefix('team-coverage')->name('team-coverage.')->group(function () {
        Route::get('/', [TeamCoveragePlannerController::class, 'show'])->name('index');
        Route::get('/pokedex-search', [TeamCoveragePlannerController::class, 'search'])->name('pokedex-search');
        Route::get('/pokedex/{pokedex}/learnset', [TeamCoveragePlannerController::class, 'learnset'])
            ->whereNumber('pokedex')
            ->name('learnset');
        Route::get('/teams/{team}/roster', [TeamCoveragePlannerController::class, 'roster'])
            ->whereNumber('team')
            ->name('roster');
    });
});

Broadcast::routes();

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/v2.php';
