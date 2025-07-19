<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\League\Models\League;
use App\Models\User;

class DraftOrder extends Model
{
    protected $table = 'draft_order';

    protected $fillable = [
        'league_id',
        'user_id',
        'pick_number',
        'status',
        'is_last_pick',
    ];
}
