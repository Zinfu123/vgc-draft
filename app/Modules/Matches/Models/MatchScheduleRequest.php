<?php

namespace App\Modules\Matches\Models;

use App\Models\User;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScheduleRequest extends Model
{
    protected $table = 'match_schedule_requests';

    protected $fillable = [
        'set_id',
        'proposed_by_user_id',
        'proposed_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_at' => 'datetime',
            'status' => ScheduleRequestStatus::class,
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    public function proposedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }
}
