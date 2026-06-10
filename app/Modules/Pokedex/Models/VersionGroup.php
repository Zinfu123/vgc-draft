<?php

namespace App\Modules\Pokedex\Models;

use App\Modules\Pokedex\Enums\GenerationalMechanic;
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
        'showdown_format_key',
        'showdown_ladder_rating',
        'generational_mechanics',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'sort_order' => 'integer',
            'showdown_ladder_rating' => 'integer',
            'generational_mechanics' => 'array',
        ];
    }

    public function hasMechanic(GenerationalMechanic $mechanic): bool
    {
        return in_array($mechanic->value, $this->generational_mechanics ?? [], true);
    }

    public function isTeraMechanic(): bool
    {
        return $this->hasMechanic(GenerationalMechanic::Tera);
    }

    public function isMegaMechanic(): bool
    {
        return $this->hasMechanic(GenerationalMechanic::Mega);
    }

    public function pokemonGenerationData(): HasMany
    {
        return $this->hasMany(PokemonGenerationData::class, 'version_group_id');
    }

    public function heldItems(): HasMany
    {
        return $this->hasMany(VersionGroupHeldItem::class, 'version_group_id');
    }
}
