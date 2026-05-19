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
        'current_deadline_at',
        'paused_at',
        'paused_remaining_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'round_number' => 'integer',
            'pick_number' => 'integer',
            'current_deadline_at' => 'datetime',
            'paused_at' => 'datetime',
            'paused_remaining_seconds' => 'integer',
        ];
    }
}
