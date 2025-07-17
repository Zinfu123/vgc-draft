<?php

use App\Modules\Shared\Controllers\PokedexController;
use App\Modules\League\Controllers\LeagueController;
use App\Modules\Teams\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

//Pokemon Routes
Route::get('pokedex', [PokedexController::class, 'index'])->middleware(['auth', 'verified'])->name('pokedex.index');

//League Routes
Route::prefix('leagues')->group(function () {
    Route::get('/', [LeagueController::class, 'index'])->middleware(['auth', 'verified'])->name('leagues.index');
    Route::get('/{league}', [LeagueController::class, 'show'])->middleware(['auth', 'verified'])->name('leagues.detail');
    Route::post('/', [LeagueController::class, 'create'])->middleware(['auth', 'verified'])->name('leagues.create');
});

//Team Routes
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->middleware(['auth', 'verified'])->name('teams.index');
    Route::post('/', [TeamController::class, 'create'])->middleware(['auth', 'verified'])->name('teams.create');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
