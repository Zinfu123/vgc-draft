<?php

namespace App\Modules\DamageCalculator\ValueObjects;

readonly class ResolvedMove
{
    public function __construct(
        public int $pokeapiMoveId,
        public string $name,
        public string $typeSlug,
        public string $damageClass,
        public ?int $power,
    ) {}

    public function isStatus(): bool
    {
        return $this->damageClass === 'status' || $this->power === null || $this->power === 0;
    }
}
