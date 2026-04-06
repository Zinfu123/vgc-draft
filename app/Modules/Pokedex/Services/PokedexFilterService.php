<?php

namespace App\Modules\Pokedex\Services;

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  array{search?: string, type1?: string, type2?: string, generation?: int|null}  $filters
     * @param  list<int>|null  $excludePokedexIds
     */
    public function paginate(int $perPage, array $filters, ?array $excludePokedexIds = null): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $type1 = isset($filters['type1']) ? trim((string) $filters['type1']) : '';
        $type2 = isset($filters['type2']) ? trim((string) $filters['type2']) : '';
        $generation = $filters['generation'] ?? null;

        if ($search !== '') {
            return Pokedex::search($search)
                ->query(function (Builder $query) use ($type1, $type2, $generation, $excludePokedexIds): void {
                    $query
                        ->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id')
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
                            $q->whereIn('pokedex.id', function (\Illuminate\Database\Query\Builder $sub) use ($generation): void {
                                $sub->select('pgd.pokedex_id')
                                    ->from('pokemon_generation_data as pgd')
                                    ->join('version_groups as vg', 'vg.id', '=', 'pgd.version_group_id')
                                    ->where('vg.generation', (int) $generation);
                            });
                        })
                        ->when($excludePokedexIds !== null && $excludePokedexIds !== [], function (Builder $q) use ($excludePokedexIds): void {
                            $q->whereNotIn('pokedex.id', $excludePokedexIds);
                        })
                        ->orderBy('pokedex.name');
                })
                ->paginate($perPage)
                ->withQueryString();
        }

        $query = Pokedex::query()
            ->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id')
            ->when($type1 !== '', function (Builder $query) use ($type1): void {
                $query->where(function (Builder $q) use ($type1): void {
                    $q->where('pokedex.type1', $type1)->orWhere('pokedex.type2', $type1);
                });
            })
            ->when($type2 !== '', function (Builder $query) use ($type2): void {
                $query->where(function (Builder $q) use ($type2): void {
                    $q->where('pokedex.type1', $type2)->orWhere('pokedex.type2', $type2);
                });
            })
            ->when($generation !== null, function (Builder $query) use ($generation): void {
                $query->whereIn('pokedex.id', function (\Illuminate\Database\Query\Builder $sub) use ($generation): void {
                    $sub->select('pgd.pokedex_id')
                        ->from('pokemon_generation_data as pgd')
                        ->join('version_groups as vg', 'vg.id', '=', 'pgd.version_group_id')
                        ->where('vg.generation', (int) $generation);
                });
            })
            ->when($excludePokedexIds !== null && $excludePokedexIds !== [], function (Builder $query) use ($excludePokedexIds): void {
                $query->whereNotIn('pokedex.id', $excludePokedexIds);
            })
            ->orderBy('pokedex.name');

        return $query->paginate($perPage)->withQueryString();
    }
}
