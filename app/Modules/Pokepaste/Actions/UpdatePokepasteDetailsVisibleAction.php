<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use Illuminate\Http\RedirectResponse;

class UpdatePokepasteDetailsVisibleAction
{
    public function __invoke(SetTeamPokepaste $pokepaste, bool $detailsVisible): RedirectResponse
    {
        SetTeamPokepaste::query()
            ->whereKey($pokepaste->id)
            ->update(['details_visible' => $detailsVisible]);

        return redirect()
            ->route('pokepaste.show', ['pokepaste' => $pokepaste, 'edit' => 1]);
    }
}
