<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('draft')->name('draft.')->group(function (): void {
    Route::get('/{league_id}', function (Request $request, int $league_id) {
        $query = $request->getQueryString();

        return redirect($query ? "/draft/{$league_id}?{$query}" : "/draft/{$league_id}");
    })->name('detail');

    Route::post('/create', function () {
        return redirect('/draft/create', 307);
    })->name('create');

    Route::post('/', function () {
        return redirect('/draft', 307);
    })->name('pick');

    Route::post('/ban', function () {
        return redirect('/draft/ban', 307);
    })->name('ban');

    Route::post('/revert-last-pick', function () {
        return redirect('/draft/revert-last-pick', 307);
    })->name('revert-last-pick');

    Route::post('/abort-draft', function () {
        return redirect('/draft/abort-draft', 307);
    })->name('abort-draft');

    Route::post('/wishlist/toggle', function () {
        return redirect('/draft/wishlist/toggle', 307);
    })->name('wishlist.toggle');

    Route::post('/wishlist/reorder', function () {
        return redirect('/draft/wishlist/reorder', 307);
    })->name('wishlist.reorder');

    Route::post('/timer/pause', function () {
        return redirect('/draft/timer/pause', 307);
    })->name('timer.pause');

    Route::post('/timer/resume', function () {
        return redirect('/draft/timer/resume', 307);
    })->name('timer.resume');

    Route::post('/timer/adjust', function () {
        return redirect('/draft/timer/adjust', 307);
    })->name('timer.adjust');

    Route::post('/timer/skip', function () {
        return redirect('/draft/timer/skip', 307);
    })->name('timer.skip');
});
