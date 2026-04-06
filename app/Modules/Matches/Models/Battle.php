<?php

namespace App\Modules\Matches\Models;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Battle extends Model
{
    protected $fillable = [
        'set_id',
        'p1_team_id',
        'p2_team_id',
        'format',
        'p1_packed_team',
        'p2_packed_team',
        'status',
        'winner',
        'battle_log',
    ];

    protected function casts(): array
    {
        return [
            'battle_log' => 'array',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function p1Team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'p1_team_id');
    }

    public function p2Team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'p2_team_id');
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function hasTeams(): bool
    {
        return $this->p1_packed_team !== null && $this->p2_packed_team !== null;
    }
}
