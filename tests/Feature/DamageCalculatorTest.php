<?php

use App\Models\User;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests from the damage calculator', function () {
    $this->get(route('damage-calculator.index'))->assertRedirect(route('login'));
});

it('shows the calculator for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('damage-calculator.index'))->assertOk();
});

it('returns damage json for a valid calculation', function () {
    $vg = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($vg)->not->toBeNull();

    $dexA = Pokedex::query()->create([
        'nationaldex_id' => 99001,
        'name' => 'CalcAttacker',
        'type1' => 'Ghost',
    ]);
    $dexB = Pokedex::query()->create([
        'nationaldex_id' => 99002,
        'name' => 'CalcDefender',
        'type1' => 'Normal',
    ]);

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $dexA->id,
        'version_group_id' => $vg->id,
        'hp' => 50,
        'atk' => 50,
        'def' => 50,
        'spa' => 50,
        'spd' => 50,
        'spe' => 50,
        'type1' => 'Ghost',
        'learnset' => [
            ['move_id' => 99001, 'move_name' => 'test-move', 'method' => 'level-up', 'level' => 1],
        ],
    ]);
    PokemonGenerationData::factory()->create([
        'pokedex_id' => $dexB->id,
        'version_group_id' => $vg->id,
        'hp' => 50,
        'atk' => 50,
        'def' => 50,
        'spa' => 50,
        'spd' => 50,
        'spe' => 50,
        'type1' => 'Normal',
    ]);

    PokeApiMoveCache::query()->insert([
        'id' => 99001,
        'name' => 'Test Move',
        'type_slug' => 'normal',
        'damage_class' => 'physical',
        'power' => 100,
        'accuracy' => 100,
        'ailment_name' => null,
        'short_effect_en' => null,
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson(route('damage-calculator.calculate'), [
        'version_group_slug' => 'scarlet-violet',
        'move_id' => 99001,
        'attacker' => [
            'pokedex_id' => $dexA->id,
            'level' => 50,
            'nature' => \App\Enums\PokemonNature::Hardy->value,
            'terastallized' => false,
            'burned' => false,
            'item' => 'none',
        ],
        'defender' => [
            'pokedex_id' => $dexB->id,
            'level' => 50,
            'nature' => \App\Enums\PokemonNature::Hardy->value,
            'terastallized' => false,
            'burned' => false,
            'item' => 'none',
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('damage.status', 'ok')
        ->assertJsonPath('damage.min', 39)
        ->assertJsonPath('damage.max', 46);
});
