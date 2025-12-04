<?php

namespace App\Modules\Matches\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Modules\Teams\Models\Team;
use App\Modules\Matches\Resources\SetsResource;

class Set extends Model
{
    protected $table = 'sets';

    protected $fillable = [
        'pool_id',
        'league_id',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'team1_pokepaste',
        'team2_pokepaste',
        'round',
        'status',
    ];

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id', 'id')->select('id', 'name', 'logo', 'user_id')->with('user:id,name');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id', 'id')->select('id', 'name', 'logo', 'user_id')->with('user:id,name');
    }
}
