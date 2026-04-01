<?php

namespace App\Modules\DamageCalculator\Services;

use App\Modules\DamageCalculator\ValueObjects\MechanicsProfile;
use App\Modules\Pokedex\Models\VersionGroup;

class MechanicsProfileResolver
{
    public function resolve(VersionGroup $versionGroup): MechanicsProfile
    {
        $cfg = is_array($versionGroup->mechanics_config) ? $versionGroup->mechanics_config : [];

        return new MechanicsProfile(
            formula: isset($cfg['formula']) && is_string($cfg['formula']) ? $cfg['formula'] : 'gen9',
            typeChartId: isset($cfg['type_chart']) && is_string($cfg['type_chart']) ? $cfg['type_chart'] : 'gen6_fairy',
            damageRollMin: isset($cfg['damage_roll_min']) && is_numeric($cfg['damage_roll_min']) ? (float) $cfg['damage_roll_min'] : 0.85,
            damageRollMax: isset($cfg['damage_roll_max']) && is_numeric($cfg['damage_roll_max']) ? (float) $cfg['damage_roll_max'] : 1.0,
            teraEnabled: ! isset($cfg['tera_enabled']) || $cfg['tera_enabled'] === true || $cfg['tera_enabled'] === 1 || $cfg['tera_enabled'] === '1',
            defaultBattle: isset($cfg['default_battle']) && is_string($cfg['default_battle']) ? $cfg['default_battle'] : 'doubles',
        );
    }
}
