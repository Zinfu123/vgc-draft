<?php

namespace App\Modules\Matches\Models;

use Illuminate\Database\Eloquent\Model;

class Pool extends Model
{
    protected $table = 'pools';

    protected $fillable = [
        'match_config_id',
        'league_id',
        'status',
    ];
}
