<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('leagues')->name('leagues.')->group(function (): void {
    Route::get('/{league}/trades', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/trades?{$query}" : "/leagues/{$league}/trades");
    })->name('trades');

    Route::post('/{league}/trades', function (int $league) {
        return redirect("/leagues/{$league}/trades", 307);
    })->name('trades.create');

    Route::post('/{league}/trades/free-agency', function (int $league) {
        return redirect("/leagues/{$league}/trades/free-agency", 307);
    })->name('trades.free-agency');

    Route::put('/{league}/trades/{trade}', function (int $league, int $trade) {
        return redirect("/leagues/{$league}/trades/{$trade}", 307);
    })->name('trades.respond');

    Route::post('/{league}/trades/set-team-trades', function (int $league) {
        return redirect("/leagues/{$league}/trades/set-team-trades", 307);
    })->name('trades.set-team-trades');
});
