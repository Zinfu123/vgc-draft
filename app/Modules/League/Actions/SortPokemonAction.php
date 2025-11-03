<?php

namespace App\Modules\League\Actions;

use Illuminate\Support\Facades\Log;

class SortPokemonAction
{
    public function __invoke(array $data)
    {
        $grouped = $data['pokemon']->groupBy('league.0.pivot.cost');
        Log::info($grouped);

        return $grouped;
    }
}
