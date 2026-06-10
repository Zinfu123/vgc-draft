<?php

namespace App\Modules\Matches\Models;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetGameResult extends Model
{
    protected $table = 'set_game_results';

    protected $fillable = [
        'set_id',
        'game_number',
        'p1_team_id',
        'p2_team_id',
        'winner_team_id',
        'p1_pokemon',
        'p2_pokemon',
        'p1_knockouts',
        'p2_knockouts',
        'p1_deaths',
        'p2_deaths',
        'p1_damage',
        'p2_damage',
    ];

    protected function casts(): array
    {
        return [
            'p1_pokemon' => 'array',
            'p2_pokemon' => 'array',
            'p1_knockouts' => 'array',
            'p2_knockouts' => 'array',
            'p1_deaths' => 'array',
            'p2_deaths' => 'array',
            'p1_damage' => 'array',
            'p2_damage' => 'array',
        ];
    }

    /** @return BelongsTo<Set, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function p1Team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'p1_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function p2Team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'p2_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }
}
