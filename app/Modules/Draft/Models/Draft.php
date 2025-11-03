<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

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
