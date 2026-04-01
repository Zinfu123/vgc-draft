<?php

namespace App\Modules\DamageCalculator\Services;

use App\Modules\DamageCalculator\ValueObjects\BattleParticipant;
use App\Modules\DamageCalculator\ValueObjects\MechanicsProfile;
use App\Modules\DamageCalculator\ValueObjects\ResolvedMove;
use App\Modules\Pokedex\Services\TypeEffectivenessTable;

class Gen9DamageEngine
{
    public function __construct(
        private readonly BattleStatCalculator $stats = new BattleStatCalculator,
    ) {}

    /**
     * @return array{min: int|null, max: int|null, base_unrounded: float|null, status: string}
     */
    public function damage(
        BattleParticipant $attacker,
        BattleParticipant $defender,
        ResolvedMove $move,
        MechanicsProfile $mechanics,
        TypeEffectivenessTable $types,
    ): array {
        if (! $mechanics->isGen9Formula() || $move->isStatus()) {
            return ['min' => null, 'max' => null, 'base_unrounded' => null, 'status' => 'not_applicable'];
        }

        $physical = $move->damageClass === 'physical';
        $attackerComputed = $this->stats->computeStatsAtLevel(
            $attacker->baseStats,
            $attacker->level,
            $attacker->nature,
            $attacker->evs,
            $attacker->ivs,
        );
        $defenderComputed = $this->stats->computeStatsAtLevel(
            $defender->baseStats,
            $defender->level,
            $defender->nature,
            $defender->evs,
            $defender->ivs,
        );

        if ($physical) {
            $attackStat = $this->stats->offensiveStat(true, $attackerComputed['atk'], $attackerComputed['spa'], $attacker->item);
            if ($attacker->burned) {
                $attackStat = (int) floor($attackStat / 2);
            }
        } else {
            $attackStat = $this->stats->offensiveStat(false, $attackerComputed['atk'], $attackerComputed['spa'], $attacker->item);
        }

        $defenseStat = $this->stats->defensiveStat($physical, $defenderComputed['def'], $defenderComputed['spd']);
        if ($defenseStat <= 0) {
            $defenseStat = 1;
        }

        $power = (int) $move->power;
        $level = $attacker->level;

        $base = (int) floor((int) floor((int) floor((2 * $level / 5) + 2) * $power * $attackStat / $defenseStat) / 50) + 2;

        $moveType = (string) $move->typeSlug;
        $typeMod = $types->multiplier(
            $moveType,
            $defender->type1,
            $defender->type2,
            $defender->terastallized && $mechanics->teraEnabled ? $defender->teraType : null,
        );

        $stab = $this->stabMultiplier($attacker, $moveType, $types, $mechanics);

        $itemDamageMod = match ($attacker->item) {
            'life_orb', 'life-orb' => 1.3,
            default => 1.0,
        };

        $modified = $base * $stab * $typeMod * $itemDamageMod;

        $rounded = (int) floor($modified);
        $min = (int) floor($rounded * $mechanics->damageRollMin);
        $max = (int) floor($rounded * $mechanics->damageRollMax);

        return [
            'min' => $min,
            'max' => max($min, $max),
            'base_unrounded' => $modified,
            'status' => 'ok',
        ];
    }

    private function stabMultiplier(
        BattleParticipant $attacker,
        string $moveTypeSlug,
        TypeEffectivenessTable $types,
        MechanicsProfile $mechanics,
    ): float {
        $moveType = $types->normalizeTypeName($moveTypeSlug);
        if ($moveType === null) {
            return 1.0;
        }

        $matches = false;
        foreach ([$attacker->type1, $attacker->type2] as $t) {
            if ($t === null || trim($t) === '') {
                continue;
            }
            $tn = $types->normalizeTypeName($t);
            if ($tn !== null && $tn === $moveType) {
                $matches = true;
                break;
            }
        }

        if ($mechanics->teraEnabled && $attacker->terastallized) {
            $tera = $attacker->teraType !== null ? $types->normalizeTypeName($attacker->teraType) : null;
            if ($tera !== null && $tera === $moveType) {
                $matches = true;
            }
        }

        return $matches ? 1.5 : 1.0;
    }
}
