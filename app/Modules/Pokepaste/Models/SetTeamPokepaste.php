<?php

namespace App\Modules\Pokepaste\Models;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SetTeamPokepaste extends Model
{
    protected $table = 'set_team_pokepastes';

    protected $fillable = [
        'set_id',
        'team_id',
        'public_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (SetTeamPokepaste $model): void {
            if ($model->public_id === null) {
                $model->public_id = self::generateUniquePublicId();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<SetTeamPokepasteSlot, $this>
     */
    public function pasteSlots(): HasMany
    {
        return $this->hasMany(SetTeamPokepasteSlot::class, 'set_team_pokepaste_id')->orderBy('slot_index');
    }

    private static function generateUniquePublicId(): int
    {
        do {
            $id = random_int(1_000_000_000_000_000, 9_223_372_036_854_775_807);
        } while (DB::table('set_team_pokepastes')->where('public_id', $id)->exists());

        return $id;
    }
}
