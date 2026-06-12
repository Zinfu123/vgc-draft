<?php

namespace App\Http\Requests\Concerns;

use App\Modules\League\Models\League;

trait ResolvesRouteLeague
{
    protected function routeLeague(): League
    {
        $league = $this->route('league');

        if ($league instanceof League) {
            return $league;
        }

        return League::query()->findOrFail((int) $league);
    }
}
