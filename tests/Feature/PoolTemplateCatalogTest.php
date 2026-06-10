<?php

use App\Models\User;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests from the pool template catalog', function () {
    $this->get(route('pool-templates.index'))->assertRedirect(route('login'));
});

it('shows the catalog for authenticated users', function () {
    $user = User::factory()->create();
    $vg = VersionGroup::query()->first();
    expect($vg)->not->toBeNull();

    LeaguePokemonTemplate::query()->create([
        'name' => 'Published catalog entry',
        'slug' => 'published-catalog-entry',
        'description' => 'For tests',
        'version_group_id' => $vg->id,
        'is_published' => true,
    ]);

    $this->actingAs($user)
        ->get(route('pool-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pool-templates/Index')
            ->has('templatesByGeneration'));
});

it('returns 404 for preview when the template is not published', function () {
    $user = User::factory()->create();
    $vg = VersionGroup::query()->first();
    expect($vg)->not->toBeNull();

    LeaguePokemonTemplate::query()->create([
        'name' => 'Draft template',
        'slug' => 'draft-template-slug',
        'description' => null,
        'version_group_id' => $vg->id,
        'is_published' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('pool-templates.preview', ['slug' => 'draft-template-slug']))
        ->assertNotFound();
});

it('returns published template JSON preview with rows', function () {
    $user = User::factory()->create();
    $vg = VersionGroup::query()->first();
    expect($vg)->not->toBeNull();

    $dex = Pokedex::query()->create([
        'nationaldex_id' => 99901,
        'name' => 'CatalogPreviewMon',
        'type1' => 'Normal',
    ]);

    $template = LeaguePokemonTemplate::query()->create([
        'name' => 'Preview template',
        'slug' => 'preview-template-slug',
        'description' => 'Desc',
        'version_group_id' => $vg->id,
        'is_published' => true,
    ]);

    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $template->id,
        'pokedex_id' => $dex->id,
        'cost' => 12,
    ]);

    $this->actingAs($user)
        ->getJson(route('pool-templates.preview', ['slug' => 'preview-template-slug']))
        ->assertOk()
        ->assertJsonPath('template.slug', 'preview-template-slug')
        ->assertJsonPath('rows.0.pokedex_id', $dex->id)
        ->assertJsonPath('rows.0.cost', 12)
        ->assertJsonPath('rows.0.name', 'CatalogPreviewMon');
});
