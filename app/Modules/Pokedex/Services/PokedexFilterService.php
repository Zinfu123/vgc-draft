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

        $query = Pokedex::query()
            ->select('pokedex.id', 'pokedex.name', 'pokedex.sprite_url', 'pokedex.type1', 'pokedex.type2', 'pokedex.nationaldex_id')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('pokedex.name', 'like', '%'.$search.'%');
            })
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
                $query->whereHas('generationData.versionGroup', function (Builder $q) use ($generation): void {
                    $q->where('generation', (int) $generation);
                });
            })
            ->when($excludePokedexIds !== null && $excludePokedexIds !== [], function (Builder $query) use ($excludePokedexIds): void {
                $query->whereNotIn('pokedex.id', $excludePokedexIds);
            })
            ->orderBy('pokedex.name');

        return $query->paginate($perPage)->withQueryString();
    }
}
