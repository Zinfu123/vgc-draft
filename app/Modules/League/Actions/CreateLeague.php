<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use Illuminate\Http\Request;

class CreateLeague
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|boolean',
            'winner' => 'required|string|max:255',
            'set_frequency' => 'required|string|max:255',
            'logo' => 'required|string|max:255',
        ]);
        $league = League::create($request->all());
        return $league;
    }
}