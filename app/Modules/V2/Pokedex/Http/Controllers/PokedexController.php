<?php

namespace App\Modules\V2\Pokedex\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\PokedexPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokedexController extends Controller
{
    public function index(Request $request, PokedexPages $pokedexPages): Response
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type1' => ['sometimes', 'nullable', 'string', 'max:30'],
            'type2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('v2/pokedex/PokedexIndex', $pokedexPages->indexProps($validated));
    }

    public function show(Request $request, int $pokedex, PokedexPages $pokedexPages): Response
    {
        $requestedSlug = $request->query('game');
        $requestedSlug = is_string($requestedSlug) && $requestedSlug !== '' ? $requestedSlug : null;

        return Inertia::render('v2/pokedex/PokedexShow', $pokedexPages->showProps($pokedex, $requestedSlug));
    }
}
