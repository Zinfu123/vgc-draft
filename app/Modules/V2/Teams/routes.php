<?php

use App\Modules\V2\Teams\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('teams')->name('teams.')->group(function (): void {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'create'])->name('create');
    Route::get('/{team_id}', [TeamController::class, 'show'])->name('detail');
    Route::post('/{team_id}', [TeamController::class, 'edit'])->name('edit');
});
