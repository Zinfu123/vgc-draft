<?php

namespace App\Modules\Draft\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftReminder extends Model
{
    protected $table = 'draft_reminders';

    protected $fillable = [
        'draft_id',
        'league_id',
        'threshold_seconds',
        'fire_at',
        'sent_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_seconds' => 'integer',
            'fire_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }
}
