<?php

namespace App\Modules\Pokedex\Services;

/**
 * Gen VI+ type chart (includes Fairy). Used for Scarlet/Violet planning.
 *
 * @phpstan-type TypeName string
 */
class TypeEffectivenessTable
{
    /**
     * Canonical attacking/defending type names (Title Case).
     */
    public const TYPE_ORDER = [
        'Normal',
        'Fire',
        'Water',
        'Electric',
        'Grass',
        'Ice',
        'Fighting',
        'Poison',
        'Ground',
        'Flying',
        'Psychic',
        'Bug',
        'Rock',
        'Ghost',
        'Dragon',
        'Dark',
        'Steel',
        'Fairy',
    ];

    /**
     * MATRIX[attacker index][defender index] = multiplier.
     *
     * @var list<list<float|int>>
     */
    private const MATRIX = [
        [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0.5, 0, 1, 1, 0.5, 1],
        [1, 0.5, 0.5, 1, 2, 2, 1, 1, 1, 1, 1, 2, 0.5, 1, 0.5, 1, 2, 1],
        [1, 2, 0.5, 1, 0.5, 1, 1, 1, 2, 1, 1, 1, 2, 1, 0.5, 1, 1, 1],
        [1, 1, 2, 0.5, 0.5, 1, 1, 1, 0, 2, 1, 1, 1, 1, 0.5, 1, 0.5, 1],
        [1, 0.5, 2, 1, 0.5, 1, 1, 0.5, 2, 0.5, 1, 0.5, 2, 1, 0.5, 1, 0.5, 1],
        [1, 0.5, 0.5, 1, 2, 0.5, 1, 1, 2, 2, 1, 1, 1, 1, 2, 1, 0.5, 1],
        [2, 1, 1, 1, 1, 2, 1, 0.5, 1, 0.5, 0.5, 0.5, 2, 0, 1, 2, 2, 0.5],
        [1, 1, 1, 1, 2, 1, 1, 0.5, 0.5, 1, 1, 1, 0.5, 0.5, 1, 1, 0, 2],
        [1, 2, 1, 2, 0.5, 1, 1, 2, 1, 0, 1, 0.5, 2, 1, 1, 1, 2, 1],
        [1, 1, 1, 0.5, 2, 1, 2, 1, 1, 1, 1, 2, 0.5, 1, 1, 1, 0.5, 1],
        [1, 1, 1, 1, 1, 1, 2, 2, 1, 1, 0.5, 1, 1, 1, 1, 0, 0.5, 1],
        [1, 0.5, 1, 1, 2, 1, 0.5, 0.5, 1, 0.5, 2, 1, 1, 0.5, 1, 2, 0.5, 0.5],
        [1, 2, 1, 1, 1, 2, 0.5, 1, 0.5, 2, 1, 2, 1, 1, 1, 1, 0.5, 1],
        [0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 1, 1, 2, 1, 0.5, 1, 1],
        [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 1, 0.5, 0],
        [1, 1, 1, 1, 1, 1, 0.5, 1, 1, 1, 2, 1, 1, 2, 1, 0.5, 1, 0.5],
        [1, 0.5, 0.5, 0.5, 1, 2, 1, 1, 1, 1, 1, 1, 2, 1, 1, 1, 0.5, 2],
        [1, 0.5, 1, 1, 1, 1, 2, 0.5, 1, 1, 1, 1, 1, 1, 2, 2, 1, 1],
    ];

    /**
     * Map lowercase / slug / mixed input to canonical Title Case type name.
     */
    public function normalizeTypeName(string $raw): ?string
    {
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }

        $lower = strtolower($trim);
        foreach (self::TYPE_ORDER as $name) {
            if (strtolower($name) === $lower) {
                return $name;
            }
        }

        return null;
    }

    public function isValidType(string $type): bool
    {
        return $this->normalizeTypeName($type) !== null;
    }

    /**
     * Effectiveness of one attacking type against a single defending type.
     */
    public function singleMultiplier(string $attackType, string $defendType): float
    {
        $atk = $this->normalizeTypeName($attackType);
        $def = $this->normalizeTypeName($defendType);
        if ($atk === null || $def === null) {
            return 1.0;
        }

        $ai = array_search($atk, self::TYPE_ORDER, true);
        $di = array_search($def, self::TYPE_ORDER, true);
        if ($ai === false || $di === false) {
            return 1.0;
        }

        return (float) self::MATRIX[$ai][$di];
    }

    /**
     * Effectiveness against a Pokémon: dual types, or Tera replaces both when set.
     *
     * @param  string|null  $teraType  When non-null, defender is treated as monotype Tera.
     */
    public function multiplier(
        string $attackType,
        string $defenderType1,
        ?string $defenderType2,
        ?string $teraType = null,
    ): float {
        $atk = $this->normalizeTypeName($attackType);
        if ($atk === null) {
            return 1.0;
        }

        $tera = $teraType !== null && trim($teraType) !== ''
            ? $this->normalizeTypeName($teraType)
            : null;

        if ($tera !== null) {
            return $this->singleMultiplier($atk, $tera);
        }

        $t1 = $this->normalizeTypeName($defenderType1);
        if ($t1 === null) {
            return 1.0;
        }

        $m = $this->singleMultiplier($atk, $t1);
        $t2 = $defenderType2 !== null && trim((string) $defenderType2) !== ''
            ? $this->normalizeTypeName($defenderType2)
            : null;

        if ($t2 !== null && $t2 !== $t1) {
            $m *= $this->singleMultiplier($atk, $t2);
        }

        return $m;
    }
}
