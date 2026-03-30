<?php

namespace App\Modules\Pokedex\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pokedex\Services\PokeApiItemReader;
use Inertia\Inertia;
use Inertia\Response;

class PokedexItemController extends Controller
{
    public function show(int $id, PokeApiItemReader $reader): Response
    {
        return Inertia::render('pokedex/PokedexItemShow', $reader->propsForInertia($id));
    }
}
