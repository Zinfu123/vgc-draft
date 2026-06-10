<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbilityGenerationData extends Model
{
    protected $table = 'abilities_generation_data';

    protected $fillable = [
        'pokedex_id',
        'version_group_id',
        'pokeapi_ability_id',
        'ability_name',
        'slot',
        'is_hidden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pokeapi_ability_id' => 'integer',
            'slot' => 'integer',
            'is_hidden' => 'boolean',
        ];
    }

    public function pokedex(): BelongsTo
    {
        return $this->belongsTo(Pokedex::class, 'pokedex_id');
    }

    public function versionGroup(): BelongsTo
    {
        return $this->belongsTo(VersionGroup::class, 'version_group_id');
    }
}
