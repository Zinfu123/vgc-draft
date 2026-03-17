<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

class DraftConfig extends Model
{
    protected $table = 'draft_config';

    protected $fillable = [
        'league_id',
        'draft_date',
        'draft_points',
        'minimum_drafts',
        'ban_enabled',
        'bans_per_user',
        'minimum_cost_to_ban',
    ];

    public function league(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }
}
