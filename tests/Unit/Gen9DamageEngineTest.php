<?php

use App\Enums\PokemonNature;
use App\Modules\DamageCalculator\Services\BattleStatCalculator;
use App\Modules\DamageCalculator\Services\Gen9DamageEngine;
use App\Modules\DamageCalculator\ValueObjects\BattleParticipant;
use App\Modules\DamageCalculator\ValueObjects\MechanicsProfile;
use App\Modules\DamageCalculator\ValueObjects\ResolvedMove;
use App\Modules\Pokedex\Services\TypeEffectivenessTable;

it('computes neutral physical damage min and max at level 50', function () {
    $mechanics = new MechanicsProfile(
        formula: 'gen9',
        typeChartId: 'gen6_fairy',
        damageRollMin: 0.85,
        damageRollMax: 1.0,
        teraEnabled: true,
        defaultBattle: 'doubles',
    );
    $types = TypeEffectivenessTable::forChart($mechanics->typeChartId);
    $engine = new Gen9DamageEngine(new BattleStatCalculator);

    $base = ['hp' => 50, 'atk' => 50, 'def' => 50, 'spa' => 50, 'spd' => 50, 'spe' => 50];
    $attacker = new BattleParticipant(
        baseStats: $base,
        level: 50,
        nature: PokemonNature::Hardy,
        type1: 'Ghost',
        type2: null,
        teraType: null,
        terastallized: false,
        burned: false,
        item: 'none',
    );
    $defender = new BattleParticipant(
        baseStats: $base,
        level: 50,
        nature: PokemonNature::Hardy,
        type1: 'Normal',
        type2: null,
        teraType: null,
        terastallized: false,
        burned: false,
        item: 'none',
    );
    $move = new ResolvedMove(1, 'Test', 'normal', 'physical', 100);

    $r = $engine->damage($attacker, $defender, $move, $mechanics, $types);

    expect($r['status'])->toBe('ok')
        ->and($r['min'])->toBe(39)
        ->and($r['max'])->toBe(46);
});

it('applies stab for matching move type', function () {
    $mechanics = new MechanicsProfile(
        formula: 'gen9',
        typeChartId: 'gen6_fairy',
        damageRollMin: 0.85,
        damageRollMax: 1.0,
        teraEnabled: true,
        defaultBattle: 'doubles',
    );
    $types = TypeEffectivenessTable::forChart($mechanics->typeChartId);
    $engine = new Gen9DamageEngine(new BattleStatCalculator);

    $base = ['hp' => 50, 'atk' => 50, 'def' => 50, 'spa' => 50, 'spd' => 50, 'spe' => 50];
    $attacker = new BattleParticipant(
        baseStats: $base,
        level: 50,
        nature: PokemonNature::Hardy,
        type1: 'Fire',
        type2: null,
        teraType: null,
        terastallized: false,
        burned: false,
        item: 'none',
    );
    $defender = new BattleParticipant(
        baseStats: $base,
        level: 50,
        nature: PokemonNature::Hardy,
        type1: 'Normal',
        type2: null,
        teraType: null,
        terastallized: false,
        burned: false,
        item: 'none',
    );
    $move = new ResolvedMove(2, 'Flamethrower', 'fire', 'special', 100);

    $r = $engine->damage($attacker, $defender, $move, $mechanics, $types);

    expect($r['status'])->toBe('ok')
        ->and($r['max'])->toBeGreaterThan(46);
});
