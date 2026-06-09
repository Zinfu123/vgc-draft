<?php

namespace App\Modules\Pokedex\Services;

use App\Kernel\Support\ShowdownFormatHelper;
use App\Modules\Pokedex\Models\Pokedex;

class DraftPoolPokedexResolver
{
    /**
     * Resolve a pokedex name to the canonical draft-pool species name.
     * Battle-only or cosmetic forms (e.g. Ash-Greninja) draft as the base species.
     */
    public function canonicalName(string $pokedexName): string
    {
        return ShowdownFormatHelper::speciesToMatchKey($pokedexName);
    }

    public function isCanonicalName(string $pokedexName): bool
    {
        return $this->canonicalName($pokedexName) === $pokedexName;
    }

    /**
     * Find the canonical pokedex row used for league pools and templates.
     */
    public function resolveByName(string $pokedexName): ?Pokedex
    {
        $canonical = $this->canonicalName($pokedexName);

        return $this->queryCanonicalRow($canonical);
    }

    public function resolveByNationaldexId(float $nationaldexId): ?Pokedex
    {
        $exact = Pokedex::query()->where('nationaldex_id', $nationaldexId)->first();
        if ($exact !== null) {
            return $this->resolvePokedex($exact);
        }

        $floored = floor($nationaldexId);
        if ($floored !== $nationaldexId) {
            $base = Pokedex::query()->where('nationaldex_id', $floored)->first();
            if ($base !== null) {
                return $this->resolvePokedex($base);
            }
        }

        return null;
    }

    public function resolvePokedex(Pokedex $pokedex): ?Pokedex
    {
        $name = (string) $pokedex->name;
        $canonical = $this->canonicalName($name);

        if ($canonical === $name && $this->isBaseNationaldexRow($pokedex)) {
            return $pokedex;
        }

        if ($canonical === strtolower($name) && $this->isBaseNationaldexRow($pokedex)) {
            return $pokedex;
        }

        return $this->queryCanonicalRow($canonical);
    }

    private function queryCanonicalRow(string $canonicalName): ?Pokedex
    {
        return Pokedex::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($canonicalName)])
            ->orderByRaw('CASE WHEN nationaldex_id = FLOOR(nationaldex_id) THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();
    }

    private function isBaseNationaldexRow(Pokedex $pokedex): bool
    {
        $nationaldexId = (float) $pokedex->getAttribute('nationaldex_id');

        return $nationaldexId === floor($nationaldexId);
    }
}
