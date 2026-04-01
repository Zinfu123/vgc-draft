<?php

namespace App\Modules\DamageCalculator\Services;

use App\Modules\DamageCalculator\ValueObjects\ResolvedMove;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\PokemonMoveVersionData;
use App\Modules\Pokedex\Models\VersionGroup;

class MoveDataResolver
{
    public function resolve(VersionGroup $versionGroup, int $pokeapiMoveId): ?ResolvedMove
    {
        $row = PokemonMoveVersionData::query()
            ->where('version_group_id', $versionGroup->id)
            ->where('pokeapi_move_id', $pokeapiMoveId)
            ->first();

        if ($row === null) {
            $fallback = PokeApiMoveCache::query()->find($pokeapiMoveId);
            if ($fallback === null) {
                return null;
            }

            return new ResolvedMove(
                pokeapiMoveId: (int) $fallback->id,
                name: (string) $fallback->name,
                typeSlug: (string) $fallback->type_slug,
                damageClass: (string) $fallback->damage_class,
                power: $fallback->power,
            );
        }

        return new ResolvedMove(
            pokeapiMoveId: (int) $row->pokeapi_move_id,
            name: (string) $row->name,
            typeSlug: (string) $row->type_slug,
            damageClass: (string) $row->damage_class,
            power: $row->power,
        );
    }
}
