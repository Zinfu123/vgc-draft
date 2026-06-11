<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('leagues')->name('leagues.admin.')->group(function (): void {
    Route::get('/{league}/admin/playoffs', function (Request $request, int $league) {
        $query = $request->getQueryString();

        return redirect($query ? "/leagues/{$league}/admin/playoffs?{$query}" : "/leagues/{$league}/admin/playoffs");
    })->name('playoffs');

    Route::patch('/{league}/admin/playoffs', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs", 307);
    })->name('playoffs.update');

    Route::post('/{league}/admin/playoffs/generate', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs/generate", 307);
    })->name('playoffs.generate');

    Route::post('/{league}/admin/playoffs/record', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs/record", 307);
    })->name('playoffs.record');

    Route::post('/{league}/admin/playoffs/rollback', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs/rollback", 307);
    })->name('playoffs.rollback');

    Route::post('/{league}/admin/playoffs/close', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs/close", 307);
    })->name('playoffs.close');

    Route::post('/{league}/admin/playoffs/reset', function (int $league) {
        return redirect("/leagues/{$league}/admin/playoffs/reset", 307);
    })->name('playoffs.reset');
});
