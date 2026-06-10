<?php

namespace App\Kernel\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TeamCoveragePlanner
{
    /**
     * @return array{versionGroups: mixed, defaultVersionSlug: string, typeOrder: list<string>, myTeams: list<array{id: int, name: string, league_id: int, league_name: string}>}
     */
    public function showProps(int $userId): array;

    /**
     * @param  array{search?: string|null, type1?: string|null, type2?: string|null, generation?: int|null, per_page?: int}  $validated
     */
    public function searchPokedex(int $perPage, array $validated): LengthAwarePaginator;

    /**
     * @return array{pokemon: array{id: int, name: string, sprite_url: string|null, type1: string, type2: string|null}, game: array{slug: string, name: string, type1: string|null, type2: string|null}|null, abilities: list<array<string, mixed>>, learnset: list<array<string, mixed>>}
     */
    public function learnsetPayload(int $pokedexId, ?string $gameSlug): array;

    /**
     * @return array{team_id: int, league_id: int, version_group_slug: string, slots: list<array<string, mixed>>}
     */
    public function rosterPayload(int $teamId): array;
}
