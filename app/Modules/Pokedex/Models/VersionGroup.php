<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersionGroup extends Model
{
    protected $table = 'version_groups';

    protected $fillable = [
        'slug',
        'generation',
        'sort_order',
        'name',
        'mechanics_config',
        'showdown_format_key',
        'showdown_ladder_rating',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'sort_order' => 'integer',
            'mechanics_config' => 'array',
            'showdown_ladder_rating' => 'integer',
        ];
    }

    public function pokemonGenerationData(): HasMany
    {
        return $this->hasMany(PokemonGenerationData::class, 'version_group_id');
    }

    public function heldItems(): HasMany
    {
        return $this->hasMany(VersionGroupHeldItem::class, 'version_group_id');
    }

    public function pokemonMoveVersionData(): HasMany
    {
        return $this->hasMany(PokemonMoveVersionData::class, 'version_group_id');
    }
}
