<?php

namespace App\Modules\Pokepaste\Support;

class PokepasteSlotDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function emptyOne(): array
    {
        return [
            'league_pokemon_id' => null,
            'ability' => '',
            'moves' => ['', '', '', ''],
            'version_group_held_item_id' => null,
            'nature' => null,
            'tera_type' => null,
            'evs' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function sixEmptySlots(): array
    {
        return array_fill(0, 6, self::emptyOne());
    }
}
