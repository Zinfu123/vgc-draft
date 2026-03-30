<?php

namespace App\Modules\Pokedex\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pokedex\Services\PokeApiAbilityReader;
use Inertia\Inertia;
use Inertia\Response;

class PokedexAbilityController extends Controller
{
    public function show(int $id, PokeApiAbilityReader $reader): Response
    {
        return Inertia::render('pokedex/PokedexAbilityShow', $reader->propsForInertia($id));
    }
}
