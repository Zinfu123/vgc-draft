<?php

namespace App\Modules\DamageCalculator\ValueObjects;

readonly class MechanicsProfile
{
    public function __construct(
        public string $formula,
        public string $typeChartId,
        public float $damageRollMin,
        public float $damageRollMax,
        public bool $teraEnabled,
        public string $defaultBattle,
    ) {}

    public function isGen9Formula(): bool
    {
        return $this->formula === 'gen9' || $this->formula === 'gen8';
    }
}
