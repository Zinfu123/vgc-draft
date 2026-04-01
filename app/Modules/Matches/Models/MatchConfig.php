<?php

namespace App\Modules\Matches\Models;

use Illuminate\Database\Eloquent\Model;

class MatchConfig extends Model
{
    protected $table = 'match_configs';

    protected $fillable = [
        'league_id',
        'number_of_pools',
        'frequency_type',
        'frequency_value',
        'status',
        'enforce_round_count',
        'round_count',
        'require_team_match_pokepaste_before_results',
        'require_replays_before_results',
        'auto_complete_set_from_replays',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enforce_round_count' => 'boolean',
            'require_team_match_pokepaste_before_results' => 'boolean',
            'require_replays_before_results' => 'boolean',
            'auto_complete_set_from_replays' => 'boolean',
        ];
    }
}
