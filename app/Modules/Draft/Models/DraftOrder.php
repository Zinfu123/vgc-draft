<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

class DraftOrder extends Model
{
    protected $table = 'draft_order';

    protected $fillable = [
        'league_id',
        'user_id',
        'pick_number',
        'status',
        'team_name',
        'is_last_pick',
    ];
}
