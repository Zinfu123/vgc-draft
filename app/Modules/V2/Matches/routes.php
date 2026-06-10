<?php

use App\Modules\V2\Matches\Http\Controllers\MatchMessageController;
use App\Modules\V2\Matches\Http\Controllers\MatchScheduleRequestController;
use App\Modules\V2\Matches\Http\Controllers\PoolController;
use App\Modules\V2\Matches\Http\Controllers\SetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('match')->name('sets.')->group(function (): void {
    Route::post('/{league}/create', [SetController::class, 'create'])->name('create');
    Route::get('/set/{set_id}', [SetController::class, 'show'])->name('show');
    Route::put('/', [SetController::class, 'update'])->name('update');
    Route::put('/replays', [SetController::class, 'updateReplays'])->name('update-replays');
    Route::post('/replays/import-teams', [SetController::class, 'importReplayTeams'])->name('import-replay-teams');
    Route::post('/replays/preview-players', [SetController::class, 'previewReplayPlayers'])->name('preview-replay-players');

    Route::get('/set/{set}/messages', [MatchMessageController::class, 'index'])->name('messages.index');
    Route::post('/set/{set}/messages', [MatchMessageController::class, 'store'])->name('messages.store');
    Route::post('/set/{set}/messages/read', [MatchMessageController::class, 'markRead'])->name('messages.mark-read');

    Route::post('/set/{set}/schedule-request', [MatchScheduleRequestController::class, 'store'])->name('schedule-request.store');
    Route::patch('/schedule-request/{scheduleRequest}/respond', [MatchScheduleRequestController::class, 'respond'])->name('schedule-request.respond');
});

Route::middleware(['auth', 'verified'])->prefix('pools')->name('pools.')->group(function (): void {
    Route::post('/create', [PoolController::class, 'create'])->name('create');
    Route::get('/{pool_id}', [PoolController::class, 'show'])->name('detail');
    Route::post('/assign-teams-to-pools', [PoolController::class, 'assignTeamsToPools'])->name('assign-teams-to-pools');
});
