<?php

namespace App\Modules\Playoffs\Models;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayoffMatch extends Model
{
    protected $table = 'playoff_matches';

    protected $fillable = [
        'playoff_id',
        'slot',
        'round_index',
        'sort_order',
        'is_bronze',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_team_id',
        'completed_at',
        'feeds',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bronze' => 'boolean',
            'feeds' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function playoff(): BelongsTo
    {
        return $this->belongsTo(Playoff::class, 'playoff_id');
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function isComplete(): bool
    {
        return $this->winner_team_id !== null && $this->completed_at !== null;
    }
}
