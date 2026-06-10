<?php

use App\Modules\V2\Draft\Http\Controllers\DraftController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('draft')->name('draft.')->group(function (): void {
    Route::get('/{league_id}', [DraftController::class, 'index'])->name('detail');
    Route::post('/create', [DraftController::class, 'create'])->name('create');
    Route::post('/', [DraftController::class, 'pick'])->name('pick');
    Route::post('/ban', [DraftController::class, 'ban'])->name('ban');
    Route::post('/revert-last-pick', [DraftController::class, 'revertLastPick'])->name('revert-last-pick');
    Route::post('/abort-draft', [DraftController::class, 'abortDraft'])->name('abort-draft');
    Route::post('/wishlist/toggle', [DraftController::class, 'toggleWishlist'])->name('wishlist.toggle');
    Route::post('/wishlist/reorder', [DraftController::class, 'reorderWishlist'])->name('wishlist.reorder');
    Route::post('/timer/pause', [DraftController::class, 'pauseTimer'])->name('timer.pause');
    Route::post('/timer/resume', [DraftController::class, 'resumeTimer'])->name('timer.resume');
    Route::post('/timer/adjust', [DraftController::class, 'adjustTimer'])->name('timer.adjust');
    Route::post('/timer/skip', [DraftController::class, 'forceSkip'])->name('timer.skip');
});
