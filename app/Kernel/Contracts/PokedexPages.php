<?php

namespace App\Kernel\Contracts;

interface PokedexPages
{
    /**
     * @param  array{search?: string|null, type1?: string|null, type2?: string|null, generation?: int|null, per_page?: int}  $validated
     * @return array{pokemon: mixed, filters: array{search: string, type1: string, type2: string, generation: int|null, per_page: int}, typeOptions: list<string>, generationFilterOptions: list<int>}
     */
    public function indexProps(array $validated): array;

    /**
     * @return array{pokemon: array{id: int, name: string, sprite_url: string|null, type1: string, type2: string|null, nationaldex_id: int}, versionGroups: mixed, selectedVersionSlug: string, gameData: array<string, mixed>|null}
     */
    public function showProps(int $pokedexId, ?string $requestedGameSlug): array;

    /**
     * @return array<string, mixed>
     */
    public function abilityProps(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function itemProps(int $id): array;
}
