<?php

namespace App\Modules\Matches\Models;

use Illuminate\Database\Eloquent\Model;

class MatchConfig extends Model
{
    protected $table = 'match_configs';

    protected $fillable = [
        'league_id',
        'number_of_pools',
        'frequency_type',
        'frequency_value',
        'status',
    ];
}
