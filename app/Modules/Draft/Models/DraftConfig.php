<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

class DraftConfig extends Model
{
    protected $table = 'draft_config';

    protected $fillable = [
        'league_id',
        'draft_date',
        'draft_start_at',
        'draft_ended_at',
        'draft_points',
        'minimum_drafts',
        'ban_enabled',
        'bans_per_user',
        'minimum_cost_to_ban',
        'pick_timer_enabled',
        'pick_timer_seconds',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_timezone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draft_date' => 'date',
            'draft_start_at' => 'datetime',
            'draft_ended_at' => 'datetime',
            'draft_points' => 'integer',
            'minimum_drafts' => 'integer',
            'ban_enabled' => 'boolean',
            'bans_per_user' => 'integer',
            'minimum_cost_to_ban' => 'integer',
            'pick_timer_enabled' => 'boolean',
            'pick_timer_seconds' => 'integer',
            'quiet_hours_enabled' => 'boolean',
        ];
    }

    public function league(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }
}
