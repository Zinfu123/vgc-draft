<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pokedex', function (Request $request) {
        $query = $request->getQueryString();

        return redirect($query ? "/pokedex?{$query}" : '/pokedex');
    })->name('pokedex.index');

    Route::get('pokedex/abilities/{id}', function (Request $request, int $id) {
        $query = $request->getQueryString();

        return redirect($query ? "/pokedex/abilities/{$id}?{$query}" : "/pokedex/abilities/{$id}");
    })
        ->whereNumber('id')
        ->name('pokedex.abilities.show');

    Route::get('pokedex/items/{id}', function (Request $request, int $id) {
        $query = $request->getQueryString();

        return redirect($query ? "/pokedex/items/{$id}?{$query}" : "/pokedex/items/{$id}");
    })
        ->whereNumber('id')
        ->name('pokedex.items.show');

    Route::get('pokedex/{pokedex}', function (Request $request, int $pokedex) {
        $query = $request->getQueryString();

        return redirect($query ? "/pokedex/{$pokedex}?{$query}" : "/pokedex/{$pokedex}");
    })
        ->whereNumber('pokedex')
        ->name('pokedex.show');
});
