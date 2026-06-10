/** Gen VI+ chart — keep in sync with `TypeEffectivenessTable::MATRIX`. */

export const TYPE_ORDER = [
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
] as const;

export type TypeName = (typeof TYPE_ORDER)[number];

const MATRIX: number[][] = [
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

export function normalizeTypeName(raw: string): TypeName | null {
    const t = raw.trim();
    if (!t) {
        return null;
    }
    const lower = t.toLowerCase();
    for (const name of TYPE_ORDER) {
        if (name.toLowerCase() === lower) {
            return name;
        }
    }

    return null;
}

export function singleMultiplier(attackType: string, defendType: string): number {
    const atk = normalizeTypeName(attackType);
    const def = normalizeTypeName(defendType);
    if (!atk || !def) {
        return 1;
    }
    const ai = TYPE_ORDER.indexOf(atk);
    const di = TYPE_ORDER.indexOf(def);
    if (ai < 0 || di < 0) {
        return 1;
    }

    return MATRIX[ai][di];
}

export function multiplier(attackType: string, defenderType1: string, defenderType2: string | null | undefined, teraType: string | null | undefined): number {
    const atk = normalizeTypeName(attackType);
    if (!atk) {
        return 1;
    }

    const tera = teraType?.trim() ? normalizeTypeName(teraType) : null;
    if (tera) {
        return singleMultiplier(atk, tera);
    }

    const t1 = normalizeTypeName(defenderType1);
    if (!t1) {
        return 1;
    }
    let m = singleMultiplier(atk, t1);
    const t2Raw = defenderType2?.trim() ? defenderType2 : '';
    const t2 = t2Raw ? normalizeTypeName(t2Raw) : null;
    if (t2 && t2 !== t1) {
        m *= singleMultiplier(atk, t2);
    }

    return m;
}

export function bracketLabel(m: number): 'immune' | 'resist' | 'neutral' | 'super' {
    if (m === 0) {
        return 'immune';
    }
    if (m < 1) {
        return 'resist';
    }
    if (m > 1) {
        return 'super';
    }

    return 'neutral';
}
