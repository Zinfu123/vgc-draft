<?php

namespace App\Modules\V2\Pokedex\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class PokedexModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Pokedex';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'pokedex' => DB::table('pokedex')->count(),
            'pokemon_generation_data' => DB::table('pokemon_generation_data')->count(),
            'abilities_generation_data' => DB::table('abilities_generation_data')->count(),
            'version_groups' => DB::table('version_groups')->count(),
        ];

        $issues = [];

        $orphanedGameData = DB::table('pokemon_generation_data')
            ->leftJoin('pokedex', 'pokemon_generation_data.pokedex_id', '=', 'pokedex.id')
            ->whereNull('pokedex.id')
            ->count();

        if ($orphanedGameData > 0) {
            $issues[] = [
                'check' => 'orphaned_pokemon_generation_data',
                'message' => "{$orphanedGameData} pokemon_generation_data rows reference missing pokedex records.",
            ];
        }

        $orphanedAbilities = DB::table('abilities_generation_data')
            ->leftJoin('pokedex', 'abilities_generation_data.pokedex_id', '=', 'pokedex.id')
            ->whereNull('pokedex.id')
            ->count();

        if ($orphanedAbilities > 0) {
            $issues[] = [
                'check' => 'orphaned_abilities_generation_data',
                'message' => "{$orphanedAbilities} abilities_generation_data rows reference missing pokedex records.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}
