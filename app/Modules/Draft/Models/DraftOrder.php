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

    public function team()
    {
        return $this->belongsTo(\App\Modules\Teams\Models\Team::class, 'team_id')->select('id', 'name', 'logo', 'draft_points');
    }
}
