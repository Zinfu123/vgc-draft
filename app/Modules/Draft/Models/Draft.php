<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\League\Models\League;
use App\Models\User;

class Draft extends Model
{
    protected $table = 'drafts';

    protected $fillable = [
        'league_id',
        'status',
        'round_number',
        'pick_number',
    ];
}
