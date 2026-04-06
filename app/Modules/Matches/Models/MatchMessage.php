<?php

namespace App\Modules\Matches\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchMessage extends Model
{
    protected $table = 'match_messages';

    protected $fillable = [
        'set_id',
        'user_id',
        'body',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
