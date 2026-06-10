<?php

namespace App\Modules\Matches\Models;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    protected $table = 'pools';

    protected $fillable = [
        'match_config_id',
        'league_id',
        'name',
        'status',
    ];

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'pool_id');
    }
}
