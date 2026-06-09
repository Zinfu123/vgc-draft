<?php

namespace App\Modules\V2\Pokedex\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\PokedexPages;
use Inertia\Inertia;
use Inertia\Response;

class PokedexItemController extends Controller
{
    public function show(int $id, PokedexPages $pokedexPages): Response
    {
        return Inertia::render('v2/pokedex/PokedexItemShow', $pokedexPages->itemProps($id));
    }
}
