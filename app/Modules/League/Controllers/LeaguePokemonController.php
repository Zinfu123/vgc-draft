<?php

namespace App\Modules\League\Controllers;

/* Define Models */
/* End Define Models */

/* Define Controllers */
use App\Http\Controllers\Controller;
/* End Define Controllers */

/* Define Dependencies */
use App\Modules\League\Actions\CreateEditLeaguePokemonAction;
/* End Define Dependencies */

/* Define Actions */
use Illuminate\Http\Request;

/* End Define Actions */

class LeaguePokemonController extends Controller
{
    public function create(Request $data, CreateEditLeaguePokemonAction $createEditLeaguePokemonAction)
    {
        $leaguePokemon = $createEditLeaguePokemonAction($data->all());

        return redirect()->route('leagues.detail', ['league' => $data->league_id]);
    }
}
