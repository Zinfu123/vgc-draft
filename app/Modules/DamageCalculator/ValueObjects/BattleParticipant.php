<?php

namespace App\Modules\DamageCalculator\ValueObjects;

use App\Enums\PokemonNature;

readonly class BattleParticipant
{
    /**
     * @param  array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}  $baseStats
     * @param  array{hp?: int, atk?: int, def?: int, spa?: int, spd?: int, spe?: int}  $evs
     * @param  array{hp?: int, atk?: int, def?: int, spa?: int, spd?: int, spe?: int}  $ivs
     */
    public function __construct(
        public array $baseStats,
        public int $level,
        public PokemonNature $nature,
        public string $type1,
        public ?string $type2,
        public ?string $teraType,
        public bool $terastallized,
        public bool $burned,
        public string $item,
        public array $evs = [],
        public array $ivs = [],
    ) {}
}
