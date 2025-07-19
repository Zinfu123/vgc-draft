<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\League\Models\League;
use App\Models\User;

class DraftPick extends Model
{
    protected $table = 'draft_picks';

    protected $fillable = [
        'draft_id',
        'team_id',
        'league_pokemon_id',
        'round_number',
        'pick_number',
    ];
}
