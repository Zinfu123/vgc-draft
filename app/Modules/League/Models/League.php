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
        'set_start_date',
        'open',
        'maximum_teams',
        'league_owner',
        'created_at',
        'updated_at',
    ];

    public function teams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Teams\Models\Team::class, 'league_id');
    }

    public function draftConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Draft\Models\DraftConfig::class, 'league_id');
    }

    public function matchConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Matches\Models\MatchConfig::class, 'league_id');
    }
}
