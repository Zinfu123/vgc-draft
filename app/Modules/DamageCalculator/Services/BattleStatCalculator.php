<?php

namespace App\Modules\DamageCalculator\Services;

use App\Enums\PokemonNature;

class BattleStatCalculator
{
    /**
     * @param  array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}  $base
     * @param  array{hp?: int, atk?: int, def?: int, spa?: int, spd?: int, spe?: int}  $ev
     * @param  array{hp?: int, atk?: int, def?: int, spa?: int, spd?: int, spe?: int}  $iv
     * @return array{hp: int, atk: int, def: int, spa: int, spd: int, spe: int}
     */
    public function computeStatsAtLevel(array $base, int $level, PokemonNature $nature, array $ev = [], array $iv = []): array
    {
        $hpIv = (int) ($iv['hp'] ?? 31);
        $hpEv = (int) ($ev['hp'] ?? 0);
        $hp = (int) (floor((floor((2 * $base['hp'] + $hpIv + floor($hpEv / 4)) * $level / 100) + $level + 10)));

        $stat = function (string $key) use ($base, $level, $nature, $ev, $iv): int {
            $b = $base[$key];
            $e = (int) ($ev[$key] ?? 0);
            $i = (int) ($iv[$key] ?? 31);
            $raw = (int) (floor((floor((2 * $b + $i + floor($e / 4)) * $level / 100) + 5) * $nature->statMultiplier($key)));

            return $raw;
        };

        return [
            'hp' => $hp,
            'atk' => $stat('atk'),
            'def' => $stat('def'),
            'spa' => $stat('spa'),
            'spd' => $stat('spd'),
            'spe' => $stat('spe'),
        ];
    }

    public function offensiveStat(bool $physical, int $atk, int $spa, string $item): int
    {
        if ($physical) {
            $v = $atk;

            return match ($item) {
                'choice_band', 'choice-band' => (int) floor($v * 1.5),
                default => $v,
            };
        }

        $v = $spa;

        return match ($item) {
            'choice_specs', 'choice-specs' => (int) floor($v * 1.5),
            default => $v,
        };
    }

    public function defensiveStat(bool $physical, int $def, int $spd): int
    {
        return $physical ? $def : $spd;
    }
}
