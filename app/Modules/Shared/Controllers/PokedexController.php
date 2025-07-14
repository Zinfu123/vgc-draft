<?php

namespace App\Modules\Shared\Controllers;

use App\Modules\Shared\Models\Pokedex;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class PokedexController extends Controller
{
    public function index()
    {
        $pokedex = Pokedex::all();
        return Inertia::render('pokedex/PokedexIndex', [
            'pokemon' => $pokedex
        ]);
    }
}
