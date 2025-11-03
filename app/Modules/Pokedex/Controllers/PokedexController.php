<?php

namespace App\Modules\Pokedex\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pokedex\Models\Pokedex;
use Inertia\Inertia;

class PokedexController extends Controller
{
    public function index()
    {
        $pokedex = Pokedex::all();

        return Inertia::render('pokedex/PokedexIndex', [
            'pokemon' => $pokedex,
        ]);
    }
}
