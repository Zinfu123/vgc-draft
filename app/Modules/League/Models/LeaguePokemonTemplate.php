<?php

namespace App\Modules\League\Models;

use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaguePokemonTemplate extends Model
{
    protected $table = 'league_pokemon_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version_group_id',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * @return BelongsTo<VersionGroup, $this>
     */
    public function versionGroup(): BelongsTo
    {
        return $this->belongsTo(VersionGroup::class, 'version_group_id');
    }

    /**
     * @return HasMany<LeaguePokemonTemplateRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(LeaguePokemonTemplateRow::class, 'league_pokemon_template_id');
    }
}
