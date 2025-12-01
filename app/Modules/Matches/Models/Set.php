<?php

namespace App\Modules\Matches\Models;

use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    protected $table = 'sets';

    protected $fillable = [
        'pool_id',
        'league_id',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'team1_pokepaste',
        'team2_pokepaste',
        'round',
        'status',
    ];
}
