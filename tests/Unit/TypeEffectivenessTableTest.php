<?php

use App\Modules\Pokedex\Services\TypeEffectivenessTable;

beforeEach(function () {
    $this->table = new TypeEffectivenessTable;
});

it('normalizes pokeapi slugs to canonical type names', function () {
    expect($this->table->normalizeTypeName('fire'))->toBe('Fire');
    expect($this->table->normalizeTypeName('FIRE'))->toBe('Fire');
    expect($this->table->normalizeTypeName('electric'))->toBe('Electric');
    expect($this->table->normalizeTypeName(''))->toBeNull();
    expect($this->table->normalizeTypeName('notatype'))->toBeNull();
});

it('computes known single-type matchups', function () {
    expect($this->table->singleMultiplier('Fire', 'Grass'))->toBe(2.0);
    expect($this->table->singleMultiplier('Fire', 'Water'))->toBe(0.5);
    expect($this->table->singleMultiplier('Electric', 'Ground'))->toBe(0.0);
    expect($this->table->singleMultiplier('Ghost', 'Normal'))->toBe(0.0);
    expect($this->table->singleMultiplier('Fighting', 'Normal'))->toBe(2.0);
});

it('stacks dual types by multiplying effectiveness', function () {
    // Fire vs Grass/Steel: 2 * 2 = 4
    expect($this->table->multiplier('Fire', 'Grass', 'Steel', null))->toBe(4.0);
    // Ground vs pure Electric: 2 (Electric single)
    expect($this->table->multiplier('Ground', 'Electric', null, null))->toBe(2.0);
    // Ground vs Electric/Flying: 2 * 0 = 0
    expect($this->table->multiplier('Ground', 'Electric', 'Flying', null))->toBe(0.0);
});

it('uses tera type as monotype defense when set', function () {
    // Ghost/Normal would resist Fighting x0; Tera Flying takes neutral Fighting
    expect($this->table->multiplier('Fighting', 'Ghost', 'Normal', null))->toBe(0.0);
    expect($this->table->multiplier('Fighting', 'Ghost', 'Normal', 'Flying'))->toBe(0.5);
});

it('returns one when attack type is unknown', function () {
    expect($this->table->multiplier('Unknown', 'Fire', 'Water', null))->toBe(1.0);
});

it('forChart uses the gen6 fairy matrix for gen9 id', function () {
    $gen9 = TypeEffectivenessTable::forChart('gen9');
    expect($gen9->chartId())->toBe('gen6_fairy')
        ->and($gen9->singleMultiplier('Fire', 'Grass'))->toBe(2.0);
});
