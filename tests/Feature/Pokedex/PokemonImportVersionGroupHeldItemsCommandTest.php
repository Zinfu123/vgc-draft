<?php

use App\Modules\Pokedex\Models\VersionGroupHeldItem;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('imports held items from PokeAPI into version_group_held_items', function () {
    config([
        'pokemon.pokeapi_url' => 'https://pokeapi.co/api/v2',
        'pokemon.pokeapi_held_item_category_ids' => [12],
    ]);

    Http::fake([
        'https://pokeapi.co/api/v2/item-category/12/' => Http::response([
            'items' => [
                ['name' => 'leftovers', 'url' => 'https://pokeapi.co/api/v2/item/211/'],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/item/211/' => Http::response([
            'id' => 211,
            'name' => 'leftovers',
            'cost' => 200,
            'flavor_text_entries' => [
                [
                    'version_group' => ['name' => 'scarlet-violet'],
                ],
            ],
            'names' => [
                ['name' => 'Leftovers', 'language' => ['name' => 'en']],
            ],
            'sprites' => ['default' => 'https://example.com/leftovers.png'],
        ], 200),
    ]);

    $this->artisan('pokemon:import-version-group-held-items', ['slug' => 'scarlet-violet'])
        ->assertExitCode(0);

    expect(VersionGroupHeldItem::query()->count())->toBe(1);
    $row = VersionGroupHeldItem::query()->first();
    expect($row->pokeapi_item_id)->toBe(211);
    expect($row->name)->toBe('leftovers');
    expect($row->display_name_en)->toBe('Leftovers');
});

it('imports berry pocket items when enumerating item-category medicine', function () {
    config([
        'pokemon.pokeapi_url' => 'https://pokeapi.co/api/v2',
        'pokemon.pokeapi_held_item_category_ids' => [3],
    ]);

    Http::fake([
        'https://pokeapi.co/api/v2/item-category/3/' => Http::response([
            'items' => [
                ['name' => 'cheri-berry', 'url' => 'https://pokeapi.co/api/v2/item/126/'],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/item/126/' => Http::response([
            'id' => 126,
            'name' => 'cheri-berry',
            'cost' => 80,
            'flavor_text_entries' => [
                [
                    'version_group' => ['name' => 'sword-shield'],
                ],
            ],
            'names' => [
                ['name' => 'Cheri Berry', 'language' => ['name' => 'en']],
            ],
            'sprites' => ['default' => 'https://example.com/cheri.png'],
        ], 200),
    ]);

    $this->artisan('pokemon:import-version-group-held-items', ['slug' => 'scarlet-violet'])
        ->assertExitCode(0);

    expect(VersionGroupHeldItem::query()->count())->toBe(1);
    expect(VersionGroupHeldItem::query()->first()->pokeapi_item_id)->toBe(126);
});

it('merges item-pocket categories with held-items when category ids are not overridden', function () {
    config([
        'pokemon.pokeapi_url' => 'https://pokeapi.co/api/v2',
        'pokemon.pokeapi_held_item_category_ids' => [],
        'pokemon.pokeapi_held_item_pocket_ids' => [5],
    ]);

    Http::fake([
        'https://pokeapi.co/api/v2/item-pocket/5/' => Http::response([
            'categories' => [
                ['name' => 'medicine', 'url' => 'https://pokeapi.co/api/v2/item-category/3/'],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/item-category/13/' => Http::response(['items' => []], 200),
        'https://pokeapi.co/api/v2/item-category/15/' => Http::response(['items' => []], 200),
        'https://pokeapi.co/api/v2/item-category/17/' => Http::response(['items' => []], 200),
        'https://pokeapi.co/api/v2/item-category/18/' => Http::response(['items' => []], 200),
        'https://pokeapi.co/api/v2/item-category/19/' => Http::response(['items' => []], 200),
        'https://pokeapi.co/api/v2/item-category/12/' => Http::response([
            'items' => [
                ['name' => 'leftovers', 'url' => 'https://pokeapi.co/api/v2/item/211/'],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/item-category/3/' => Http::response([
            'items' => [
                ['name' => 'cheri-berry', 'url' => 'https://pokeapi.co/api/v2/item/126/'],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/item/211/' => Http::response([
            'id' => 211,
            'name' => 'leftovers',
            'cost' => 200,
            'flavor_text_entries' => [
                ['version_group' => ['name' => 'scarlet-violet']],
            ],
            'names' => [
                ['name' => 'Leftovers', 'language' => ['name' => 'en']],
            ],
            'sprites' => ['default' => 'https://example.com/l.png'],
        ], 200),
        'https://pokeapi.co/api/v2/item/126/' => Http::response([
            'id' => 126,
            'name' => 'cheri-berry',
            'cost' => 80,
            'flavor_text_entries' => [
                ['version_group' => ['name' => 'sword-shield']],
            ],
            'names' => [
                ['name' => 'Cheri Berry', 'language' => ['name' => 'en']],
            ],
            'sprites' => ['default' => 'https://example.com/cheri.png'],
        ], 200),
    ]);

    $this->artisan('pokemon:import-version-group-held-items', ['slug' => 'scarlet-violet'])
        ->assertExitCode(0);

    expect(VersionGroupHeldItem::query()->count())->toBe(2);
});
