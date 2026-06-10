<?php

namespace App\Modules\Pokedex\Services;

use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokeApiMoveCache;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class PokedexFilterService
{
    /**
     * Distinct generations from {@see VersionGroup} (used for Pokédex / admin filters).
     *
     * @return list<int>
     */
    public static function generationFilterOptionInts(): array
    {
        return VersionGroup::query()
            ->distinct()
            ->orderBy('generation')
            ->pluck('generation')
            ->map(fn ($g): int => (int) $g)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function abilityFilterOptionsForVersionGroup(int $versionGroupId): array
    {
        return AbilityGenerationData::query()
            ->where('version_group_id', $versionGroupId)
            ->distinct()
            ->orderBy('ability_name')
            ->pluck('ability_name')
            ->map(fn ($name): string => (string) $name)
            ->values()
            ->all();
    }

    /**
     * @param  array{search?: string, type1?: string, type2?: string, generation?: int|null, game?: string, ability?: string, move?: string}  $filters
     * @param  list<int>|null  $excludePokedexIds
     */
    public function paginate(int $perPage, array $filters, ?array $excludePokedexIds = null): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        if ($search !== '') {
            return Pokedex::search($search)
                ->query(function (Builder $query) use ($filters, $excludePokedexIds): void {
                    $query->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id');
                    $this->applyFilterConstraints($query, $filters, $excludePokedexIds);
                    $query->orderBy('pokedex.name');
                })
                ->paginate($perPage)
                ->withQueryString();
        }

        $query = Pokedex::query()
            ->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id');

        $this->applyFilterConstraints($query, $filters, $excludePokedexIds);
        $query->orderBy('pokedex.name');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{search?: string, type1?: string, type2?: string, generation?: int|null, game?: string, ability?: string, move?: string}  $filters
     * @param  list<int>|null  $excludePokedexIds
     */
    private function applyFilterConstraints(Builder $query, array $filters, ?array $excludePokedexIds): void
    {
        $type1 = isset($filters['type1']) ? trim((string) $filters['type1']) : '';
        $type2 = isset($filters['type2']) ? trim((string) $filters['type2']) : '';
        $generation = $filters['generation'] ?? null;
        $game = isset($filters['game']) ? trim((string) $filters['game']) : '';
        $ability = isset($filters['ability']) ? trim((string) $filters['ability']) : '';
        $move = isset($filters['move']) ? trim((string) $filters['move']) : '';

        $query
            ->when($type1 !== '', function (Builder $q) use ($type1): void {
                $q->where(function (Builder $inner) use ($type1): void {
                    $inner->where('pokedex.type1', $type1)->orWhere('pokedex.type2', $type1);
                });
            })
            ->when($type2 !== '', function (Builder $q) use ($type2): void {
                $q->where(function (Builder $inner) use ($type2): void {
                    $inner->where('pokedex.type1', $type2)->orWhere('pokedex.type2', $type2);
                });
            })
            ->when($generation !== null, function (Builder $q) use ($generation): void {
                $q->whereIn('pokedex.id', function (QueryBuilder $sub) use ($generation): void {
                    $sub->select('pgd.pokedex_id')
                        ->from('pokemon_generation_data as pgd')
                        ->join('version_groups as vg', 'vg.id', '=', 'pgd.version_group_id')
                        ->where('vg.generation', (int) $generation);
                });
            })
            ->when($ability !== '', function (Builder $q) use ($game, $ability): void {
                $versionGroupId = $this->resolveVersionGroupId($game);
                $abilitySlug = $this->toSlug($ability);

                $q->whereIn('pokedex.id', function (QueryBuilder $sub) use ($versionGroupId, $abilitySlug): void {
                    $sub->select('pokedex_id')
                        ->from('abilities_generation_data')
                        ->where('version_group_id', $versionGroupId)
                        ->where('ability_name', 'like', $abilitySlug.'%');
                });
            })
            ->when($move !== '', function (Builder $q) use ($game, $move): void {
                $versionGroupId = $this->resolveVersionGroupId($game);
                $resolvedMove = $this->resolveMoveFilter($move);

                $q->whereIn('pokedex.id', function (QueryBuilder $sub) use ($versionGroupId, $resolvedMove): void {
                    $sub->select('pokedex_id')
                        ->from('pokemon_generation_data')
                        ->where('version_group_id', $versionGroupId)
                        ->where(function (QueryBuilder $inner) use ($resolvedMove): void {
                            if ($resolvedMove['id'] !== null) {
                                $inner->where('learnset', 'like', '%"move_id":'.$resolvedMove['id'].'%');
                            } else {
                                $inner->where('learnset', 'like', '%"move_name":"'.$resolvedMove['slug'].'"%');
                            }
                        });
                });
            })
            ->when($excludePokedexIds !== null && $excludePokedexIds !== [], function (Builder $q) use ($excludePokedexIds): void {
                $q->whereNotIn('pokedex.id', $excludePokedexIds);
            });
    }

    private function resolveVersionGroupId(string $gameSlug): int
    {
        if ($gameSlug !== '') {
            $id = VersionGroup::query()->where('slug', $gameSlug)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        $defaultSlug = (string) config('pokemon.default_version_group_slug');
        $defaultId = VersionGroup::query()->where('slug', $defaultSlug)->value('id');
        if ($defaultId !== null) {
            return (int) $defaultId;
        }

        return (int) VersionGroup::query()->orderByDesc('sort_order')->value('id');
    }

    /**
     * @return array{id: int|null, slug: string}
     */
    private function resolveMoveFilter(string $move): array
    {
        $slug = $this->toSlug($move);

        $cached = PokeApiMoveCache::query()
            ->where('name', $slug)
            ->first(['id', 'name']);

        if ($cached === null) {
            $cached = PokeApiMoveCache::query()
                ->where('name', 'like', $slug.'%')
                ->orderBy('name')
                ->first(['id', 'name']);
        }

        if ($cached !== null) {
            return [
                'id' => (int) $cached->id,
                'slug' => (string) $cached->name,
            ];
        }

        return [
            'id' => null,
            'slug' => $slug,
        ];
    }

    private function toSlug(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '_'], '-', $normalized);

        return preg_replace('/[^a-z0-9-]/', '', $normalized) ?? '';
    }
}
