<?php

namespace App\Modules\MatchPrep\Models;

use App\Models\User;
use App\Modules\Matches\Models\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPrepNote extends Model
{
    protected $table = 'match_prep_notes';

    protected $fillable = [
        'user_id',
        'set_id',
        'bring_six_slots',
        'plan_1_slots',
        'plan_2_slots',
        'plan_3_slots',
        'plan_1_notes',
        'plan_2_notes',
        'plan_3_notes',
        'calcs',
        'share_enabled',
        'share_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bring_six_slots' => 'array',
            'plan_1_slots' => 'array',
            'plan_2_slots' => 'array',
            'plan_3_slots' => 'array',
            'calcs' => 'array',
            'share_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    /**
     * @return list<int|null>
     */
    public static function defaultBringSix(): array
    {
        return [null, null, null, null, null, null];
    }

    /**
     * @return list<int|null>
     */
    public static function defaultPlanSlots(): array
    {
        return [null, null, null, null];
    }
}
