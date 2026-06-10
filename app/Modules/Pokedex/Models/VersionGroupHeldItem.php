<?php

namespace App\Modules\Pokedex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionGroupHeldItem extends Model
{
    protected $table = 'version_group_held_items';

    protected $fillable = [
        'version_group_id',
        'pokeapi_item_id',
        'name',
        'display_name_en',
        'cost',
        'sprite_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pokeapi_item_id' => 'integer',
            'cost' => 'integer',
        ];
    }

    public function versionGroup(): BelongsTo
    {
        return $this->belongsTo(VersionGroup::class, 'version_group_id');
    }

    public function resolvedSpriteUrl(): ?string
    {
        $direct = $this->sprite_url;
        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        $name = trim((string) $this->name);
        if ($name === '') {
            return null;
        }

        return 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/'.rawurlencode($name).'.png';
    }
}
