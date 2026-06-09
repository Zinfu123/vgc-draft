<?php

use App\Modules\V2\Pokedex\Http\Controllers\PokedexAbilityController;
use App\Modules\V2\Pokedex\Http\Controllers\PokedexController;
use App\Modules\V2\Pokedex\Http\Controllers\PokedexItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pokedex', [PokedexController::class, 'index'])->name('pokedex.index');
    Route::get('pokedex/abilities/{id}', [PokedexAbilityController::class, 'show'])
        ->whereNumber('id')
        ->name('pokedex.abilities.show');
    Route::get('pokedex/items/{id}', [PokedexItemController::class, 'show'])
        ->whereNumber('id')
        ->name('pokedex.items.show');
    Route::get('pokedex/{pokedex}', [PokedexController::class, 'show'])
        ->whereNumber('pokedex')
        ->name('pokedex.show');
});
