<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pokedex extends Model
{
    protected $table = 'pokedex';

    protected $fillable = [
        'nationaldex_id',
        'name',
        'type1',
        'type2',
        'sprite_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nationaldex_id' => 'float',
        ];
    }

    public function league(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\League\Models\League::class, 'league_pokemon', 'pokedex_id')->withPivot('cost', 'id');
    }

    public function leaguePokemon(): HasMany
    {
        return $this->hasMany(\App\Modules\League\Models\LeaguePokemon::class, 'pokedex_id');
    }

    public function generationData(): HasMany
    {
        return $this->hasMany(PokemonGenerationData::class, 'pokedex_id');
    }
}
