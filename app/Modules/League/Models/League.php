<?php

namespace App\Modules\League\Models;

use Illuminate\Database\Eloquent\Model;

class League extends Model
{
    protected $table = 'leagues';

    protected $fillable = [
        'name',
        'status',
        'winner',
        'set_frequency',
        'logo',
        'draft_date',
        'set_start_date',
        'draft_points',
        'minimum_drafts',
        'league_owner',
        'status',
        'created_at',
        'updated_at',
    ];

    public function teams()
    {
        return $this->hasMany(\App\Modules\Teams\Models\Team::class, 'league_id');
    }
}
