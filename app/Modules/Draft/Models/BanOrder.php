<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;

class BanOrder extends Model
{
    protected $table = 'draft_ban_order';

    protected $fillable = [
        'league_id',
        'team_id',
        'user_id',
        'team_name',
        'ban_number',
        'round_number',
        'status',
        'is_last_ban',
        'skipped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skipped_at' => 'datetime',
        ];
    }

    public function league(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\League\Models\League::class, 'league_id');
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Teams\Models\Team::class, 'team_id')->select('id', 'name', 'logo', 'draft_points', 'user_id');
    }
}
